<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChromeBrowserService
{
    const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';
    const DOWNLOADS_DIR = 'downloads';

    /**
     * Dapatkan path penyimpanan berkas unduhan di server
     */
    public static function getDownloadsPath(): string
    {
        $path = storage_path('app/' . self::DOWNLOADS_DIR);
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
        return $path;
    }

    /**
     * Bersihkan dan standarisasi URL input
     */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (empty($url)) {
            return 'https://www.google.com';
        }

        // Jika bukan format URL (misal: "speedtest" atau "resep masakan"), ubah ke pencarian Google
        if (!preg_match('#^https?://#i', $url)) {
            if (str_contains($url, '.') && !str_contains($url, ' ') && !str_starts_with($url, '?')) {
                $url = 'https://' . $url;
            } else {
                $url = 'https://www.google.com/search?q=' . urlencode($url);
            }
        }

        return $url;
    }

    /**
     * Deteksi apakah URL atau header respons mengindikasikan berkas unduhan (binary / attachment)
     */
    public static function isDownloadable(string $url, string $contentType = '', string $contentDisposition = ''): bool
    {
        if (stripos($contentDisposition, 'attachment') !== false) {
            return true;
        }

        $downloadTypes = [
            'application/octet-stream',
            'application/zip',
            'application/x-zip-compressed',
            'application/x-tar',
            'application/gzip',
            'application/x-gzip',
            'application/x-bzip2',
            'application/x-7z-compressed',
            'application/x-rar-compressed',
            'application/pdf',
            'application/vnd.debian.binary-package',
            'application/x-executable',
            'application/x-msdos-program',
            'application/x-msi',
            'application/vnd.android.package-archive',
        ];

        foreach ($downloadTypes as $type) {
            if (stripos($contentType, $type) !== false) {
                return true;
            }
        }

        $downloadExtensions = [
            '.zip', '.tar', '.gz', '.tgz', '.bz2', '.7z', '.rar',
            '.deb', '.rpm', '.apk', '.exe', '.msi', '.dmg', '.iso',
            '.bin', '.img', '.docx', '.xlsx', '.pptx', '.mp4', '.mkv'
        ];

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        foreach ($downloadExtensions as $ext) {
            if (str_ends_with(strtolower($path), $ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ekstrak nama berkas dari header atau URL
     */
    public static function extractFileName(string $url, string $contentDisposition = ''): string
    {
        if (!empty($contentDisposition) && preg_match('/filename\*?=(?:UTF-8\'\')?["\']?([^"\';]+)["\']?/i', $contentDisposition, $matches)) {
            $name = rawurldecode($matches[1]);
            return basename($name);
        }

        $path = parse_url($url, PHP_URL_PATH);
        $name = basename($path);
        if (empty($name) || !str_contains($name, '.')) {
            $name = 'unduhan_' . date('Ymd_His') . '.bin';
        }

        return preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);
    }

    /**
     * Jelajahi halaman web melalui server proxy (menghapus X-Frame-Options & CSP)
     */
    public static function browse(string $url)
    {
        $targetUrl = self::normalizeUrl($url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 6);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
            'Sec-Ch-Ua: "Chromium";v="128", "Google Chrome";v="128", "Not=A?Brand";v="99"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Windows"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
        ]);

        // Simpan header respons
        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        });

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'text/html';
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $targetUrl;
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || !empty($curlError)) {
            return response(self::renderErrorPage($targetUrl, $curlError ?: 'Gagal menyambung ke situs web.'), 502, [
                'Content-Type' => 'text/html; charset=UTF-8'
            ]);
        }

        $contentDisposition = $responseHeaders['content-disposition'] ?? '';

        // Jika respons berupa berkas unduhan
        if (self::isDownloadable($effectiveUrl, $contentType, $contentDisposition)) {
            $fileName = self::extractFileName($effectiveUrl, $contentDisposition);
            return response($body, 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="' . addslashes($fileName) . '"',
                'Content-Length' => strlen($body),
            ]);
        }

        // Jika respons berupa dokumen HTML, lakukan injeksi base href dan jembatan event
        if (stripos($contentType, 'text/html') !== false) {
            $processedHtml = self::processHtml($body, $effectiveUrl);
            return response($processedHtml, $httpCode ?: 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Effective-Url' => $effectiveUrl,
            ]);
        }

        // Konten lainnya (gambar, json, plain text)
        return response($body, $httpCode ?: 200, [
            'Content-Type' => $contentType,
        ]);
    }

    /**
     * Memproses HTML untuk injeksi <base> tag dan jembatan event Chrome
     */
    protected static function processHtml(string $html, string $effectiveUrl): string
    {
        // Hilangkan meta tag pelindung frame yang kaku
        $html = preg_replace('/<meta[^>]*http-equiv=["\']?X-Frame-Options["\']?[^>]*>/i', '', $html);
        $html = preg_replace('/<meta[^>]*http-equiv=["\']?Content-Security-Policy["\']?[^>]*>/i', '', $html);

        $baseTag = '<base href="' . htmlspecialchars($effectiveUrl, ENT_QUOTES, 'UTF-8') . '">';
        
        $script = <<<HTML
<script>
(function() {
    function getProxyUrl(target) {
        return '/admin/system-check/chrome/browse?url=' + encodeURIComponent(target);
    }

    // Intersepsi klik tautan di dalam iframe agar tetap lewat peramban Chrome
    document.addEventListener('click', function(e) {
        var a = e.target.closest('a');
        if (a && a.href && !a.href.startsWith('javascript:') && !a.href.startsWith('#')) {
            e.preventDefault();
            window.location.href = getProxyUrl(a.href);
        }
    }, true);

    // Intersepsi pengiriman form pencarian/GET
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (form && form.action) {
            var method = (form.method || 'GET').toUpperCase();
            if (method === 'GET') {
                e.preventDefault();
                var formData = new FormData(form);
                var params = new URLSearchParams(formData).toString();
                var fullUrl = form.action + (form.action.indexOf('?') !== -1 ? '&' : '?') + params;
                window.location.href = getProxyUrl(fullUrl);
            }
        }
    }, true);

    // Laporkan judul dan URL aktif ke bilah alamat (Omnibar) induk
    function notifyParent() {
        try {
            window.parent.postMessage({
                type: 'CHROME_PAGE_LOADED',
                title: document.title || '{$effectiveUrl}',
                url: '{$effectiveUrl}'
            }, '*');
        } catch(err) {}
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        notifyParent();
    } else {
        window.addEventListener('DOMContentLoaded', notifyParent);
    }
})();
</script>
HTML;

        if (stripos($html, '<head>') !== false) {
            $html = preg_replace('/<head>/i', "<head>\n" . $baseTag . "\n" . $script, $html, 1);
        } elseif (stripos($html, '<head ') !== false) {
            $html = preg_replace('/(<head[^>]*>)/i', "$1\n" . $baseTag . "\n" . $script, $html, 1);
        } else {
            $html = $baseTag . "\n" . $script . "\n" . $html;
        }

        return $html;
    }

    /**
     * Halaman HTML ramah saat terjadi galat navigasi
     */
    protected static function renderErrorPage(string $url, string $errorMessage): string
    {
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $safeMsg = htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Situs Tidak Dapat Dijangkau - SINDEN Chrome</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f8fafc; color: #1e293b; padding: 40px 20px; display: flex; justify-content: center; align-items: center; min-height: 80vh; margin: 0; }
        .box { background: #ffffff; border-radius: 20px; padding: 32px; max-width: 540px; width: 100%; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); text-align: center; }
        .icon { width: 56px; height: 56px; margin: 0 auto 16px; background: #fee2e2; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #dc2626; font-size: 24px; }
        h2 { margin: 0 0 8px; font-size: 18px; font-weight: 800; text-transform: uppercase; letter-spacing: -0.5px; }
        p { font-size: 13px; color: #64748b; line-height: 1.5; margin: 0 0 16px; }
        .url { font-family: monospace; font-size: 11px; background: #f1f5f9; padding: 8px 12px; border-radius: 8px; word-break: break-all; color: #0f172a; margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .btn-group { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .btn { padding: 10px 18px; font-size: 12px; font-weight: 700; border-radius: 10px; cursor: pointer; text-decoration: none; border: none; transition: 0.2s; }
        .btn-primary { background: #4f46e5; color: #fff; }
        .btn-primary:hover { background: #4338ca; }
        .btn-outline { background: #f1f5f9; color: #334155; }
        .btn-outline:hover { background: #e2e8f0; }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">⚠️</div>
        <h2>Situs Tidak Dapat Dijangkau</h2>
        <p>{$safeMsg}</p>
        <div class="url">{$safeUrl}</div>
        <div class="btn-group">
            <button onclick="window.location.reload()" class="btn btn-primary">Muat Ulang</button>
            <button onclick="window.location.href='/admin/system-check/chrome/browse?url=https://www.google.com'" class="btn btn-outline">Beranda Google</button>
            <a href="{$safeUrl}" target="_blank" class="btn btn-outline">Buka di Tab Asli</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Unduh berkas langsung ke peramban pengguna (Streaming Download ke Laptop/HP)
     */
    public static function streamDownloadToClient(string $url): StreamedResponse
    {
        $targetUrl = self::normalizeUrl($url);

        return new StreamedResponse(function () use ($targetUrl) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $targetUrl);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
            curl_setopt($ch, CURLOPT_TIMEOUT, 600); // 10 menit
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) {
                if (connection_aborted()) {
                    return 0;
                }
                echo $chunk;
                flush();
                return strlen($chunk);
            });

            curl_exec($ch);
            curl_close($ch);
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . addslashes(self::extractFileName($targetUrl)) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Unduh berkas internet langsung ke penyimpanan VPS server (storage/app/downloads)
     */
    public static function downloadToServer(string $url): array
    {
        $targetUrl = self::normalizeUrl($url);
        $saveDir = self::getDownloadsPath();
        $fileName = self::extractFileName($targetUrl);

        // Hindari overwrite jika nama berkas sudah ada
        $base = pathinfo($fileName, PATHINFO_FILENAME);
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $finalName = $fileName;
        $counter = 1;

        while (File::exists($saveDir . '/' . $finalName)) {
            $finalName = $base . "_{$counter}" . ($ext ? ".{$ext}" : "");
            $counter++;
        }

        $destPath = $saveDir . '/' . $finalName;
        $fp = @fopen($destPath, 'w+b');
        if (!$fp) {
            throw new \Exception("Gagal membuka izin tulis berkas di server: {$destPath}");
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_TIMEOUT, 900); // 15 menit
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $downloadSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode >= 400) {
            @unlink($destPath);
            throw new \Exception($err ?: "Server sumber merespons dengan kode HTTP {$httpCode}");
        }

        $realSize = File::exists($destPath) ? filesize($destPath) : $downloadSize;

        return [
            'file_name' => $finalName,
            'file_path' => $destPath,
            'size_bytes' => $realSize,
            'size_human' => self::formatBytes($realSize),
            'downloaded_at' => now()->format('d M Y, H:i:s'),
            'original_url' => $targetUrl,
        ];
    }

    /**
     * Dapatkan daftar berkas unduhan yang tersimpan di server
     */
    public static function getServerDownloads(): array
    {
        $saveDir = self::getDownloadsPath();
        $files = File::files($saveDir);

        $result = [];
        foreach ($files as $file) {
            $size = $file->getSize();
            $result[] = [
                'name' => $file->getFilename(),
                'size_bytes' => $size,
                'size_human' => self::formatBytes($size),
                'modified_at' => date('d M Y, H:i:s', $file->getMTime()),
                'extension' => strtolower($file->getExtension()),
            ];
        }

        // Urutkan dari yang paling baru
        usort($result, fn($a, $b) => strcmp($b['modified_at'], $a['modified_at']));

        return $result;
    }

    /**
     * Hapus berkas unduhan dari penyimpanan server
     */
    public static function deleteServerDownload(string $filename): bool
    {
        $sanitized = basename($filename);
        $path = self::getDownloadsPath() . '/' . $sanitized;

        if (File::exists($path)) {
            return File::delete($path);
        }

        return false;
    }

    /**
     * Cek apakah service Docker Chromium Web GUI (noVNC) dapat dihubungi
     */
    public static function checkDockerStatus(string $url = 'http://127.0.0.1:3001'): array
    {
        try {
            $res = Http::timeout(2)->get($url);
            return [
                'online' => $res->successful() || $res->status() === 200 || $res->status() === 401,
                'status_code' => $res->status(),
                'url' => $url,
            ];
        } catch (\Throwable $e) {
            return [
                'online' => false,
                'error' => $e->getMessage(),
                'url' => $url,
            ];
        }
    }

    /**
     * Format byte ke string yang mudah dibaca manusia
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int)floor(log($bytes, 1024)), count($units) - 1);
        return round($bytes / pow(1024, $power), $precision) . ' ' . $units[$power];
    }
}
