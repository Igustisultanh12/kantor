<?php

namespace App\Services;

/**
 * Layanan Generator Streaming ZIP64
 *
 * Mengalirkan arsip berkas secara langsung ke output stream HTTP (php://output)
 * menggunakan metode STORE (tanpa kompresi ulang) dan spesifikasi ZIP64.
 *
 * Keunggulan arsitektur:
 * 1. Zero disk overhead: Tidak membuat berkas temporer di disk server.
 * 2. Bypass batas waktu 100 detik Cloudflare: Byte data pertama langsung dialirkan dalam 1-2 detik.
 * 3. ZIP64 compliant: Mendukung ukuran arsip di atas 4 GB dan ribuan berkas.
 * 4. Kompatibel penuh dengan Windows Explorer, WinRAR, 7-Zip, dan macOS.
 */
class StreamingZipService
{
    /** @var resource */
    private $output;

    /** @var int Posisi byte offset dalam aliran */
    private int $offset = 0;

    /** @var array Riwayat berkas dan direktori untuk Central Directory */
    private array $files = [];

    /**
     * @param resource $outputStream Resource stream tujuan (misal php://output atau file pointer)
     */
    public function __construct($outputStream)
    {
        if (!is_resource($outputStream)) {
            throw new \InvalidArgumentException('Output stream harus berupa resource yang valid.');
        }
        $this->output = $outputStream;
    }

    /**
     * Menambahkan entri direktori kosong ke dalam arsip
     */
    public function addEmptyDir(string $dirPath, ?int $timestamp = null): void
    {
        $dirPath = trim(str_replace('\\', '/', $dirPath), '/');
        if ($dirPath === '') {
            return;
        }
        $dirPath .= '/';
        $dirLen = strlen($dirPath);

        $time = $timestamp ?? time();
        $dosTime = (int)date('H', $time) << 11 | (int)date('i', $time) << 5 | (int)((int)date('s', $time) / 2);
        $dosDate = ((int)date('Y', $time) - 1980) << 9 | (int)date('m', $time) << 5 | (int)date('d', $time);

        $localHeaderOffset = $this->offset;

        // Local File Header untuk direktori (metode STORE, size 0, crc 0, flag UTF-8)
        $localHeader = pack(
            'VvvvvvVVVvv',
            0x04034b50, // Signature
            45,         // Version needed (4.5 untuk ZIP64)
            0x0800,     // General purpose flag: bit 11 = UTF-8
            0,          // Compression method: 0 = STORE
            $dosTime,
            $dosDate,
            0,          // CRC-32
            0,          // Compressed size
            0,          // Uncompressed size
            $dirLen,
            0           // Extra field length
        );

        fwrite($this->output, $localHeader . $dirPath);
        $this->flushOutput();
        $this->offset += strlen($localHeader) + $dirLen;

        $this->files[] = [
            'name' => $dirPath,
            'is_dir' => true,
            'offset' => $localHeaderOffset,
            'size' => 0,
            'crc32' => 0,
            'time' => $dosTime,
            'date' => $dosDate,
        ];
    }

    /**
     * Mengalirkan satu berkas ke dalam arsip menggunakan chunk callback
     *
     * @param string $zipPath Jalur berkas di dalam arsip ZIP
     * @param callable $chunkProvider Callback bertipe function(callable $writeChunk)
     * @param int|null $timestamp Waktu modifikasi berkas
     */
    public function addFileStream(string $zipPath, callable $chunkProvider, ?int $timestamp = null): void
    {
        $zipPath = ltrim(str_replace('\\', '/', $zipPath), '/');
        $nameLen = strlen($zipPath);

        $time = $timestamp ?? time();
        $dosTime = (int)date('H', $time) << 11 | (int)date('i', $time) << 5 | (int)((int)date('s', $time) / 2);
        $dosDate = ((int)date('Y', $time) - 1980) << 9 | (int)date('m', $time) << 5 | (int)date('d', $time);

        $localHeaderOffset = $this->offset;

        // Local Header dengan Data Descriptor (bit 3 = 0x08) dan UTF-8 (bit 11 = 0x0800)
        // Nilai CRC-32 dan ukuran diset 0 karena diisi pada Data Descriptor setelah streaming selesai
        $localHeader = pack(
            'VvvvvvVVVvv',
            0x04034b50, // Signature
            45,         // Version needed (4.5 untuk ZIP64)
            0x0808,     // Flag: bit 3 (Data Descriptor) + bit 11 (UTF-8)
            0,          // Compression method: 0 = STORE
            $dosTime,
            $dosDate,
            0,          // CRC-32 placeholder
            0,          // Compressed size placeholder
            0,          // Uncompressed size placeholder
            $nameLen,
            0           // Extra field length
        );

        fwrite($this->output, $localHeader . $zipPath);
        $this->flushOutput();
        $this->offset += strlen($localHeader) + $nameLen;

        $hashCtx = hash_init('crc32b');
        $bytesWritten = 0;

        // Alirkan kepingan data langsung ke output stream
        $chunkProvider(function ($chunk) use (&$hashCtx, &$bytesWritten) {
            if ($chunk === '' || $chunk === false) {
                return;
            }
            if (connection_aborted()) {
                return;
            }
            hash_update($hashCtx, $chunk);
            $len = strlen($chunk);
            $bytesWritten += $len;
            fwrite($this->output, $chunk);
            $this->flushOutput();
            $this->offset += $len;
        });

        $crc32 = hexdec(hash_final($hashCtx));

        // Data Descriptor ZIP64: Signature (4B) + CRC-32 (4B) + CompSize (8B) + UncompSize (8B)
        $descriptor = pack('VVP2', 0x08074b50, $crc32, $bytesWritten, $bytesWritten);
        fwrite($this->output, $descriptor);
        $this->flushOutput();
        $this->offset += strlen($descriptor);

        $this->files[] = [
            'name' => $zipPath,
            'is_dir' => false,
            'offset' => $localHeaderOffset,
            'size' => $bytesWritten,
            'crc32' => $crc32,
            'time' => $dosTime,
            'date' => $dosDate,
        ];
    }

    /**
     * Menyelesaikan pembuatan arsip ZIP64 dengan menuliskan Central Directory dan EOCD
     */
    public function finish(): void
    {
        $cdStartOffset = $this->offset;
        $numEntries = count($this->files);

        // 1. Tuliskan Central Directory Header untuk setiap berkas/folder
        foreach ($this->files as $file) {
            $nameLen = strlen($file['name']);
            $isDir = !empty($file['is_dir']);
            $extAttr = $isDir ? 0x41ed0010 : 0x81a40020;

            // Extra Field ZIP64: tag(2) + len(2) + origSize(8) + compSize(8) + offset(8) = 28 bytes
            $extraZip64 = pack('vvPPP', 0x0001, 24, $file['size'], $file['size'], $file['offset']);

            $cdHeader = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50, // Central Directory Signature
                45,         // Version made by (4.5)
                45,         // Version needed (4.5)
                0x0808,     // Flags
                0,          // Compression method: 0 = STORE
                $file['time'],
                $file['date'],
                $file['crc32'],
                0xFFFFFFFF, // Compressed size marker (baca dari extra field)
                0xFFFFFFFF, // Uncompressed size marker (baca dari extra field)
                $nameLen,
                strlen($extraZip64),
                0,          // File comment length
                0,          // Disk number start
                0,          // Internal file attributes
                $extAttr,   // External file attributes
                0xFFFFFFFF  // Local header offset marker (baca dari extra field)
            );

            fwrite($this->output, $cdHeader . $file['name'] . $extraZip64);
            $this->offset += strlen($cdHeader) + $nameLen + strlen($extraZip64);
        }

        $cdEndOffset = $this->offset;
        $cdSize = $cdEndOffset - $cdStartOffset;

        // 2. ZIP64 End of Central Directory Record (56 bytes)
        $zip64Eocd = pack(
            'VPvvVVPPPP',
            0x06064b50, // Signature
            44,         // Size of remaining record
            45,         // Version made by
            45,         // Version needed
            0,          // Disk number
            0,          // Disk with CD
            $numEntries,
            $numEntries,
            $cdSize,
            $cdStartOffset
        );
        fwrite($this->output, $zip64Eocd);
        $this->offset += strlen($zip64Eocd);

        // 3. ZIP64 End of Central Directory Locator (20 bytes)
        $zip64Locator = pack(
            'VVPV',
            0x07064b50,   // Signature
            0,            // Disk with ZIP64 EOCD
            $cdEndOffset, // Offset of ZIP64 EOCD
            1             // Total disks
        );
        fwrite($this->output, $zip64Locator);
        $this->offset += strlen($zip64Locator);

        // 4. Standard End of Central Directory Record (22 bytes)
        $eocd = pack(
            'VvvvvVVv',
            0x06054b50, // Signature
            0,          // Disk number
            0,          // Disk with CD
            0xFFFF,     // Num entries on disk (0xFFFF untuk penanda ZIP64)
            0xFFFF,     // Total entries (0xFFFF untuk penanda ZIP64)
            0xFFFFFFFF, // CD size marker (0xFFFFFFFF untuk penanda ZIP64)
            0xFFFFFFFF, // CD offset marker (0xFFFFFFFF untuk penanda ZIP64)
            0           // Comment length
        );
        fwrite($this->output, $eocd);
        $this->offset += strlen($eocd);

        $this->flushOutput();
    }

    /**
     * Memastikan data buffer terdorong keluar ke jaringan
     */
    private function flushOutput(): void
    {
        if (is_resource($this->output)) {
            fflush($this->output);
        }
        if (function_exists('flush')) {
            @flush();
        }
    }
}
