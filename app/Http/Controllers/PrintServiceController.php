<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use App\Models\Setting;
use App\Services\PrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PrintServiceController extends Controller
{
    protected PrintService $printService;

    public function __construct(PrintService $printService)
    {
        $this->printService = $printService;
    }

    /**
     * Halaman Utama Layanan Printing
     */
    public function index()
    {
        $user = Auth::user();

        $activeJob = PrintJob::with('user')
            ->where('status', 'printing')
            ->first();

        $queuedJobs = PrintJob::with('user')
            ->where('status', 'queued')
            ->orderBy('created_at', 'asc')
            ->get();

        $recentJobs = PrintJob::with('user')
            ->whereIn('status', ['completed', 'failed', 'cancelled'])
            ->latest()
            ->take(15)
            ->get();

        $printerSettings = [
            'printer_ip' => Setting::where('key', 'printer_brother_ip')->value('value') ?: '192.168.1.200',
            'printer_port' => (int)(Setting::where('key', 'printer_brother_port')->value('value') ?: 9100),
            'printer_name' => Setting::where('key', 'printer_brother_name')->value('value') ?: 'Brother Network Printer',
            'color_mode' => 'monochrome',
            'warning_notice' => 'Layanan ini hanya tersedia warna Hitam putih saja',
        ];

        return Inertia::render('PrintService/Index', [
            'activeJob' => $activeJob,
            'queuedJobs' => $queuedJobs,
            'recentJobs' => $recentJobs,
            'printerSettings' => $printerSettings,
            'stats' => [
                'total_queued' => $queuedJobs->count(),
                'total_today' => PrintJob::whereDate('created_at', today())->count(),
                'sheets_today' => (int)PrintJob::where('status', 'completed')->whereDate('completed_at', today())->sum('printed_sheets'),
            ]
        ]);
    }

    /**
     * Unggah berkas dokumen (PDF / DOCX), lakukan konversi, sisipkan lembar pemisah,
     * dan siapkan pratinjau sebelum dicetak
     */
    public function uploadAndPreview(Request $request)
    {
        $request->validate([
            'document_title' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,docx,doc|max:51200', // Maksimal 50MB
        ]);

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $fileType = strtolower($file->getClientOriginalExtension());

        // Simpan berkas asli
        $storedPath = $file->store('print_jobs/originals', 'local');
        $originalFullPath = Storage::disk('local')->path($storedPath);
        if (!file_exists($originalFullPath)) {
            $fallbackPath = storage_path('app/' . $storedPath);
            if (file_exists($fallbackPath)) {
                $originalFullPath = $fallbackPath;
            }
        }

        try {
            // 1. Dapatkan berkas PDF untuk pratinjau (konversi jika DOCX / DOC)
            $previewPdfPath = $this->printService->convertToPdf($originalFullPath, $fileType);

            // 2. Siapkan berkas siap cetak dengan menyisipkan 1 lembar kosong pemisah
            $prepResult = $this->printService->preparePrintablePdf($previewPdfPath);

            // 3. Simpan entitas PrintJob berstatus 'draft'
            $printerIp = Setting::where('key', 'printer_brother_ip')->value('value') ?: '192.168.1.200';

            $job = PrintJob::create([
                'user_id' => Auth::id(),
                'document_title' => strtoupper($request->document_title),
                'original_filename' => $originalFilename,
                'file_type' => $fileType,
                'original_file_path' => $originalFullPath,
                'preview_pdf_path' => $previewPdfPath,
                'printable_pdf_path' => $prepResult['printable_pdf_path'],
                'total_pages' => $prepResult['total_pages'],
                'separator_pages' => $prepResult['separator_pages'],
                'total_sheets' => $prepResult['total_sheets'],
                'printed_sheets' => 0,
                'copies' => 1,
                'color_mode' => 'monochrome',
                'printer_ip' => $printerIp,
                'status' => 'draft',
            ]);

            return response()->json([
                'success' => true,
                'job' => $job,
                'preview_url' => route('printing.preview', $job->id),
                'message' => 'Berkas berhasil diproses. Silakan periksa pratinjau dokumen sebelum mencetak.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses berkas dokumen: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Mengalirkan streaming berkas PDF untuk penampil pratinjau di browser
     */
    public function streamPreviewPdf($id)
    {
        $job = PrintJob::findOrFail($id);

        // Hanya pemilik atau admin yang dapat melihat berkas
        if ($job->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403, 'Akses tidak diizinkan.');
        }

        $pdfPath = $job->preview_pdf_path;
        if (!$pdfPath || !file_exists($pdfPath)) {
            abort(404, 'Berkas pratinjau dokumen tidak ditemukan.');
        }

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($pdfPath) . '"'
        ]);
    }

    /**
     * Konfirmasi cetak dokumen setelah personel memeriksa pratinjau
     */
    public function confirmPrint(Request $request, $id)
    {
        $job = PrintJob::findOrFail($id);

        if ($job->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403, 'Akses tidak diizinkan.');
        }

        if ($job->status !== 'draft') {
            return back()->with('error', 'Dokumen ini sudah pernah diproses.');
        }

        $job->update([
            'status' => 'queued',
            'error_message' => null
        ]);

        // Picu pemrosesan antrean
        $this->printService->processQueue();

        return back()->with('success', 'Dokumen berhasil dimasukkan ke dalam antrean cetak.');
    }

    /**
     * Batalkan dokumen dari antrean
     */
    public function cancelJob($id)
    {
        $job = PrintJob::findOrFail($id);

        if ($job->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403, 'Akses tidak diizinkan.');
        }

        if ($job->status === 'queued' || $job->status === 'draft') {
            $this->printService->cleanupPhysicalFiles($job);
            $job->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);
            return back()->with('success', 'Pengajuan cetak dokumen berhasil dibatalkan dan berkas fisik dibersihkan.');
        }

        return back()->with('error', 'Dokumen yang sedang dicetak atau telah selesai tidak dapat dibatalkan.');
    }

    /**
     * Endpoint data status antrean untuk pembaruan latar belakang secara berkala
     */
    public function getQueueStatus()
    {
        $activeJob = PrintJob::with('user')
            ->where('status', 'printing')
            ->first();

        $queuedJobs = PrintJob::with('user')
            ->where('status', 'queued')
            ->orderBy('created_at', 'asc')
            ->get();

        $recentJobs = PrintJob::with('user')
            ->whereIn('status', ['completed', 'failed', 'cancelled'])
            ->latest()
            ->take(10)
            ->get();

        // Jika tidak ada job yang sedang dicetak namun masih ada antrean yang menunggu,
        // picu proses antrean untuk menjaga kestabilan
        if (!$activeJob && $queuedJobs->isNotEmpty()) {
            $this->printService->processQueue();
        }

        return response()->json([
            'active_job' => $activeJob,
            'queued_jobs' => $queuedJobs,
            'recent_jobs' => $recentJobs,
            'stats' => [
                'total_queued' => $queuedJobs->count(),
                'total_today' => PrintJob::whereDate('created_at', today())->count(),
                'sheets_today' => (int)PrintJob::where('status', 'completed')->whereDate('completed_at', today())->sum('printed_sheets'),
            ]
        ]);
    }

    /**
     * Tes koneksi soket IP Printer Brother (Khusus Admin)
     */
    public function testPrinterConnection(Request $request)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Otoritas ditolak.'], 403);
        }

        $ip = $request->input('ip');
        $port = (int)$request->input('port', 9100);

        $result = $this->printService->testPrinterConnection($ip, $port);
        return response()->json($result);
    }

    /**
     * Pembaruan pengaturan IP Printer Brother (Khusus Admin)
     */
    public function updatePrinterSettings(Request $request)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Otoritas ditolak.');
        }

        $request->validate([
            'printer_ip' => 'required|string|max:100',
            'printer_port' => 'required|integer|min:1|max:65535',
            'printer_name' => 'nullable|string|max:100',
        ]);

        Setting::updateOrCreate(['key' => 'printer_brother_ip'], ['value' => trim($request->printer_ip)]);
        Setting::updateOrCreate(['key' => 'printer_brother_port'], ['value' => (string)$request->printer_port]);
        if ($request->filled('printer_name')) {
            Setting::updateOrCreate(['key' => 'printer_brother_name'], ['value' => trim($request->printer_name)]);
        }

        return back()->with('success', 'Konfigurasi Printer Brother berhasil diperbarui.');
    }
}
