<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ThumbnailService
{
    const THUMB_MAX_WIDTH = 320;
    const THUMB_MAX_HEIGHT = 320;
    const THUMB_QUALITY = 75;

    /**
     * Sajikan HTTP response untuk berkas thumbnail dengan caching peramban maksimal (super enteng & cepat)
     */
    public static function getThumbnailResponse(string $fullPath, string $originalFileName, bool $isArw = false)
    {
        if (!file_exists($fullPath)) {
            abort(404, 'Berkas fisik tidak ditemukan.');
        }

        $thumbPath = self::getOrCreateThumbnail($fullPath, $originalFileName, $isArw);

        if (!$thumbPath || !file_exists($thumbPath) || filesize($thumbPath) < 50) {
            abort(404, 'Gagal memuat pratinjau thumbnail.');
        }

        $isWebp = str_ends_with($thumbPath, '.webp');
        $mime = $isWebp ? 'image/webp' : 'image/jpeg';
        $etag = '"' . md5_file($thumbPath) . '"';

        // Cek conditional GET (HTTP 304 Not Modified)
        $ifNoneMatch = request()->header('If-None-Match');
        if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
            return response('', 304)->withHeaders([
                'Cache-Control' => 'public, max-age=2592000, immutable',
                'ETag' => $etag,
            ]);
        }

        return response()->file($thumbPath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=2592000, immutable',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Dapatkan path berkas thumbnail di disk, atau hasilkan baru jika belum ada
     */
    public static function getOrCreateThumbnail(string $fullPath, string $originalFileName, bool $isArw = false): ?string
    {
        $cacheDir = storage_path('app/public/cache_thumbs');
        if (!file_exists($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $fileMtime = @filemtime($fullPath) ?: 0;
        $fileSize = @filesize($fullPath) ?: 0;
        $useWebp = function_exists('imagewebp');
        $ext = $useWebp ? 'webp' : 'jpg';

        $cacheKey = md5("thumb_{$fullPath}_{$fileMtime}_{$fileSize}_320");
        $cachedThumbPath = $cacheDir . '/' . $cacheKey . '.' . $ext;

        // 1. FAST PATH: Jika thumbnail sudah ada dan valid, langsung kembalikan (< 1 ms)
        if (file_exists($cachedThumbPath) && filesize($cachedThumbPath) > 50) {
            return $cachedThumbPath;
        }

        // 2. STAMPEDE MUTEX PROTECTION (Cegah puluhan worker membuat thumbnail yang sama serempak)
        $lockFile = $cachedThumbPath . '.lock';
        $lockFp = @fopen($lockFile, 'c');
        if ($lockFp) {
            if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
                // Tunggu proses yang sedang berjalan hingga max 1.5 detik
                $waited = 0;
                while ($waited < 15) {
                    usleep(100000); // 100ms
                    $waited++;
                    if (file_exists($cachedThumbPath) && filesize($cachedThumbPath) > 50) {
                        fclose($lockFp);
                        return $cachedThumbPath;
                    }
                }
                fclose($lockFp);
                $lockFp = @fopen($lockFile, 'c');
                @flock($lockFp, LOCK_EX);
            }
        }

        try {
            if (file_exists($cachedThumbPath) && filesize($cachedThumbPath) > 50) {
                return $cachedThumbPath;
            }

            // Batasi alokasi memori sementara untuk manipulasi gambar
            @ini_set('memory_limit', '256M');

            $success = false;

            if ($isArw || ArwService::isArw($originalFileName)) {
                $success = self::generateArwThumbnail($fullPath, $cachedThumbPath, $useWebp);
            } else {
                $success = self::generateImageThumbnail($fullPath, $originalFileName, $cachedThumbPath, $useWebp);
            }

            if ($success && file_exists($cachedThumbPath) && filesize($cachedThumbPath) > 50) {
                return $cachedThumbPath;
            }
        } catch (\Throwable $e) {
            Log::warning("Thumbnail generation failed for {$originalFileName}: " . $e->getMessage());
        } finally {
            if ($lockFp) {
                @flock($lockFp, LOCK_UN);
                @fclose($lockFp);
                @unlink($lockFile);
            }
        }

        return null;
    }

    /**
     * Hasilkan thumbnail ultra-ringan untuk format Sony RAW (.ARW)
     */
    protected static function generateArwThumbnail(string $fullPath, string $targetThumbPath, bool $useWebp): bool
    {
        // 1. Cek apakah konversi JPG resolusi tinggi sudah ada di cache ArwService
        $fileMtime = filemtime($fullPath);
        $fileSize = filesize($fullPath);
        $arwCacheKey = md5($fullPath . '_' . $fileMtime . '_' . $fileSize);
        $cachedArwJpg = storage_path('app/public/cache_arw/' . $arwCacheKey . '.jpg');

        if (file_exists($cachedArwJpg) && filesize($cachedArwJpg) > 10000) {
            return self::downsampleImageFile($cachedArwJpg, $targetThumbPath, $useWebp);
        }

        // 2. Jika berkas terenkripsi, buat berkas temp sementara
        $tempArw = null;
        $scanPath = $fullPath;
        if (FileSecurityService::isEncrypted($fullPath)) {
            $tempArw = FileSecurityService::createDecryptedTempFile($fullPath);
            if ($tempArw) {
                $scanPath = $tempArw;
            }
        }

        $tmpThumbJpg = storage_path('app/temp_dec/arw_thumb_' . uniqid() . '.jpg');

        try {
            // Coba ekstrak cepat EXIF Thumbnail (sangat cepat, ~0.05s) via exiftool
            if (self::isCommandAvailable('exiftool')) {
                $cmd = "exiftool -b -ThumbnailImage " . escapeshellarg($scanPath) . " > " . escapeshellarg($tmpThumbJpg) . " 2>/dev/null";
                @exec($cmd);
                if (file_exists($tmpThumbJpg) && filesize($tmpThumbJpg) > 2000) {
                    return self::downsampleImageFile($tmpThumbJpg, $targetThumbPath, $useWebp);
                }

                $cmd = "exiftool -b -PreviewImage " . escapeshellarg($scanPath) . " > " . escapeshellarg($tmpThumbJpg) . " 2>/dev/null";
                @exec($cmd);
                if (file_exists($tmpThumbJpg) && filesize($tmpThumbJpg) > 10000) {
                    return self::downsampleImageFile($tmpThumbJpg, $targetThumbPath, $useWebp);
                }
            }

            // Jika exiftool tidak tersedia atau gagal, gunakan pipeline umum ArwService
            $jpgPath = ArwService::getConvertedJpgPath($fullPath);
            if ($jpgPath && file_exists($jpgPath)) {
                return self::downsampleImageFile($jpgPath, $targetThumbPath, $useWebp);
            }
        } finally {
            if ($tempArw && file_exists($tempArw)) {
                @unlink($tempArw);
            }
            if (file_exists($tmpThumbJpg)) {
                @unlink($tmpThumbJpg);
            }
        }

        return false;
    }

    /**
     * Hasilkan thumbnail untuk format gambar standar (JPG, PNG, WEBP, GIF, dsb.)
     */
    protected static function generateImageThumbnail(string $fullPath, string $originalFileName, string $targetThumbPath, bool $useWebp): bool
    {
        $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));

        // Format vektor SVG tidak perlu di-resize, langsung sajikan
        if ($ext === 'svg') {
            $data = FileSecurityService::getDecryptedContents($fullPath);
            if ($data) {
                return (bool)@file_put_contents($targetThumbPath, $data);
            }
            return false;
        }

        // Ambil data gambar (terdekripsi jika terenkripsi)
        $data = FileSecurityService::getDecryptedContents($fullPath);
        if (!$data || strlen($data) < 20) {
            return false;
        }

        return self::downsampleImageString($data, $targetThumbPath, $useWebp);
    }

    /**
     * Downsample citra biner dari memori menggunakan ekstensi GD
     */
    public static function downsampleImageString(string $imageData, string $targetPath, bool $useWebp): bool
    {
        if (!function_exists('imagecreatefromstring')) {
            // Jika GD tidak ada sama sekali, simpan data mentah
            return (bool)@file_put_contents($targetPath, $imageData);
        }

        $srcImg = @imagecreatefromstring($imageData);
        if (!$srcImg) {
            return false;
        }

        return self::resizeAndSaveGdImage($srcImg, $targetPath, $useWebp);
    }

    /**
     * Downsample berkas citra fisik dari disk menggunakan ekstensi GD
     */
    public static function downsampleImageFile(string $sourcePath, string $targetPath, bool $useWebp): bool
    {
        if (!file_exists($sourcePath)) return false;

        if (!function_exists('imagecreatefromstring')) {
            return @copy($sourcePath, $targetPath);
        }

        $content = @file_get_contents($sourcePath);
        if (!$content) return false;

        return self::downsampleImageString($content, $targetPath, $useWebp);
    }

    /**
     * Resize resource GD image ke resolusi thumbnail dan simpan ke target path
     */
    protected static function resizeAndSaveGdImage($srcImg, string $targetPath, bool $useWebp): bool
    {
        $origW = imagesx($srcImg);
        $origH = imagesy($srcImg);

        if ($origW <= 0 || $origH <= 0) {
            imagedestroy($srcImg);
            return false;
        }

        // Hitung skala aspect ratio
        $ratio = min(self::THUMB_MAX_WIDTH / $origW, self::THUMB_MAX_HEIGHT / $origH);
        
        // Jika gambar aslinya sudah sangat kecil, tidak perlu diperbesar
        if ($ratio >= 1.0) {
            $newW = $origW;
            $newH = $origH;
        } else {
            $newW = max(1, (int)round($origW * $ratio));
            $newH = max(1, (int)round($origH * $ratio));
        }

        $dstImg = imagecreatetruecolor($newW, $newH);

        // Pengaturan transparansi untuk WebP/PNG
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
        imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $transparent);

        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        $tmpTarget = $targetPath . '.tmp.' . uniqid();

        $saved = false;
        if ($useWebp && function_exists('imagewebp')) {
            $saved = @imagewebp($dstImg, $tmpTarget, self::THUMB_QUALITY);
        } else {
            $saved = @imagejpeg($dstImg, $tmpTarget, self::THUMB_QUALITY);
        }

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        if ($saved && file_exists($tmpTarget) && filesize($tmpTarget) > 50) {
            @rename($tmpTarget, $targetPath);
            return true;
        }

        if (file_exists($tmpTarget)) {
            @unlink($tmpTarget);
        }

        return false;
    }

    /**
     * Cek apakah perintah CLI tersedia di sistem operasi
     */
    protected static function isCommandAvailable(string $cmd): bool
    {
        $where = stripos(PHP_OS, 'WIN') === 0 ? 'where' : 'which';
        $output = [];
        $returnVar = 0;
        @exec("{$where} " . escapeshellarg($cmd), $output, $returnVar);
        return $returnVar === 0 && !empty($output);
    }
}
