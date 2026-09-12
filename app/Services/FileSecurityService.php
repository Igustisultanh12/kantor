<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileSecurityService
{
    const HEADER_MAGIC = 'SINDEN_SEC_V1';
    const HEADER_LEN = 16;
    const CHUNK_SIZE = 65536; // 64 KB per chunk

    /**
     * Dapatkan encryption key 256-bit turunan dari APP_KEY
     */
    protected static function getKey(): string
    {
        $appKey = config('app.key');
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }
        return hash('sha256', $appKey ?: 'sinden-default-secure-key', true);
    }

    /**
     * Cek apakah berkas di disk memiliki tanda pengamanan AES-256
     */
    public static function isEncrypted(string $fullPath): bool
    {
        if (!file_exists($fullPath) || filesize($fullPath) < self::HEADER_LEN) {
            return false;
        }

        $fp = @fopen($fullPath, 'rb');
        if (!$fp) return false;

        $header = fread($fp, self::HEADER_LEN);
        fclose($fp);

        return $header === pack('a16', self::HEADER_MAGIC);
    }

    /**
     * Enkripsi berkas langsung saat diunggah / dipindahkan ke storage
     */
    public static function encryptAndStoreFile(string $sourcePath, string $targetFullPath): bool
    {
        $targetDir = dirname($targetFullPath);
        if (!file_exists($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $in = @fopen($sourcePath, 'rb');
        if (!$in) return false;

        $out = @fopen($targetFullPath, 'wb');
        if (!$out) {
            fclose($in);
            return false;
        }

        $key = self::getKey();

        // Tulis header identitas pengamanan berkas (16 bytes)
        fwrite($out, pack('a16', self::HEADER_MAGIC));

        while (!feof($in)) {
            $chunk = fread($in, self::CHUNK_SIZE);
            if ($chunk === false || $chunk === '') break;

            $iv = random_bytes(16);
            $encrypted = openssl_encrypt($chunk, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

            // Simpan IV (16 byte) + Panjang Ciphertext (4 byte) + Ciphertext
            fwrite($out, $iv);
            fwrite($out, pack('N', strlen($encrypted)));
            fwrite($out, $encrypted);
        }

        fclose($in);
        fclose($out);

        return true;
    }

    /**
     * Alirkan berkas terdekripsi langsung ke peramban (Download)
     */
    public static function streamDecryptedDownload(string $fullPath, string $downloadName, ?string $mimeType = null): StreamedResponse
    {
        if (!file_exists($fullPath)) {
            abort(404, 'Berkas fisik tidak ditemukan.');
        }

        $mime = $mimeType ?: (@mime_content_type($fullPath) ?: 'application/octet-stream');
        $isEncrypted = self::isEncrypted($fullPath);

        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . addslashes($downloadName) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ];

        return new StreamedResponse(function () use ($fullPath, $isEncrypted) {
            $fp = fopen($fullPath, 'rb');
            if (!$fp) return;

            if ($isEncrypted) {
                // Lewati magic header
                fseek($fp, self::HEADER_LEN);
                $key = self::getKey();

                while (!feof($fp)) {
                    $iv = fread($fp, 16);
                    if (strlen($iv) < 16) break;

                    $lenBytes = fread($fp, 4);
                    if (strlen($lenBytes) < 4) break;

                    $encLen = unpack('N', $lenBytes)[1];
                    $encData = fread($fp, $encLen);

                    $plain = openssl_decrypt($encData, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                    if ($plain !== false) {
                        echo $plain;
                        flush();
                    }
                }
            } else {
                // Berkas reguler / legacy
                while (!feof($fp)) {
                    echo fread($fp, 65536);
                    flush();
                }
            }

            fclose($fp);
        }, 200, $headers);
    }

    /**
     * Alirkan berkas terdekripsi langsung ke peramban secara inline (Pratinjau Gambar/PDF)
     */
    public static function streamDecryptedInline(string $fullPath, string $fileName, ?string $mimeType = null): StreamedResponse
    {
        if (!file_exists($fullPath)) {
            abort(404, 'Berkas fisik tidak ditemukan.');
        }

        $mime = $mimeType;
        if (!$mime) {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $mimeMap = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                'txt' => 'text/plain',
                'csv' => 'text/csv',
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
            ];
            $mime = $mimeMap[$ext] ?? (@mime_content_type($fullPath) ?: 'application/octet-stream');
        }

        $isEncrypted = self::isEncrypted($fullPath);

        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($fileName) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ];

        return new StreamedResponse(function () use ($fullPath, $isEncrypted) {
            $fp = fopen($fullPath, 'rb');
            if (!$fp) return;

            if ($isEncrypted) {
                fseek($fp, self::HEADER_LEN);
                $key = self::getKey();

                while (!feof($fp)) {
                    $iv = fread($fp, 16);
                    if (strlen($iv) < 16) break;

                    $lenBytes = fread($fp, 4);
                    if (strlen($lenBytes) < 4) break;

                    $encLen = unpack('N', $lenBytes)[1];
                    $encData = fread($fp, $encLen);

                    $plain = openssl_decrypt($encData, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                    if ($plain !== false) {
                        echo $plain;
                        flush();
                    }
                }
            } else {
                while (!feof($fp)) {
                    echo fread($fp, 65536);
                    flush();
                }
            }

            fclose($fp);
        }, 200, $headers);
    }

    /**
     * Dapatkan file sementara terdekripsi (digunakan saat perlu path fisik lokal, misal kompresi ZIP atau konversi)
     */
    public static function createDecryptedTempFile(string $fullPath): ?string
    {
        if (!file_exists($fullPath)) return null;

        // Jika tidak terenkripsi, kembalikan path aslinya langsung tanpa perlu duplikasi
        if (!self::isEncrypted($fullPath)) {
            return $fullPath;
        }

        $tempDir = storage_path('app/temp_dec');
        if (!file_exists($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }

        $tempPath = $tempDir . '/tmp_' . uniqid() . '_' . basename($fullPath);
        $in = @fopen($fullPath, 'rb');
        if (!$in) return null;

        $out = @fopen($tempPath, 'wb');
        if (!$out) {
            fclose($in);
            return null;
        }

        fseek($in, self::HEADER_LEN);
        $key = self::getKey();

        while (!feof($in)) {
            $iv = fread($in, 16);
            if (strlen($iv) < 16) break;

            $lenBytes = fread($in, 4);
            if (strlen($lenBytes) < 4) break;

            $encLen = unpack('N', $lenBytes)[1];
            $encData = fread($in, $encLen);

            $plain = openssl_decrypt($encData, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($plain !== false) {
                fwrite($out, $plain);
            }
        }

        fclose($in);
        fclose($out);

        return $tempPath;
    }
}
