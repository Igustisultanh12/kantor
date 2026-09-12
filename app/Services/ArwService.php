<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\Pc;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ArwService
{
    /**
     * Memeriksa apakah suatu berkas atau ekstensi adalah Sony RAW (.ARW)
     */
    public static function isArw($filenameOrExt): bool
    {
        if (!$filenameOrExt) return false;
        $ext = strtolower(pathinfo($filenameOrExt, PATHINFO_EXTENSION) ?: $filenameOrExt);
        return $ext === 'arw';
    }

    /**
     * Mengambil path berkas JPG hasil konversi kualitas tinggi dari berkas .ARW
     * Menggunakan caching cerdas agar tidak perlu konversi ulang jika berkas belum berubah.
     */
    public static function getConvertedJpgPath(string $fullArwPath): ?string
    {
        if (!file_exists($fullArwPath) || !is_readable($fullArwPath)) {
            Log::warning("ARW file not found: {$fullArwPath}");
            return null;
        }

        $cacheDir = storage_path('app/public/cache_arw');
        if (!file_exists($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $fileMtime = filemtime($fullArwPath);
        $fileSize = filesize($fullArwPath);
        $cacheKey = md5($fullArwPath . '_' . $fileMtime . '_' . $fileSize);
        $cachedJpgPath = $cacheDir . '/' . $cacheKey . '.jpg';

        // Jika cache sudah ada dan valid, langsung kembalikan
        if (file_exists($cachedJpgPath) && filesize($cachedJpgPath) > 10000) {
            return $cachedJpgPath;
        }

        // Jalankan pipeline konversi multi-tier
        $success = self::executeConversionPipeline($fullArwPath, $cachedJpgPath);

        if ($success && file_exists($cachedJpgPath) && filesize($cachedJpgPath) > 10000) {
            return $cachedJpgPath;
        }

        return null;
    }

    /**
     * Memberikan response streaming preview JPG untuk browser
     */
    public static function previewResponse(string $fullArwPath, string $originalFileName)
    {
        $jpgPath = self::getConvertedJpgPath($fullArwPath);

        if (!$jpgPath || !file_exists($jpgPath)) {
            abort(500, 'Gagal memproses pratinjau format gambar Sony RAW (.ARW).');
        }

        return response()->file($jpgPath, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400, must-revalidate',
            'Content-Disposition' => 'inline; filename="' . pathinfo($originalFileName, PATHINFO_FILENAME) . '.jpg"',
        ]);
    }

    /**
     * Mengonversi .ARW dan menyimpannya sebagai berkas .JPG baru di pangkalan storage yang sama
     */
    public static function convertAndSaveToBackup(Backup $backupItem): ?Backup
    {
        $pc = Pc::findOrFail($backupItem->pc_id);
        $sourceFullPath = storage_path('app/public/' . $backupItem->file_path);

        $convertedJpgPath = self::getConvertedJpgPath($sourceFullPath);
        if (!$convertedJpgPath || !file_exists($convertedJpgPath)) {
            return null;
        }

        $newFileSize = filesize($convertedJpgPath);

        // Periksa kuota pangkalan
        if (($pc->current_usage + $newFileSize) > $pc->max_quota) {
            throw new \Exception('Kapasitas penyimpanan tidak mencukupi untuk menyimpan hasil konversi JPG.');
        }

        $baseName = pathinfo($backupItem->file_name, PATHINFO_FILENAME);
        $newOriginalName = $baseName . '.jpg';

        // Cegah duplikasi nama dengan menambahkan suffix angka jika nama sudah ada di folder
        $existingCount = Backup::where('pc_id', $pc->id)
            ->where('parent_id', $backupItem->parent_id)
            ->where('file_name', 'like', $baseName . '%.jpg')
            ->count();

        if ($existingCount > 0) {
            $newOriginalName = $baseName . '_' . ($existingCount + 1) . '.jpg';
        }

        $uniquePrefix = time() . '_' . substr(uniqid(), -6);
        $newSubPath = 'backups/' . $pc->id . '/' . $uniquePrefix . '_' . $newOriginalName;
        $destFullPath = storage_path('app/public/' . $newSubPath);

        $destDir = dirname($destFullPath);
        if (!file_exists($destDir)) {
            @mkdir($destDir, 0777, true);
        }

        if (!copy($convertedJpgPath, $destFullPath)) {
            return null;
        }

        $newBackup = Backup::create([
            'pc_id' => $pc->id,
            'parent_id' => $backupItem->parent_id,
            'file_name' => $newOriginalName,
            'file_path' => $newSubPath,
            'file_size' => $newFileSize,
            'is_folder' => false,
            'file_type' => 'jpg',
        ]);

        $pc->increment('current_usage', $newFileSize);

        return $newBackup;
    }

    /**
     * Download langsung berkas .ARW sebagai format .JPG dengan kualitas tinggi
     */
    public static function downloadConvertedJpg(Backup $backupItem)
    {
        $sourceFullPath = storage_path('app/public/' . $backupItem->file_path);
        $convertedJpgPath = self::getConvertedJpgPath($sourceFullPath);

        if (!$convertedJpgPath || !file_exists($convertedJpgPath)) {
            abort(500, 'Gagal mengonversi berkas Sony RAW (.ARW) ke format JPG.');
        }

        $downloadName = pathinfo($backupItem->file_name, PATHINFO_FILENAME) . '.jpg';

        return response()->download($convertedJpgPath, $downloadName, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'no-cache, must-revalidate'
        ]);
    }

    /**
     * PIPELINE KONVERSI MULTI-TIER
     * 1. ExifTool (Ekstrak preview kamera Sony BIONZ full quality)
     * 2. dcraw (RAW decapsulator teruji)
     * 3. ImageMagick CLI (magick / convert dengan -quality 95)
     * 4. PHP Imagick Extension
     * 5. Pure PHP Native Binary Scanner (mengekstrak JPEG resolusi tinggi tertanam tanpa alat eksternal)
     */
    protected static function executeConversionPipeline(string $arwPath, string $targetJpgPath): bool
    {
        // 1. ExifTool (Kualitas terbaik & warna asli profil kamera Sony)
        if (self::isCommandAvailable('exiftool')) {
            $cmd = "exiftool -b -PreviewImage " . escapeshellarg($arwPath) . " > " . escapeshellarg($targetJpgPath) . " 2>&1";
            @exec($cmd);
            if (self::isValidJpg($targetJpgPath)) {
                return true;
            }

            $cmd = "exiftool -b -JpgFromRaw " . escapeshellarg($arwPath) . " > " . escapeshellarg($targetJpgPath) . " 2>&1";
            @exec($cmd);
            if (self::isValidJpg($targetJpgPath)) {
                return true;
            }
        }

        // 2. dcraw (Ekstraksi thumbnail / preview tertanam dari sensor Sony)
        if (self::isCommandAvailable('dcraw')) {
            $cmd = "dcraw -e " . escapeshellarg($arwPath) . " 2>&1";
            @exec($cmd);
            $expectedThumb = preg_replace('/\.arw$/i', '.thumb.jpg', $arwPath);
            if (file_exists($expectedThumb) && filesize($expectedThumb) > 50000) {
                rename($expectedThumb, $targetJpgPath);
                return true;
            }
        }

        // 3. ImageMagick CLI (magick atau convert)
        $magickCmd = self::isCommandAvailable('magick') ? 'magick' : (self::isCommandAvailable('convert') ? 'convert' : null);
        if ($magickCmd && !self::isWindowsSystemConvert($magickCmd)) {
            $cmd = "{$magickCmd} " . escapeshellarg($arwPath) . " -quality 95 " . escapeshellarg($targetJpgPath) . " 2>&1";
            @exec($cmd);
            if (self::isValidJpg($targetJpgPath)) {
                return true;
            }
        }

        // 4. Ekstensi PHP Imagick
        if (extension_loaded('imagick') && class_exists('\Imagick')) {
            try {
                $im = new \Imagick();
                $im->readImage($arwPath);
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(95);
                $im->writeImage($targetJpgPath);
                $im->clear();
                $im->destroy();
                if (self::isValidJpg($targetJpgPath)) {
                    return true;
                }
            } catch (\Throwable $e) {
                Log::info("Imagick ARW conversion skipped: " . $e->getMessage());
            }
        }

        // 5. Fallback Murni Native PHP: Ekstraksi stream JPEG resolusi tinggi di dalam TIFF ARW
        $extractedData = self::extractEmbeddedJpegNative($arwPath);
        if ($extractedData && strlen($extractedData) > 50000) {
            file_put_contents($targetJpgPath, $extractedData);
            if (self::isValidJpg($targetJpgPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ekstraktor JPEG Embedded Native PHP
     * Menemukan blok JPEG HD terbesar yang disimpan kamera Sony di dalam file RAW
     */
    protected static function extractEmbeddedJpegNative(string $filePath): ?string
    {
        $fp = @fopen($filePath, 'rb');
        if (!$fp) return null;

        $fileSize = @filesize($filePath);
        if (!$fileSize || $fileSize < 10000) {
            fclose($fp);
            return null;
        }

        $header = fread($fp, 4);
        if (substr($header, 0, 2) !== 'II' && substr($header, 0, 2) !== 'MM') {
            fclose($fp);
            return null;
        }

        rewind($fp);

        $bufferSize = 2 * 1024 * 1024; // 2 MB per chunk
        $offset = 0;
        $candidates = [];

        $data = fread($fp, $bufferSize);
        $dataLen = strlen($data);

        while ($dataLen > 0) {
            $pos = 0;
            while (($pos = strpos($data, "\xFF\xD8\xFF", $pos)) !== false) {
                $absoluteOffset = $offset + $pos;
                $candidates[] = $absoluteOffset;
                $pos += 3;
            }

            $overlap = 32;
            if ($offset + $bufferSize >= $fileSize) {
                break;
            }

            $offset += ($bufferSize - $overlap);
            fseek($fp, $offset);
            $data = fread($fp, $bufferSize);
            $dataLen = strlen($data);
        }

        if (empty($candidates)) {
            fclose($fp);
            return null;
        }

        $bestJpeg = null;
        $bestSize = 0;

        foreach ($candidates as $candOffset) {
            fseek($fp, $candOffset);
            // Baca hingga 25MB untuk preview kualitas tinggi
            $candData = fread($fp, min(25 * 1024 * 1024, $fileSize - $candOffset));

            $eoiPos = strrpos($candData, "\xFF\xD9");
            if ($eoiPos !== false) {
                $jpegLen = $eoiPos + 2;
                if ($jpegLen > $bestSize && $jpegLen > 50000) {
                    $jpegCandidate = substr($candData, 0, $jpegLen);
                    $bestSize = $jpegLen;
                    $bestJpeg = $jpegCandidate;
                }
            }
        }

        fclose($fp);
        return $bestJpeg;
    }

    protected static function isCommandAvailable(string $cmd): bool
    {
        $where = stripos(PHP_OS, 'WIN') === 0 ? 'where' : 'which';
        $output = [];
        $returnVar = 0;
        @exec("{$where} " . escapeshellarg($cmd), $output, $returnVar);
        return $returnVar === 0 && !empty($output);
    }

    protected static function isWindowsSystemConvert(string $cmd): bool
    {
        if (stripos(PHP_OS, 'WIN') === 0 && strtolower($cmd) === 'convert') {
            $out = [];
            @exec("where convert", $out);
            foreach ($out as $line) {
                if (stripos($line, 'system32\convert.exe') !== false) return true;
            }
        }
        return false;
    }

    protected static function isValidJpg(string $filePath): bool
    {
        if (!file_exists($filePath) || filesize($filePath) < 10000) {
            return false;
        }
        $h = @file_get_contents($filePath, false, null, 0, 3);
        return $h === "\xFF\xD8\xFF";
    }
}
