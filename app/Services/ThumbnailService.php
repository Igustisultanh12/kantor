<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ThumbnailService
{
    const THUMB_MAX_WIDTH = 320;
    const THUMB_MAX_HEIGHT = 320;
    const THUMB_QUALITY = 75;
    const MAX_CONCURRENT_GENERATIONS = 2;

    // 26-byte valid 1x1 transparent WebP image data
    const TRANSPARENT_WEBP_BASE64 = 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==';

    /**
     * Sajikan HTTP response untuk berkas thumbnail dengan caching peramban maksimal
     */
    public static function getThumbnailResponse(string $fullPath, string $originalFileName, bool $isArw = false)
    {
        if (!file_exists($fullPath)) {
            abort(404, 'Berkas fisik tidak ditemukan.');
        }

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

        // 1. FAST PATH: Jika thumbnail sudah ada dan valid, langsung layani (< 1 ms, 0% CPU, 0% I/O)
        if (file_exists($cachedThumbPath) && filesize($cachedThumbPath) > 50) {
            return self::serveCachedFile($cachedThumbPath);
        }

        // 2. GENERATION SLOT & MUTEX CONTROL: Batasi pembuatan serempak
        $thumbPath = self::getOrCreateThumbnail($fullPath, $originalFileName, $cachedThumbPath, $isArw, $useWebp);

        if ($thumbPath && file_exists($thumbPath) && filesize($thumbPath) > 50) {
            return self::serveCachedFile($thumbPath);
        }

        // 3. Jika sedang dalam antrean pembuatan (server sibuk), kembalikan respons 202 placeholder
        return self::getQueuedResponse();
    }

    /**
     * Layani berkas thumbnail dari cache dengan header HTTP peramban optimal
     */
    protected static function serveCachedFile(string $filePath)
    {
        $isWebp = str_ends_with($filePath, '.webp');
        $mime = $isWebp ? 'image/webp' : 'image/jpeg';
        
        // Gunakan ETag berbasis stat tanpa membaca seluruh byte berkas dari disk
        $etag = '"' . md5($filePath . '_' . @filemtime($filePath) . '_' . @filesize($filePath)) . '"';

        $ifNoneMatch = request()->header('If-None-Match');
        if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
            return response('', 304)->withHeaders([
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'ETag' => $etag,
            ]);
        }

        return response()->file($filePath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Kembalikan respons 202 Accepted (Placeholder ringan) saat thumbnail sedang dibuat
     */
    public static function getQueuedResponse()
    {
        $data = base64_decode(self::TRANSPARENT_WEBP_BASE64);
        return response($data, 202, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Retry-After' => '1',
            'X-Thumbnail-Status' => 'generating',
        ]);
    }

    /**
     * Hasilkan thumbnail dengan pengamanan Stampede Mutex dan Concurrency Semaphore
     */
    public static function getOrCreateThumbnail(string $fullPath, string $originalFileName, string $cachedThumbPath, bool $isArw = false, bool $useWebp = true): ?string
    {
        // Pengecekan awal
        if (file_exists($cachedThumbPath) && filesize($cachedThumbPath) > 50) {
            return $cachedThumbPath;
        }

        $lockDir = storage_path('app/locks');
        if (!file_exists($lockDir)) {
            @mkdir($lockDir, 0777, true);
        }

        // A. Per-File Mutex: Cegah proses ganda membuat berkas yang persis sama
        $fileLockName = 'thumb_file_' . md5($cachedThumbPath) . '.lock';
        $fileLockPath = $lockDir . '/' . $fileLockName;
        $fileLockFp = @fopen($fileLockPath, 'c+');

        if (!$fileLockFp) {
            return null;
        }

        $hasFileLock = flock($fileLockFp, LOCK_EX | LOCK_NB);
        if (!$hasFileLock) {
            // Worker lain sedang membuat thumbnail ini. Tunggu non-blocking hingga 1.5 detik
            $waited = 0;
            while ($waited < 15) {
                usleep(100000); // 100ms
                $waited++;
                if (file_exists($cachedThumbPath) && filesize($cachedThumbPath) > 50) {
                    fclose($fileLockFp);
                    return $cachedThumbPath;
                }
            }
            // Jika masih belum selesai setelah 1.5s, lepaskan dan biarkan klien antre ulang
            fclose($fileLockFp);
            return null;
        }

        // B. Global Concurrency Semaphore: Maksimal 2 worker paralel membuat thumbnail baru
        $slotLockFp = self::acquireGlobalGenerationSlot();
        if (!$slotLockFp) {
            // Server sedang sibuk memproses 2 pembuatan thumbnail lain
            // Tunggu hingga 1 detik untuk mendapatkan slot
            $waited = 0;
            while ($waited < 10) {
                usleep(100000); // 100ms
                $waited++;
                if (file_exists($cachedThumbPath) && filesize($cachedThumbPath) > 50) {
                    flock($fileLockFp, LOCK_UN);
                    fclose($fileLockFp);
                    return $cachedThumbPath;
                }
                $slotLockFp = self::acquireGlobalGenerationSlot();
                if ($slotLockFp) break;
            }

            if (!$slotLockFp) {
                flock($fileLockFp, LOCK_UN);
                fclose($fileLockFp);
                return null;
            }
        }

        try {
            // Cek sekali lagi setelah mendapatkan kunci
            if (file_exists($cachedThumbPath) && filesize($cachedThumbPath) > 50) {
                return $cachedThumbPath;
            }

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
            if ($slotLockFp) {
                flock($slotLockFp, LOCK_UN);
                fclose($slotLockFp);
            }
            if ($fileLockFp) {
                flock($fileLockFp, LOCK_UN);
                fclose($fileLockFp);
            }
        }

        return null;
    }

    /**
     * Dapatkan slot pembuatan thumbnail global (Maksimal 2 pembuatan serempak)
     */
    protected static function acquireGlobalGenerationSlot()
    {
        $lockDir = storage_path('app/locks');
        for ($i = 0; $i < self::MAX_CONCURRENT_GENERATIONS; $i++) {
            $slotPath = $lockDir . '/thumb_slot_' . $i . '.lock';
            $fp = @fopen($slotPath, 'c+');
            if ($fp) {
                if (flock($fp, LOCK_EX | LOCK_NB)) {
                    return $fp;
                }
                fclose($fp);
            }
        }
        return null;
    }

    /**
     * Hasilkan thumbnail ultra-ringan untuk format Sony RAW (.ARW)
     * LANGSUNG DARI ALIRAN MEMORI BINARY TANPA MENULIS BERKAS SEMENTARA 50MB KE DISK!
     */
    protected static function generateArwThumbnail(string $fullPath, string $targetThumbPath, bool $useWebp): bool
    {
        // 1. Cek apakah konversi JPG resolusi tinggi sudah ada di cache ArwService
        $fileMtime = @filemtime($fullPath) ?: 0;
        $fileSize = @filesize($fullPath) ?: 0;
        $arwCacheKey = md5($fullPath . '_' . $fileMtime . '_' . $fileSize);
        $cachedArwJpg = storage_path('app/public/cache_arw/' . $arwCacheKey . '.jpg');

        if (file_exists($cachedArwJpg) && filesize($cachedArwJpg) > 10000) {
            return self::downsampleImageFile($cachedArwJpg, $targetThumbPath, $useWebp);
        }

        // 2. ULTRA-FAST IN-MEMORY EXTRACTION: Dekripsi hanya 1.5MB pertama untuk membaca TIFF IFD1
        // Sony RAW (.ARW) menyimpan thumbnail JPEG terkompresi di IFD1 pada 1.5MB pertama berkas.
        $headBytes = FileSecurityService::getDecryptedContents($fullPath, 1536 * 1024);
        if ($headBytes && strlen($headBytes) > 1000) {
            $embeddedJpeg = self::extractEmbeddedThumbnailBinary($headBytes);
            if ($embeddedJpeg && strlen($embeddedJpeg) > 1000) {
                return self::downsampleImageString($embeddedJpeg, $targetThumbPath, $useWebp);
            }
        }

        // 3. Fallback: Jika exiftool tersedia di sistem
        if (self::isCommandAvailable('exiftool')) {
            $tempArw = null;
            $scanPath = $fullPath;
            if (FileSecurityService::isEncrypted($fullPath)) {
                $tempArw = FileSecurityService::createDecryptedTempFile($fullPath);
                if ($tempArw) {
                    $scanPath = $tempArw;
                }
            }

            $tmpThumbJpg = storage_path('app/locks/arw_thumb_' . uniqid() . '.jpg');
            try {
                $cmd = "exiftool -b -ThumbnailImage " . escapeshellarg($scanPath) . " > " . escapeshellarg($tmpThumbJpg) . " 2>/dev/null";
                @exec($cmd);
                if (file_exists($tmpThumbJpg) && filesize($tmpThumbJpg) > 2000) {
                    return self::downsampleImageFile($tmpThumbJpg, $targetThumbPath, $useWebp);
                }
            } finally {
                if ($tempArw && file_exists($tempArw)) @unlink($tempArw);
                if (file_exists($tmpThumbJpg)) @unlink($tmpThumbJpg);
            }
        }

        // 4. Fallback Terakhir: Pipeline ArwService
        $jpgPath = ArwService::getConvertedJpgPath($fullPath);
        if ($jpgPath && file_exists($jpgPath)) {
            return self::downsampleImageFile($jpgPath, $targetThumbPath, $useWebp);
        }

        return false;
    }

    /**
     * Hasilkan thumbnail untuk format gambar standar (JPG, PNG, WEBP, GIF, dsb.)
     */
    protected static function generateImageThumbnail(string $fullPath, string $originalFileName, string $targetThumbPath, bool $useWebp): bool
    {
        $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));

        // Format vektor SVG langsung disajikan
        if ($ext === 'svg') {
            $data = FileSecurityService::getDecryptedContents($fullPath, 512 * 1024);
            if ($data) {
                return (bool)@file_put_contents($targetThumbPath, $data);
            }
            return false;
        }

        // Untuk format JPEG: Coba ekstrak cepat EXIF APP1 Thumbnail dari 128KB pertama
        if (in_array($ext, ['jpg', 'jpeg'])) {
            $headerChunk = FileSecurityService::getDecryptedContents($fullPath, 131072); // 128 KB
            if ($headerChunk && strlen($headerChunk) > 1000) {
                $embeddedJpeg = self::extractEmbeddedThumbnailBinary($headerChunk);
                if ($embeddedJpeg && strlen($embeddedJpeg) > 1000) {
                    return self::downsampleImageString($embeddedJpeg, $targetThumbPath, $useWebp);
                }
            }
        }

        // Ambil data gambar secukupnya (maksimal 15MB)
        $data = FileSecurityService::getDecryptedContents($fullPath, 15 * 1024 * 1024);
        if (!$data || strlen($data) < 20) {
            return false;
        }

        return self::downsampleImageString($data, $targetThumbPath, $useWebp);
    }

    /**
     * Ekstraksi Cepat Thumbnail JPEG Tertanam dari Data Biner (TIFF/ARW atau JPEG APP1)
     */
    public static function extractEmbeddedThumbnailBinary(string $binaryData): ?string
    {
        $len = strlen($binaryData);
        if ($len < 64) return null;

        // 1. Format JPEG: Ekstrak APP1 EXIF segment
        if (substr($binaryData, 0, 3) === "\xFF\xD8\xFF") {
            $offset = 2;
            while ($offset + 4 < $len) {
                if ($binaryData[$offset] !== "\xFF") break;
                $marker = ord($binaryData[$offset + 1]);
                if ($marker === 0xDA || $marker === 0xD9) break;

                $segLen = (ord($binaryData[$offset + 2]) << 8) | ord($binaryData[$offset + 3]);
                if ($segLen < 2 || $offset + 2 + $segLen > $len) break;

                // APP1 (FF E1)
                if ($marker === 0xE1 && $segLen > 14) {
                    $exifHeader = substr($binaryData, $offset + 4, 6);
                    if ($exifHeader === "Exif\0\0") {
                        $tiffData = substr($binaryData, $offset + 10, $segLen - 8);
                        $thumb = self::extractFromTiffBinary($tiffData);
                        if ($thumb) return $thumb;
                    }
                }
                $offset += 2 + $segLen;
            }
            return null;
        }

        // 2. Format TIFF / Sony ARW
        $magic = substr($binaryData, 0, 2);
        if ($magic === 'II' || $magic === 'MM') {
            return self::extractFromTiffBinary($binaryData);
        }

        return null;
    }

    /**
     * Ekstrak IFD1 thumbnail dari struktur TIFF
     */
    protected static function extractFromTiffBinary(string $data): ?string
    {
        $len = strlen($data);
        if ($len < 16) return null;

        $endian = substr($data, 0, 2);
        if ($endian !== 'II' && $endian !== 'MM') return null;

        $isLE = ($endian === 'II');
        $u16 = $isLE ? 'v' : 'n';
        $u32 = $isLE ? 'V' : 'N';

        $magic = unpack($u16, substr($data, 2, 2))[1];
        if ($magic !== 42) return null;

        $ifd0Offset = unpack($u32, substr($data, 4, 4))[1];
        if ($ifd0Offset + 2 > $len) return null;

        $numEntries0 = unpack($u16, substr($data, $ifd0Offset, 2))[1];
        $nextIfdOffsetPos = $ifd0Offset + 2 + ($numEntries0 * 12);
        if ($nextIfdOffsetPos + 4 > $len) return null;

        $ifd1Offset = unpack($u32, substr($data, $nextIfdOffsetPos, 4))[1];

        if ($ifd1Offset > 0 && $ifd1Offset + 2 <= $len) {
            $thumb = self::parseIfdForJpeg($data, $ifd1Offset, $u16, $u32, $len);
            if ($thumb) return $thumb;
        }

        return self::parseIfdForJpeg($data, $ifd0Offset, $u16, $u32, $len);
    }

    protected static function parseIfdForJpeg(string $data, int $ifdOffset, string $u16, string $u32, int $totalLen): ?string
    {
        if ($ifdOffset + 2 > $totalLen) return null;

        $numEntries = unpack($u16, substr($data, $ifdOffset, 2))[1];
        $thumbOffset = null;
        $thumbLength = null;

        for ($i = 0; $i < $numEntries; $i++) {
            $entryPos = $ifdOffset + 2 + ($i * 12);
            if ($entryPos + 12 > $totalLen) break;

            $tag = unpack($u16, substr($data, $entryPos, 2))[1];
            // 0x0201 = JPEGInterchangeFormat (Offset)
            if ($tag === 0x0201) {
                $thumbOffset = unpack($u32, substr($data, $entryPos + 8, 4))[1];
            }
            // 0x0202 = JPEGInterchangeFormatLength (Length)
            elseif ($tag === 0x0202) {
                $thumbLength = unpack($u32, substr($data, $entryPos + 8, 4))[1];
            }
        }

        if ($thumbOffset !== null && $thumbLength !== null && $thumbLength > 500) {
            if ($thumbOffset + $thumbLength <= $totalLen) {
                $candidate = substr($data, $thumbOffset, $thumbLength);
                if (substr($candidate, 0, 3) === "\xFF\xD8\xFF") {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Downsample citra biner dari memori menggunakan ekstensi GD
     */
    public static function downsampleImageString(string $imageData, string $targetPath, bool $useWebp): bool
    {
        if (!function_exists('imagecreatefromstring')) {
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
        
        if ($ratio >= 1.0) {
            $newW = $origW;
            $newH = $origH;
        } else {
            $newW = max(1, (int)round($origW * $ratio));
            $newH = max(1, (int)round($origH * $ratio));
        }

        $dstImg = imagecreatetruecolor($newW, $newH);

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

    protected static function isCommandAvailable(string $cmd): bool
    {
        $where = stripos(PHP_OS, 'WIN') === 0 ? 'where' : 'which';
        $output = [];
        $returnVar = 0;
        @exec("{$where} " . escapeshellarg($cmd), $output, $returnVar);
        return $returnVar === 0 && !empty($output);
    }
}
