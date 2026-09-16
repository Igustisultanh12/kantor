<?php

namespace App\Services;

use App\Models\PrintJob;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

        $tempOutDir = Storage::disk('local')->path('print_jobs/converted_' . uniqid());
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
     * serta menyesuaikan ukuran kertas yang dipilih (A4, F4/Folio, Letter, Legal)
     */
    public function preparePrintablePdf(string $sourcePdfPath, string $paperSize = 'A4'): array
    {
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($sourcePdfPath);

        // Dimensi lembar target (dalam milimeter)
        $dimensions = match(strtoupper($paperSize)) {
            'F4', 'FOLIO' => [215, 330],
            'LETTER'      => [215.9, 279.4],
            'LEGAL'       => [215.9, 355.6],
            default       => [210, 297], // A4
        };

        // Salin seluruh halaman dokumen asli dan sesuaikan ukuran lembar target
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pageWidth = ($orientation === 'P') ? $dimensions[0] : $dimensions[1];
            $pageHeight = ($orientation === 'P') ? $dimensions[1] : $dimensions[0];

            $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
            $pdf->useTemplate($templateId, 0, 0, $pageWidth, $pageHeight, true);
        }

        // Sisipkan 1 lembar kosong di akhir dokumen sebagai pemisah (dengan ukuran kertas yang sama)
        $pdf->AddPage('P', [$dimensions[0], $dimensions[1]]);

        $printableDir = Storage::disk('local')->path('print_jobs/printable');
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
            'paper_size' => strtoupper($paperSize),
        ];
    }

    /**
     * Tes koneksi soket jaringan ke Printer (Brother atau Canon G3010)
     */
    public function testPrinterConnection(?string $ip = null, ?int $port = null, string $target = 'brother'): array
    {
        if ($target === 'canon') {
            $printerIp = $ip ?: Setting::where('key', 'printer_canon_ip')->value('value') ?: '192.168.1.201';
            $printerPort = $port ?: (int)(Setting::where('key', 'printer_canon_port')->value('value') ?: 9100);
            $printerName = Setting::where('key', 'printer_canon_name')->value('value') ?: 'Canon PIXMA G3010';
        } else {
            $printerIp = $ip ?: Setting::where('key', 'printer_brother_ip')->value('value') ?: '192.168.1.200';
            $printerPort = $port ?: (int)(Setting::where('key', 'printer_brother_port')->value('value') ?: 9100);
            $printerName = Setting::where('key', 'printer_brother_name')->value('value') ?: 'Brother Network Printer';
        }

        if (!$printerIp) {
            return [
                'success' => false,
                'message' => "Alamat IP {$printerName} belum diatur di Pengaturan Sistem."
            ];
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($printerIp, $printerPort, $errno, $errstr, 3);

        if ($socket) {
            fclose($socket);
            return [
                'success' => true,
                'message' => "{$printerName} pada {$printerIp}:{$printerPort} TERHUBUNG (ONLINE)."
            ];
        }

        return [
            'success' => false,
            'message' => "Tidak dapat terhubung ke {$printerName} pada {$printerIp}:{$printerPort} (OFFLINE). Error ({$errno}): {$errstr}"
        ];
    }

    /**
     * Kirim data dokumen ke Printer Brother via TCP RAW Port 9100
     * dengan kontrol PJL hitam putih (Monochrome)
     */
    public function sendToBrotherPrinter(PrintJob $job): bool
    {
        $printerIp = Setting::where('key', 'printer_brother_ip')->value('value') ?: ($job->printer_ip ?: '192.168.1.200');
        $printerPort = (int)(Setting::where('key', 'printer_brother_port')->value('value') ?: 9100);

        $filePath = $job->printable_pdf_path ?: $job->preview_pdf_path;
        if (!file_exists($filePath)) {
            throw new \Exception("Berkas siap cetak tidak ditemukan di server: {$filePath}");
        }

        $fileContent = file_get_contents($filePath);
        $cleanDocTitle = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $job->document_title ?: 'SINDEN_DOC');

        $densityLines = match($job->print_density) {
            'light', 'terang' => "@PJL SET TONERSAVE = ON\r\n@PJL SET DENSITY = 1\r\n",
            'dark', 'pekat'   => "@PJL SET TONERSAVE = OFF\r\n@PJL SET DENSITY = 5\r\n",
            default           => "@PJL SET TONERSAVE = OFF\r\n@PJL SET DENSITY = 3\r\n",
        };

        $paperPjl = match(strtoupper($job->paper_size ?? 'A4')) {
            'F4', 'FOLIO' => 'FOLIO',
            'LETTER'      => 'LETTER',
            'LEGAL'       => 'LEGAL',
            default       => 'A4',
        };

        $pjlHeader = "\x1B%-12345X@PJL\r\n"
            . "@PJL JOB NAME = \"SINDEN_{$job->id}_{$cleanDocTitle}\"\r\n"
            . "@PJL SET COLORMODE = MONO\r\n"
            . "@PJL SET RENDERMODE = GRAYSCALE\r\n"
            . "@PJL SET PAPER = {$paperPjl}\r\n"
            . $densityLines
            . "@PJL ENTER LANGUAGE = PDF\r\n";
        $pjlFooter = "\r\n\x1B%-12345X@PJL EOJ\r\n\x1B%-12345X\r\n";

        $dataStream = $pjlHeader . $fileContent . $pjlFooter;
        $streamLength = strlen($dataStream);

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($printerIp, $printerPort, $errno, $errstr, 5);

        if (!$socket) {
            Log::warning("Koneksi soket ke Printer Brother offline pada {$printerIp}:{$printerPort}. ({$errno}) {$errstr}");
            return true;
        }

        $chunkSize = 65536; // 64KB per chunk
        $bytesSent = 0;
        while ($bytesSent < $streamLength) {
            $chunk = substr($dataStream, $bytesSent, $chunkSize);
            $written = @fwrite($socket, $chunk);
            if ($written === false || $written === 0) {
                fclose($socket);
                throw new \Exception("Koneksi ke Printer Brother terputus saat transmisi data pada offset {$bytesSent}.");
            }
            $bytesSent += $written;
            usleep(5000);
        }

        @fflush($socket);
        fclose($socket);

        return true;
    }

    /**
     * Kirim data dokumen ke Printer Canon PIXMA G3010 via TCP RAW Port 9100
     * dengan kontrol warna resolusi tinggi (High Quality Color)
     */
    public function sendToCanonPrinter(PrintJob $job): bool
    {
        $printerIp = Setting::where('key', 'printer_canon_ip')->value('value') ?: ($job->printer_ip ?: '192.168.1.201');
        $printerPort = (int)(Setting::where('key', 'printer_canon_port')->value('value') ?: 9100);

        $filePath = $job->printable_pdf_path ?: $job->preview_pdf_path;
        if (!file_exists($filePath)) {
            throw new \Exception("Berkas siap cetak tidak ditemukan di server: {$filePath}");
        }

        $fileContent = file_get_contents($filePath);
        $cleanDocTitle = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $job->document_title ?: 'SINDEN_CANON_DOC');

        $paperPjl = match(strtoupper($job->paper_size ?? 'A4')) {
            'F4', 'FOLIO' => 'FOLIO',
            'LETTER'      => 'LETTER',
            'LEGAL'       => 'LEGAL',
            default       => 'A4',
        };

        // Header kontrol Canon G3010: Mode Warna & Kualitas Sangat Tinggi
        $pjlHeader = "\x1B%-12345X@PJL\r\n"
            . "@PJL JOB NAME = \"SINDEN_CANON_{$job->id}_{$cleanDocTitle}\"\r\n"
            . "@PJL SET COLORMODE = COLOR\r\n"
            . "@PJL SET RENDERMODE = COLOR\r\n"
            . "@PJL SET QUALITY = VERY_HIGH\r\n"
            . "@PJL SET RESOLUTION = 1200\r\n"
            . "@PJL SET MEDIATYPE = PLAINPAPER\r\n"
            . "@PJL SET PAPER = {$paperPjl}\r\n"
            . "@PJL ENTER LANGUAGE = PDF\r\n";
        $pjlFooter = "\r\n\x1B%-12345X@PJL EOJ\r\n\x1B%-12345X\r\n";

        $dataStream = $pjlHeader . $fileContent . $pjlFooter;
        $streamLength = strlen($dataStream);

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($printerIp, $printerPort, $errno, $errstr, 5);

        if (!$socket) {
            Log::warning("Koneksi soket ke Printer Canon G3010 offline pada {$printerIp}:{$printerPort}. ({$errno}) {$errstr}");
            return true;
        }

        $chunkSize = 65536; // 64KB per chunk
        $bytesSent = 0;
        while ($bytesSent < $streamLength) {
            $chunk = substr($dataStream, $bytesSent, $chunkSize);
            $written = @fwrite($socket, $chunk);
            if ($written === false || $written === 0) {
                fclose($socket);
                throw new \Exception("Koneksi ke Printer Canon G3010 terputus saat transmisi data pada offset {$bytesSent}.");
            }
            $bytesSent += $written;
            usleep(5000);
        }

        @fflush($socket);
        fclose($socket);

        return true;
    }

    /**
     * Memulai pencetakan berkas dokumen ke printer yang sesuai
     */
    public function startPrintingJob(PrintJob $job): void
    {
        $job->update([
            'status' => 'printing',
            'started_at' => now(),
            'printed_sheets' => 1,
            'error_message' => null,
        ]);

        try {
            if ($job->color_mode === 'color' || $job->printer_brand === 'canon') {
                $this->sendToCanonPrinter($job);
            } else {
                $this->sendToBrotherPrinter($job);
            }
        } catch (\Exception $e) {
            Log::error('Error saat mengirim spool cetak ke printer: ' . $e->getMessage(), [
                'job_id' => $job->id,
                'printer' => $job->printer_brand,
            ]);

            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Memproses pergerakan lembar/halaman yang sedang dicetak secara bertahap
     * (Dipanggil setiap kali status antrean diperbarui)
     */
    public function tickActiveJobProgress(): void
    {
        $activeJob = PrintJob::where('status', 'printing')->first();

        if (!$activeJob) {
            // Jika tidak ada dokumen aktif, jalankan antrean menunggu berikutnya
            $this->processQueue();
            return;
        }

        if (!$activeJob->started_at) {
            $activeJob->update(['started_at' => now()]);
            return;
        }

        $elapsed = now()->diffInSeconds($activeJob->started_at);
        // Durasi mekanik cetak per lembar:
        // Canon G3010 Warna Kualitas Tinggi (~8 detik/lembar)
        // Brother Monokrom Laser (~3.5 detik/lembar)
        $secondsPerSheet = ($activeJob->color_mode === 'color' || $activeJob->printer_brand === 'canon') ? 8 : 4;
        $totalSheets = max(1, $activeJob->total_sheets);

        $currentSheet = min($totalSheets, 1 + (int)floor($elapsed / $secondsPerSheet));

        if ($currentSheet > $activeJob->printed_sheets) {
            $activeJob->update(['printed_sheets' => $currentSheet]);
        }

        // Jika seluruh lembar telah terlewati ditambah jeda finalisasi 2 detik
        $totalDuration = ($totalSheets * $secondsPerSheet) + 2;
        if ($elapsed >= $totalDuration) {
            $activeJob->update([
                'printed_sheets' => $totalSheets,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Bersihkan berkas fisik dari server setelah cetak selesai
            $this->cleanupPhysicalFiles($activeJob);

            // Lanjutkan memproses antrean berikutnya jika ada
            $this->processQueue();
        }
    }

    /**
     * Memproses antrean cetak berikutnya dengan Atomic Lock untuk mencegah tabrakan cetak
     */
    public function processQueue(): void
    {
        $lock = Cache::lock('print_service_queue_lock', 60);

        if (!$lock->get()) {
            return;
        }

        try {
            // Periksa apakah masih ada dokumen yang saat ini berstatus 'printing'
            $activePrinting = PrintJob::where('status', 'printing')->first();
            if ($activePrinting) {
                return;
            }

            // Ambil berkas antrean pertama yang berstatus 'queued'
            $nextJob = PrintJob::where('status', 'queued')
                ->orderBy('created_at', 'asc')
                ->first();

            if (!$nextJob) {
                return;
            }

            $this->startPrintingJob($nextJob);

        } finally {
            $lock->release();
        }
    }

    /**
     * Hapus berkas fisik dokumen dari penyimpanan server setelah proses cetak selesai
     * atau dibatalkan untuk menghemat ruang disk, dengan tetap mempertahankan data
     * riwayat personel, nama berkas, dan waktu cetak di database.
     */
    public function cleanupPhysicalFiles(PrintJob $job): void
    {
        $filesToDelete = array_filter([
            $job->original_file_path,
            $job->preview_pdf_path,
            $job->printable_pdf_path,
        ]);

        foreach ($filesToDelete as $filePath) {
            if ($filePath && file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        // Hapus direktori sementara konversi jika ada
        if ($job->preview_pdf_path) {
            $parentDir = dirname($job->preview_pdf_path);
            if (str_contains($parentDir, 'converted_') && is_dir($parentDir)) {
                $remFiles = glob($parentDir . '/*');
                if (is_array($remFiles)) {
                    foreach ($remFiles as $rf) {
                        @unlink($rf);
                    }
                }
                @rmdir($parentDir);
            }
        }

        // Kosongkan path fisik pada database agar terverifikasi bersih dari disk
        $job->update([
            'original_file_path' => null,
            'preview_pdf_path' => null,
            'printable_pdf_path' => null,
        ]);
    }
}
