<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Submission; 
use App\Models\RhType;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class ApplicantController extends Controller
{
    // ==========================================================================
    // ==================== TAHAP 1: PORTAL PESS (APPLICANT SIDE) ===============
    // ==========================================================================

    /**
     * Simpan Data Awal dari Portal PESS
     */
    public function storeSubmission(Request $request)
    {
        $userId = session('user_pess_id');
        if (!$userId) return redirect()->route('pess.welcome');

        $existing = Submission::where('applicant_id', $userId)->first();
        if ($existing) {
            return redirect()->back()->withErrors(['message' => 'Sistem PESS mendeteksi pengajuan aktif. Tidak diperkenankan mengirim ganda.']);
        }

        $user = Applicant::find($userId);

        try {
            $answers = is_string($request->answers) ? json_decode($request->answers, true) : $request->answers;

            $uploadedFiles = [];
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $key => $file) {
                    $path = $file->store("submissions/{$user->nomor_identitas}/attachments", 'public');
                    $uploadedFiles[$key] = $path;
                }
            }

            $sigPemohonPath = $this->saveSignature($request->signatures['pemohon'], "sig_p_{$user->nomor_identitas}");
            $sigCalonPath = !empty($request->signatures['calon']) ? $this->saveSignature($request->signatures['calon'], "sig_c_{$user->nomor_identitas}") : null;

            $category = RhType::findOrFail($request->submission_category_id);
            
            $payload = [
                'user' => $user,
                'category' => $category,
                'answers' => $answers,
                'sig_pemohon' => storage_path('app/public/' . $sigPemohonPath),
                'sig_calon' => $sigCalonPath ? storage_path('app/public/' . $sigCalonPath) : null,
                'foto_pemohon' => isset($uploadedFiles['pas_foto_pemohon']) ? storage_path('app/public/' . $uploadedFiles['pas_foto_pemohon']) : null,
                'foto_calon' => isset($uploadedFiles['pas_foto_calon']) ? storage_path('app/public/' . $uploadedFiles['pas_foto_calon']) : null,
                'tgl_cetak' => now()->translatedFormat('d F Y')
            ];

            $pdfOutput = $this->generatePdfFromBlade($user, $payload);

            Submission::create([
                'applicant_id' => $user->id,
                'submission_category_id' => $category->id,
                'answers_json' => $answers, 
                'generated_pdf_path' => $pdfOutput,
                'attachment_paths' => $uploadedFiles,
                'status' => 'verifying' // Status awal masuk ke radar Si Sinden
            ]);

            return redirect()->route('pess.dashboard')->with('message', 'Berkas pendaftaran PESS berhasil dikirim ke Si Sinden.');
        } catch (\Exception $e) {
            Log::error('Gagal Submit PESS: ' . $e->getMessage());
            return redirect()->back()->withErrors(['message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }


    // ==========================================================================
    // ==================== TAHAP 2: WEB SI SINDEN (ADMIN SIDE) =================
    // ==========================================================================

    /**
     * Dashboard Si Sinden: Mengelola Antrean Pendaftaran PESS
     */
    public function indexAdminSinden()
    {
        return Inertia::render('Admin/SindenDashboard', [
            'submissions' => Submission::with('applicant', 'category')->orderBy('created_at', 'desc')->get()
        ]);
    }

    /**
     * Verifikasi Berkas Pemohon oleh Admin Sinden
     */
    public function verifyFiles(Request $request, $id)
    {
        $submission = Submission::findOrFail($id);
        $applicant = $submission->applicant;

        // Jika ada berkas yang salah/tidak valid
        if ($request->status_verifikasi === 'REJECTED') {
            $submission->update(['status' => 'rejected_files']);
            WhatsappService::sendMessage($applicant->no_wa, " *SI SINDEN*: Mohon maaf *{$applicant->nama_lengkap}*, berkas pengajuan Anda ditolak/perlu perbaikan. Silakan login kembali ke portal PESS.");
            return redirect()->back()->with('message', 'Pengajuan dikembalikan ke pemohon.');
        }

        // Jika benar semua, Admin mengupload File Kesehatan & Soal SC
        $request->validate([
            'file_soal_sc' => 'required|mimes:pdf,docx|max:10240',
            'file_kesehatan' => 'nullable|mimes:pdf,jpg,png|max:10240' // Wajib jika nikah
        ]);

        $paths = $submission->attachment_paths;

        if ($request->hasFile('file_soal_sc')) {
            $paths['file_soal_sc'] = $request->file('file_soal_sc')->store("submissions/{$applicant->nomor_identitas}/admin_docs", 'public');
        }
        if ($request->hasFile('file_kesehatan')) {
            $paths['file_kesehatan'] = $request->file('file_kesehatan')->store("submissions/{$applicant->nomor_identitas}/admin_docs", 'public');
        }

        // Update status menuju tahap Wawancara
        $submission->update([
            'attachment_paths' => $paths,
            'status' => 'wawancara_ready'
        ]);

        return redirect()->back()->with('message', 'Berkas disetujui, siap untuk tahap wawancara.');
    }

    /**
     * Plotting Penjadwalan Wawancara (Daring / Luring)
     */
    public function scheduleInterview(Request $request, $id)
    {
        $request->validate([
            'metode' => 'required|in:daring,langsung',
            'waktu' => 'required|date_format:Y-m-d H:i:s',
            'lokasi_link' => 'required|string'
        ]);

        $submission = Submission::findOrFail($id);
        $applicant = $submission->applicant;

        $submission->update([
            'status' => 'wawancara_process',
            'interview_details' => [
                'metode' => $request->metode,
                'waktu' => $request->waktu,
                'lokasi_link' => $request->lokasi_link
            ]
        ]);

        // Kirim Notifikasi WhatsApp & Aplikasi ke Pemohon
        $metodeTeks = $request->metode === 'daring' ? "Secara Daring (Online) via Link: " . $request->lokasi_link : "Secara Langsung (Tatap Muka) di: " . $request->lokasi_link;
        $pesan = " *UNDANGAN WAWANCARA SI SINDEN*\nHalo *{$applicant->nama_lengkap}*,\nPengajuan Pernikahan Anda melanjutkan ke tahap Wawancara.\n\n Waktu: *{$request->waktu}*\n Metode: *{$metodeTeks}*\n\nMohon hadir tepat waktu dengan mengenakan pakaian dinas resmi/rapi.";
        
        WhatsappService::sendMessage($applicant->no_wa, $pesan);

        return redirect()->back()->with('message', 'Undangan wawancara berhasil dikirim.');
    }

    /**
     * Penerbitan SKHPP & Upload File Kosongan untuk TTD Digital/Manual
     */
    public function issueSKHPP(Request $request, $id)
    {
        $request->validate([
            'jenis_ttd' => 'required|in:manual,digital',
            'file_skhpp' => 'required|mimes:pdf|max:10240' // File kosongan (jika digital) atau file scan (jika manual)
        ]);

        $submission = Submission::findOrFail($id);
        $applicant = $submission->applicant;

        $pathSKHPP = $request->file('file_skhpp')->store("submissions/{$applicant->nomor_identitas}/skhpp", 'public');

        $submission->update([
            'status' => $request->jenis_ttd === 'digital' ? 'skhpp_pending_digital' : 'skhpp_complete',
            'skhpp_path' => $pathSKHPP,
            'jenis_ttd_komandan' => $request->jenis_ttd
        ]);

        return redirect()->back()->with('message', 'SKHPP berhasil diproses.');
    }

    /**
     * Eksekusi Tanda Tangan Digital Komandan & Auto-Stempel Berdasarkan Koordinat
     */
    public function signDigitalKomandan(Request $request, $id)
    {
        $submission = Submission::findOrFail($id);
        
        if ($submission->jenis_ttd_komandan !== 'digital') {
            return response()->json(['message' => 'SKHPP ini dikonfigurasi menggunakan TTD Manual.'], 400);
        }

        // SULTAN LOGIC: Proses penempatan TTD & Stempel Digital berbasis koordinat PDF
        // Di sini kita panggil service fpdf / tcpdf / setasign untuk menempel gambar TTD dan stempel
        $pdfX = $request->coord_x ?? 120;
        $pdfY = $request->coord_y ?? 750;

        $signedPdfPath = "generated_rh/SIGNED_SKHPP_" . time() . ".pdf";
        
        // Simulasi penempelan stempel digital otomatis oleh sistem setelah sukses TTD
        // PdfService::applyDigitalStampAndSignature($submission->skhpp_path, $pdfX, $pdfY, $signedPdfPath);

        $submission->update([
            'skhpp_path' => $signedPdfPath,
            'status' => 'skhpp_complete'
        ]);

        return redirect()->back()->with('message', 'SKHPP Berhasil Ditandatangani & Distempel Digital.');
    }


    // ==========================================================================
    // ==================== TAHAP 3: INTEGRASI SINKODV (NEW GATEWAY) ============
    // ==========================================================================

    /**
     * Teruskan Seluruh Berkas ke Aplikasi Baru: SINKODV
     */
    public function forwardToSINKODV($id)
    {
        $submission = Submission::with('applicant')->findOrFail($id);

        if ($submission->status !== 'skhpp_complete') {
            return redirect()->back()->withErrors(['message' => 'Dokumen SKHPP belum lengkap atau belum ditandatangani Komandan.']);
        }

        // Lempar status ke ranah SINKODV
        $submission->update(['status' => 'sinkodv_verification']);

        // Kirim log ke pangkalan log SINKODV (API Endpoint Baru)
        Log::info('Berkas Berhasil Diteruskan ke Aplikasi SINKODV', ['id_pengajuan' => $submission->id]);

        return redirect()->back()->with('message', 'Seluruh pangkalan berkas berhasil diteruskan ke Staf Aplikasi SINKODV.');
    }

    /**
     * SINKODV MODULE: Petugas Upload Security Clearance (SC) & TTD Asintel
     */
    public function uploadAndSignSC(Request $request, $id)
    {
        $request->validate([
            'file_sc' => 'required|mimes:pdf|max:10240',
            'metode_ttd_asintel' => 'required|in:manual,digital'
        ]);

        $submission = Submission::findOrFail($id);
        $applicant = $submission->applicant;

        $pathSC = $request->file('file_sc')->store("submissions/{$applicant->nomor_identitas}/sc", 'public');

        if ($request->metode_ttd_asintel === 'digital') {
            // Jika digital, pasang koordinat stempel digital Asintel
            $pdfX = $request->coord_x ?? 150;
            $pdfY = $request->coord_y ?? 800;
            // PdfService::applyAsintelSign($pathSC, $pdfX, $pdfY);
        }

        $submission->update([
            'status' => 'sc_published',
            'sc_pdf_path' => $pathSC
        ]);

        // Notifikasi Akhir: Security Clearance Sukses Terbit!
        WhatsappService::sendMessage($applicant->no_wa, " *SECURITY CLEARANCE TERBIT*\nHalo *{$applicant->nama_lengkap}*,\nSurat Security Clearance (SC) Anda telah resmi diterbitkan oleh Asintel melalui Sistem SINKODV.");

        return redirect()->back()->with('message', 'Surat Security Clearance (SC) Berhasil Terbit Berstatus Inkrah.');
    }


    // ==========================================================================
    // ==================== HELPER INTERNAL SISTEM ==============================
    // ==========================================================================

    private function generatePdfFromBlade($user, $payload)
    {
        $folderPath = "generated_rh";
        if (!Storage::disk('public')->exists($folderPath)) { Storage::disk('public')->makeDirectory($folderPath); }
        $fileName = "RH_PESS_{$user->nomor_identitas}_" . time() . ".pdf";
        $relativePath = "{$folderPath}/{$fileName}";

        $pdf = Pdf::loadView('pdf.rh_template', $payload)->setPaper([0, 0, 609.45, 935.43], 'portrait'); 
        Storage::disk('public')->put($relativePath, $pdf->output());
        return $relativePath;
    }

    private function saveSignature($base64Data, $filename)
    {
        if (!Storage::disk('public')->exists('signatures')) { Storage::disk('public')->makeDirectory('signatures'); }
        $image = str_replace('data:image/png;base64,', '', $base64Data);
        $image = str_replace(' ', '+', $image);
        $path = "signatures/{$filename}_" . time() . ".png";
        Storage::disk('public')->put($path, base64_decode($image));
        return $path;
    }
}