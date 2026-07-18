<?php

namespace App\Http\Controllers;

use App\Models\StampLog;
use App\Models\SignatureRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use setasign\Fpdi\Fpdi;

class StampController extends Controller
{
    /**
     * Halaman Utama Manajemen Stempel
     * Memasok data Antrian dan Riwayat ke Radar Vue.
     */
    public function index()
    {
        return Inertia::render('Stamp/StampManagement', [
            // 1. ANTRIAN: Berkas yang sudah di-TTD tapi BELUM distempel
            // Perbaikan: Menambahkan pengecekan status NULL pada is_stamped
            'approvedRequests' => SignatureRequest::where('status', 'approved')
                                    ->where(function($q) {
                                        $q->where('is_stamped', false)
                                          ->orWhereNull('is_stamped');
                                    })
                                    ->latest()
                                    ->get(),

            // 2. RIWAYAT: Mengambil seluruh log berkas yang BERHASIL distempel
            'stampHistory' => StampLog::with('user')
                                    ->latest()
                                    ->get()
                                    ->map(function($log) {
                                        return [
                                            'id' => $log->id,
                                            'subject' => $log->subject,
                                            'file_url' => asset('storage/' . $log->file_path),
                                            'operator' => $log->user ? $log->user->name : 'Sistem',
                                            'date' => $log->created_at->format('d M Y H:i'),
                                            'is_manual' => $log->is_manual
                                        ];
                                    }),

            // 3. MASTER STEMPEL: Untuk pratinjau di Vue
            'commanderStamp' => Setting::where('key', 'commander_stamp')->value('value')
        ]);
    }

    /**
     * AMUNISI BARU: Upload Manual Taktis
     * Berkas disimpan ke database terlebih dahulu sebelum masuk antrian.
     */
    public function uploadManual(Request $request)
    {
        $request->validate([
            'pdf_file' => 'required|mimes:pdf|max:10240',
            'subject' => 'required|string|max:255',
        ]);

        if ($request->hasFile('pdf_file')) {
            // Simpan file ke folder storage signatures agar seragam
            $path = $request->file('pdf_file')->store('signatures', 'public');

            // Daftarkan ke tabel signature_requests dengan status langsung 'approved'
            SignatureRequest::create([
                'user_id' => auth()->id(),
                'subject' => $request->subject,
                'file_path' => $path,
                'status' => 'approved', 
                'is_stamped' => false,
                'letter_number' => 'MANUAL-' . date('YmdHis'),
            ]);

            return back()->with('success', 'Radar: Berkas manual berhasil masuk antrian stempel.');
        }

        return back()->with('error', 'Gagal memproses upload berkas manual.');
    }

    /**
     * AMUNISI BARU: Halaman Konfigurasi Stempel
     * Melayani render StampSetting.vue untuk penggantian Master PNG Stempel.
     */
    public function setting()
    {
        return Inertia::render('Settings/StampSetting', [
            'settings' => Setting::all()->pluck('value', 'key')
        ]);
    }

    /**
     * Eksekusi Proses Stempel (Serangan Pamungkas)
     * Membedah PDF dan menyuntikkan gambar stempel secara fisik di koordinat terpilih.
     */
    public function apply(Request $request)
    {
        $request->validate([
            'subject' => 'required',
            'x' => 'required|numeric',
            'y' => 'required|numeric',
            'width' => 'required|numeric',
            'target_page' => 'required|integer',
        ]);

        $stampFile = Setting::where('key', 'commander_stamp')->value('value');
        $originalFilePath = "";
        $isManual = false;

        // Cek Sumber Berkas: Manual atau Jalur Reguler
        if ($request->hasFile('pdf_file')) {
            $originalFilePath = $request->file('pdf_file')->store('temp_stamps', 'public');
            $isManual = true;
        } else {
            $sigReq = SignatureRequest::findOrFail($request->request_id);
            $originalFilePath = $sigReq->file_path;
        }

        // --- PROSES BEDAH PDF MENGGUNAKAN FPDI ---
        $pdf = new Fpdi();
        $sourcePath = storage_path('app/public/' . $originalFilePath);
        
        try {
            $pageCount = $pdf->setSourceFile($sourcePath);

            for ($i = 1; $i <= $pageCount; $i++) {
                $tplIdx = $pdf->importPage($i);
                $size = $pdf->getImportedPageSize($tplIdx);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplIdx);

                if ($i == $request->target_page) {
                    // Kalkulasi koordinat absolut berdasarkan ukuran kertas asli PDF
                    $absX = $request->x * $size['width'];
                    $absY = $request->y * $size['height'];
                    $absW = $request->width * $size['width'];
                    
                    if ($stampFile && Storage::disk('public')->exists($stampFile)) {
                        $pdf->Image(storage_path('app/public/' . $stampFile), $absX, $absY, $absW);
                    }
                }
            }

            // Tentukan Nama File Baru
            $newFileName = 'stamped/STAMPED_' . time() . '.pdf';
            if (!Storage::disk('public')->exists('stamped')) {
                Storage::disk('public')->makeDirectory('stamped');
            }

            $pdf->Output(storage_path('app/public/' . $newFileName), 'F');

            // --- PENCATATAN LOG (SANGAT VITAL) ---
            StampLog::create([
                'user_id' => auth()->id(),
                'subject' => $request->subject,
                'file_path' => $newFileName,
                'x' => $request->x,
                'y' => $request->y,
                'page' => $request->target_page,
                'is_manual' => $isManual
            ]);

            // Jika dari sistem regular, update status di tabel asal (SignatureRequest)
            if (!$isManual && isset($request->request_id)) {
                SignatureRequest::where('id', $request->request_id)->update(['is_stamped' => true]);
            }

            return back()->with('success', 'Radar: Dokumen Telah Berhasil Distempel dan Diarsipkan!');

        } catch (\Exception $e) {
            \Log::error("Gagal Stempel: " . $e->getMessage());
            return back()->with('error', 'Radar Error: ' . $e->getMessage());
        }
    }
}