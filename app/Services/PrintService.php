<?php

namespace App\Services;

use App\Models\PrintJob;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

class PrintService
{
    /**
     * Konversi berkas DOCX / DOC ke PDF menggunakan LibreOffice headless
     */
    public function convertToPdf(string $sourcePath, string $fileType): string
    {
        if (strtolower($fileType) === 'pdf') {
            return $sourcePath;
        }

        $tempOutDir = storage_path('app/print_jobs/converted_' . uniqid());
        if (!file_exists($tempOutDir)) {
            @mkdir($tempOutDir, 0777, true);
        }

        $binary = 'libreoffice';
        $userProfile = '-env:UserInstallation=file:///tmp/libo_print_' . uniqid();
        $prefix = 'export HOME=/tmp && ';

        if (PHP_OS_FAMILY === 'Windows') {
            $prefix = '';
            $userProfile = '';
            $winPaths = [
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            ];
            $binary = 'soffice';
            foreach ($winPaths as $wp) {
                if (file_exists($wp)) {
                    $binary = '"' . $wp . '"';
                    break;
                }
            }
        }

        $command = "{$prefix}{$binary} --headless --invisible --nologo --nodefault --nofirststartwizard {$userProfile} --convert-to pdf --outdir " . escapeshellarg($tempOutDir) . " " . escapeshellarg($sourcePath) . " 2>&1";
        @exec($command, $out, $ret);

        $generatedPdfs = glob($tempOutDir . '/*.pdf');
        if (empty($generatedPdfs) && PHP_OS_FAMILY !== 'Windows') {
            $commandSoffice = "export HOME=/tmp && soffice --headless --invisible --nologo --nodefault --nofirststartwizard {$userProfile} --convert-to pdf --outdir " . escapeshellarg($tempOutDir) . " " . escapeshellarg($sourcePath) . " 2>&1";
            @exec($commandSoffice, $outSoffice, $retSoffice);
            $generatedPdfs = glob($tempOutDir . '/*.pdf');
        }

        if (empty($generatedPdfs)) {
            Log::error('Gagal konversi dokumen Word ke PDF via LibreOffice', [
                'source' => $sourcePath,
                'output' => $out ?? [],
                'return_code' => $ret ?? null
            ]);
            throw new \Exception('Gagal mengonversi berkas dokumen Word ke PDF. Pastikan format berkas valid.');
        }

        return $generatedPdfs[0];
    }

    /**
     * Hitung total halaman dokumen asli dan siapkan PDF siap cetak
     * dengan menyisipkan 1 lembar kosong di akhir sebagai pemisah otomatis
     */
    public function preparePrintablePdf(string $sourcePdfPath): array
    {
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($sourcePdfPath);

        // Salin seluruh halaman dokumen asli
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
        }

        // Sisipkan 1 lembar kosong di akhir dokumen sebagai pemisah
        $pdf->AddPage();

        $printableDir = storage_path('app/print_jobs/printable');
        if (!file_exists($printableDir)) {
            @mkdir($printableDir, 0777, true);
        }

        $printablePath = $printableDir . '/printable_' . uniqid() . '.pdf';
        $pdf->Output($printablePath, 'F');

        return [
            'total_pages' => $pageCount,
            'separator_pages' => 1,
            'total_sheets' => $pageCount + 1,
            'printable_pdf_path' => $printablePath,
        ];
    }

    /**
     * Tes koneksi soket jaringan ke Printer Brother
     */
    public function testPrinterConnection(?string $ip = null, ?int $port = null): array
    {
        $printerIp = $ip ?: Setting::where('key', 'printer_brother_ip')->value('value');
        $printerPort = $port ?: (int)(Setting::where('key', 'printer_brother_port')->value('value') ?: 9100);

        if (!$printerIp) {
            return [
                'success' => false,
                'message' => 'Alamat IP Printer belum diatur di Pengaturan Sistem.'
            ];
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($printerIp, $printerPort, $errno, $errstr, 3);

        if ($socket) {
            fclose($socket);
            return [
                'success' => true,
                'message' => "Printer Brother pada {$printerIp}:{$printerPort} TERHUBUNG (ONLINE)."
            ];
        }

        return [
            'success' => false,
            'message' => "Tidak dapat terhubung ke Printer Brother pada {$printerIp}:{$printerPort} (OFFLINE). Error ({$errno}): {$errstr}"
        ];
    }

    /**
     * Kirim data dokumen ke Printer Brother via TCP RAW Port 9100
     * dengan kontrol PJL hitam putih (Monochrome)
     */
    public function sendToBrotherPrinter(PrintJob $job): bool
    {
        $printerIp = Setting::where('key', 'printer_brother_ip')->value('value') ?: '192.168.1.200';
        $printerPort = (int)(Setting::where('key', 'printer_brother_port')->value('value') ?: 9100);

        $filePath = $job->printable_pdf_path ?: $job->preview_pdf_path;
        if (!file_exists($filePath)) {
            throw new \Exception("Berkas siap cetak tidak ditemukan di server: {$filePath}");
        }

        $fileContent = file_get_contents($filePath);
        $totalBytes = strlen($fileContent);

        // Header & Footer PJL Brother (Memaksa cetak Hitam Putih / Monochrome)
        $cleanDocTitle = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $job->document_title ?: 'SINDEN_DOC');
        $pjlHeader = "\x1B%-12345X@PJL\r\n"
            . "@PJL JOB NAME = \"SINDEN_{$job->id}_{$cleanDocTitle}\"\r\n"
            . "@PJL SET COLORMODE = MONO\r\n"
            . "@PJL SET RENDERMODE = GRAYSCALE\r\n"
            . "@PJL ENTER LANGUAGE = PDF\r\n";
        $pjlFooter = "\r\n\x1B%-12345X@PJL EOJ\r\n\x1B%-12345X\r\n";

        $dataStream = $pjlHeader . $fileContent . $pjlFooter;
        $streamLength = strlen($dataStream);

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($printerIp, $printerPort, $errno, $errstr, 10);

        if (!$socket) {
            throw new \Exception("Gagal membuka koneksi ke Printer Brother pada {$printerIp}:{$printerPort}. ({$errno}) {$errstr}");
        }

        // Kirim data secara bertahap dan catat estimasi lembar tercetak
        $chunkSize = 65536; // 64KB per chunk
        $bytesSent = 0;
        $totalSheets = max(1, $job->total_sheets);

        while ($bytesSent < $streamLength) {
            $chunk = substr($dataStream, $bytesSent, $chunkSize);
            $written = @fwrite($socket, $chunk);
            if ($written === false || $written === 0) {
                fclose($socket);
                throw new \Exception("Koneksi ke Printer Brother terputus saat transmisi data pada offset {$bytesSent}.");
            }
            $bytesSent += $written;

            // Estimasi kemajuan lembar
            $progressRatio = min(1, $bytesSent / $streamLength);
            $currentSheet = min($totalSheets, (int)ceil($progressRatio * $totalSheets));
            if ($currentSheet > $job->printed_sheets) {
                $job->update(['printed_sheets' => $currentSheet]);
            }
            usleep(15000); // Penjeda 15ms agar transmisi stabil
        }

        // Pastikan soket di-flush dan ditutup rapi
        @fflush($socket);
        fclose($socket);

        // Tandai seluruh lembar telah terkirim
        $job->update([
            'printed_sheets' => $totalSheets,
            'status' => 'completed',
            'completed_at' => now(),
            'error_message' => null
        ]);

        return true;
    }

    /**
     * Memproses antrean cetak berikutnya dengan Atomic Lock untuk mencegah tabrakan cetak
     */
    public function processQueue(): void
    {
        $lock = Cache::lock('brother_print_queue_lock', 120);

        if (!$lock->get()) {
            // Sedang ada proses lain yang mengeksekusi antrean cetak
            return;
        }

        try {
            // Periksa apakah ada dokumen yang saat ini berstatus 'printing'
            $activePrinting = PrintJob::where('status', 'printing')->first();
            if ($activePrinting) {
                // Dokumen masih sedang dicetak di printer
                return;
            }

            // Ambil berkas antrean pertama yang berstatus 'queued'
            $nextJob = PrintJob::where('status', 'queued')
                ->orderBy('created_at', 'asc')
                ->first();

            if (!$nextJob) {
                // Tidak ada antrean menunggu
                return;
            }

            // Mulai eksekusi cetak
            $nextJob->update([
                'status' => 'printing',
                'started_at' => now(),
                'printed_sheets' => 0
            ]);

            try {
                $this->sendToBrotherPrinter($nextJob);
            } catch (\Exception $e) {
                Log::error('Error saat mencetak dokumen pada printer Brother', [
                    'job_id' => $nextJob->id,
                    'error' => $e->getMessage()
                ]);

                $nextJob->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now()
                ]);
            }

        } finally {
            $lock->release();
        }

        // Jika masih ada antrean menunggu berikutnya, proses secara rekursif
        $remainingQueued = PrintJob::where('status', 'queued')->exists();
        if ($remainingQueued) {
            $this->processQueue();
        }
    }
}
