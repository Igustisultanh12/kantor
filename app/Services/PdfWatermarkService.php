<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PdfWatermarkService
{
    /**
     * Menerapkan stempel watermark resmi PETINJAU (diagonal berbingkai merah)
     * langsung menempel ke dalam binary lembar naskah PDF di setiap halamannya.
     *
     * @param string $relativePdfPath Jalur relatif berkas pada disk public (misal: 'sc_documents/xyz.pdf')
     * @return string|null Jalur berkas ber-watermark (misal: 'sc_documents/wm_xyz.pdf')
     */
    public static function applyWatermark(string $relativePdfPath): ?string
    {
        if (!Storage::disk('public')->exists($relativePdfPath)) {
            return null;
        }

        // Hindari memproses ulang berkas yang sudah ber-watermark
        if (str_starts_with(basename($relativePdfPath), 'wm_')) {
            return $relativePdfPath;
        }

        $sourceFullPath = Storage::disk('public')->path($relativePdfPath);
        $fileName = basename($relativePdfPath);
        $targetRelativePath = 'sc_documents/wm_' . $fileName;
        $targetFullPath = Storage::disk('public')->path($targetRelativePath);

        // Jika sudah pernah diproses sebelumnya dan berkas watermark masih ada
        if (Storage::disk('public')->exists($targetRelativePath)) {
            return $targetRelativePath;
        }

        $watermarkImg = public_path('images/watermark_petinjau.png');
        if (!file_exists($watermarkImg)) {
            Log::warning("Asset watermark PETINJAU tidak ditemukan di: {$watermarkImg}");
            return $relativePdfPath;
        }

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($sourceFullPath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplIdx = $pdf->importPage($pageNo);
                $size = $pdf->getImportedPageSize($tplIdx);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplIdx);

                $pageW = $size['width'];
                $pageH = $size['height'];

                // Ukuran proporsional watermark diagonal di tengah lembar naskah (72% lebar kertas)
                $wmW = $pageW * 0.72;
                $wmH = $wmW * (1497 / 1146);

                if ($wmH > $pageH * 0.88) {
                    $wmH = $pageH * 0.88;
                    $wmW = $wmH * (1146 / 1497);
                }

                $wmX = ($pageW - $wmW) / 2;
                $wmY = ($pageH - $wmH) / 2;

                // Terapkan watermark PNG transparan beresolusi tinggi langsung ke halaman PDF
                $pdf->Image($watermarkImg, $wmX, $wmY, $wmW, $wmH);
            }

            // Pastikan folder target ada
            $targetDir = dirname($targetFullPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0775, true);
            }

            $pdf->Output($targetFullPath, 'F');
            return $targetRelativePath;
        } catch (\Throwable $e) {
            Log::error("Gagal menerapkan watermark fisik PETINJAU ke PDF: " . $e->getMessage());
            return $relativePdfPath;
        }
    }
}
