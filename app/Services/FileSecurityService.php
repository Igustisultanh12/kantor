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
     * DAFTAR EKSTENSI BERBAHAYA (EKSEKUTABEL & SKRIP SERVER)
     */
    protected static $blacklistedExtensions = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps', 'pht',
        'inc', 'exe', 'bat', 'cmd', 'sh', 'bash', 'zsh', 'vbs', 'vbe', 'js', 'jse', 
        'wsf', 'wsh', 'scr', 'ps1', 'ps2', 'psc1', 'cgi', 'pl', 'py', 'pyc', 'dll', 
        'jar', 'so', 'htaccess', 'htpasswd', 'ini', 'conf', 'asp', 'aspx', 'jsp', 'jspx'
    ];

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
     * Verifikasi Batas Direktori Fisik (Mencegah Directory Traversal LFI)
     */
    public static function verifySafeStoragePath(string $subPath): string
    {
        if (str_contains($subPath, '..') || str_contains($subPath, "\0")) {
            Log::critical("SECURITY ALERT [Directory Traversal Attempt]: {$subPath}");
            abort(403, 'Akses Ditolak: Deteksi upaya manipulasi direktori.');
        }

        $baseStorage = storage_path('app/public/backups');
        if (!file_exists($baseStorage)) {
            @mkdir($baseStorage, 0777, true);
        }
        $realBase = realpath($baseStorage);

        $targetFull = storage_path('app/public/' . ltrim($subPath, '/\\'));

        if (file_exists($targetFull)) {
            $realTarget = realpath($targetFull);
            if ($realTarget && $realBase && !str_starts_with($realTarget, $realBase)) {
                Log::critical("SECURITY ALERT [Path Escape Attempt]: {$realTarget} is outside {$realBase}");
                abort(403, 'Akses Ditolak: Berkas berada di luar zona penyimpanan aman.');
            }
        }

        return $targetFull;
    }

    /**
     * Validasi Keamanan Berkas Menyeluruh (Anti-Malware, Anti-Polyglot, Magic Bytes, Double Extension)
     */
    public static function validateFileSafety(string $sourcePath, string $originalName, int $maxBytes = 21474836480): void
    {
        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            throw new \Exception('Berkas tidak ditemukan atau tidak dapat dibaca oleh sistem.');
        }

        $fileSize = filesize($sourcePath);
        if ($fileSize <= 0) {
            throw new \Exception('Berkas kosong (0 byte) tidak dapat diproses.');
        }

        if ($fileSize > $maxBytes) {
            throw new \Exception('Ukuran berkas melebihi kuota maksimum yang diizinkan.');
        }

        // 1. PENCEGAHAN PATH TRAVERSAL PADA NAMA ASLI BERKAS
        $cleanName = basename($originalName);
        if ($cleanName !== $originalName || str_contains($originalName, '..') || str_contains($originalName, "\0")) {
            Log::critical("SECURITY ALERT [Path Traversal in Filename]: {$originalName}");
            throw new \Exception('Nama berkas memuat karakter terlarang (Path Traversal attempt).');
        }

        // 2. CEK EKSTENSI BERBAHAYA & DOUBLE EXTENSION ATTACK
        $parts = explode('.', strtolower($originalName));
        if (count($parts) > 1) {
            $lastExt = end($parts);
            if (in_array($lastExt, self::$blacklistedExtensions, true)) {
                Log::critical("SECURITY ALERT [Blocked Executable Upload]: {$originalName}");
                throw new \Exception("Ekstensi berkas .{$lastExt} dilarang keras demi alasan keamanan.");
            }

            for ($i = 1; $i < count($parts) - 1; $i++) {
                if (in_array($parts[$i], self::$blacklistedExtensions, true)) {
                    Log::critical("SECURITY ALERT [Double Extension Attack]: {$originalName}");
                    throw new \Exception("Manipulasi ekstensi ganda terdeteksi pada berkas: {$originalName}.");
                }
            }
        }

        // 3. PEMINDAIAN BINARY MAGIC BYTES UNTUK TIPE BERKAS UMUM
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $fp = @fopen($sourcePath, 'rb');
        if (!$fp) {
            throw new \Exception('Gagal membaca signature biner berkas.');
        }

        $header16 = fread($fp, 16);
        fseek($fp, 0);

        if (in_array($ext, ['jpg', 'jpeg'])) {
            if (substr($header16, 0, 3) !== "\xFF\xD8\xFF") {
                fclose($fp);
                throw new \Exception('Berkas tidak valid: Signature biner tidak sesuai dengan format JPEG asli.');
            }
        } elseif ($ext === 'png') {
            if (substr($header16, 0, 8) !== "\x89PNG\r\n\x1a\n") {
                fclose($fp);
                throw new \Exception('Berkas tidak valid: Signature biner tidak sesuai dengan format PNG asli.');
            }
        } elseif ($ext === 'gif') {
            if (substr($header16, 0, 6) !== 'GIF87a' && substr($header16, 0, 6) !== 'GIF89a') {
                fclose($fp);
                throw new \Exception('Berkas tidak valid: Signature biner tidak sesuai dengan format GIF asli.');
            }
        } elseif ($ext === 'pdf') {
            if (substr($header16, 0, 4) !== '%PDF') {
                fclose($fp);
                throw new \Exception('Berkas tidak valid: Signature biner tidak sesuai dengan format PDF asli.');
            }
        } elseif ($ext === 'arw') {
            $isTiff = (substr($header16, 0, 4) === "II\x2a\x00" || substr($header16, 0, 4) === "MM\x00\x2a");
            if (!$isTiff) {
                fclose($fp);
                throw new \Exception('Berkas tidak valid: Format foto Sony RAW (.ARW) rusak atau palsu.');
            }
        } elseif ($ext === 'zip' || $ext === 'docx' || $ext === 'xlsx') {
            if (substr($header16, 0, 4) !== "PK\x03\x04" && substr($header16, 0, 4) !== "PK\x05\x06") {
                fclose($fp);
                throw new \Exception('Berkas tidak valid: Signature biner paket ZIP/Office tidak sesuai.');
            }
        }

        // 4. DETEKSI WEB SHELL & POLYGLOT CODE INJECTION
        $headChunk = fread($fp, 8192);
        fseek($fp, max(0, $fileSize - 8192));
        $tailChunk = fread($fp, 8192);
        fclose($fp);

        $inspectPayload = strtolower($headChunk . ' ' . $tailChunk);
        $maliciousPatterns = [
            '<?php', '<?=', '<script', '<% ', '<%--', '__halt_compiler',
            'passthru(', 'shell_exec(', 'proc_open(', 'popen(',
            'eval(base64_decode', 'eval(gzinflate', 'eval($_'
        ];

        if (!in_array($ext, ['txt', 'csv', 'json', 'log', 'md'])) {
            foreach ($maliciousPatterns as $pattern) {
                if (str_contains($inspectPayload, $pattern)) {
                    Log::critical("SECURITY ALERT [Malicious Code Injected]: Terdeteksi '{$pattern}' pada berkas: {$originalName}");
                    throw new \Exception('Berkas ditolak oleh sistem keamanan: Terdeteksi indikasi skrip eksekusi berbahaya (Polyglot/Malware).');
                }
            }
        }

        // Khusus SVG: Scan menyeluruh terhadap Stored XSS
        if ($ext === 'svg') {
            $svgContent = @file_get_contents($sourcePath, false, null, 0, 65536);
            if ($svgContent) {
                $svgLower = strtolower($svgContent);
                if (str_contains($svgLower, '<script') || str_contains($svgLower, 'javascript:') || str_contains($svgLower, 'onload=') || str_contains($svgLower, 'onerror=')) {
                    Log::critical("SECURITY ALERT [SVG Stored XSS Attempt]: {$originalName}");
                    throw new \Exception('Berkas SVG ditolak: Memuat skrip eksekusi browser (XSS).');
                }
            }
        }
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
     * Enkripsi berkas langsung saat diunggah / dipindahkan ke storage dengan proteksi Stream DoS
     */
    public static function encryptAndStoreFile(string $sourcePath, string $targetFullPath, int $maxBytes = 21474836480): bool
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

        $totalBytesRead = 0;
        $startTime = time();
        $maxStreamDuration = 600; // Maksimal 10 menit per berkas

        while (!feof($in)) {
            // Proteksi Infinite Stream DoS & Slowloris Timeout
            if ((time() - $startTime) > $maxStreamDuration) {
                fclose($in);
                fclose($out);
                @unlink($targetFullPath);
                Log::critical("SECURITY ALERT [Stream DoS Timeout]: Pemrosesan berkas {$sourcePath} dihentikan karena melebihi batas waktu.");
                throw new \Exception('Aliran data dihentikan karena melampaui batas waktu pemrosesan streaming.');
            }

            $chunk = fread($in, self::CHUNK_SIZE);
            if ($chunk === false || $chunk === '') break;

            $totalBytesRead += strlen($chunk);
            if ($totalBytesRead > $maxBytes) {
                fclose($in);
                fclose($out);
                @unlink($targetFullPath);
                Log::critical("SECURITY ALERT [Payload Limit Exceeded]: Terbaca {$totalBytesRead} bytes melebihi batas {$maxBytes}.");
                throw new \Exception('Ukuran data streaming melampaui batas maksimum yang dialokasikan.');
            }

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
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ];

        return new StreamedResponse(function () use ($fullPath, $isEncrypted) {
            $fp = fopen($fullPath, 'rb');
            if (!$fp) return;

            $startTime = time();
            $maxStreamTime = 600;

            if ($isEncrypted) {
                fseek($fp, self::HEADER_LEN);
                $key = self::getKey();

                while (!feof($fp)) {
                    if ((time() - $startTime) > $maxStreamTime) break;

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
                    if ((time() - $startTime) > $maxStreamTime) break;
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
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ];

        return new StreamedResponse(function () use ($fullPath, $isEncrypted) {
            $fp = fopen($fullPath, 'rb');
            if (!$fp) return;

            $startTime = time();
            $maxStreamTime = 300;

            if ($isEncrypted) {
                fseek($fp, self::HEADER_LEN);
                $key = self::getKey();

                while (!feof($fp)) {
                    if ((time() - $startTime) > $maxStreamTime) break;

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
                    if ((time() - $startTime) > $maxStreamTime) break;
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

    /**
     * Baca isi berkas terdekripsi ke dalam memori secara aman
     */
    public static function getDecryptedContents(string $fullPath, int $maxBytes = 35 * 1024 * 1024): ?string
    {
        if (!file_exists($fullPath)) return null;

        if (!self::isEncrypted($fullPath)) {
            return @file_get_contents($fullPath);
        }

        $in = @fopen($fullPath, 'rb');
        if (!$in) return null;

        fseek($in, self::HEADER_LEN);
        $key = self::getKey();

        $buffer = '';
        $total = 0;

        while (!feof($in)) {
            $iv = fread($in, 16);
            if (strlen($iv) < 16) break;

            $lenBytes = fread($in, 4);
            if (strlen($lenBytes) < 4) break;

            $encLen = unpack('N', $lenBytes)[1];
            $encData = fread($in, $encLen);

            $plain = openssl_decrypt($encData, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($plain !== false) {
                $buffer .= $plain;
                $total += strlen($plain);
                if ($total > $maxBytes) break;
            }
        }

        fclose($in);
        return $buffer;
    }
}
