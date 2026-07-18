<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SindenToSinkodvController extends Controller
{
    /**
     * OPERASI 1: Meneruskan Seluruh Paket Data Berkas ke API SINKODV
     */
    public function forwardPayloadToSinkodv($submissionId)
    {
        $submission = Submission::with('applicant', 'category')->findOrFail($submissionId);

        // Siapkan payload terenkripsi yang dibutuhkan oleh SINKODV
        $payload = [
            'pess_submission_id' => $submission->id,
            'kategori'           => $submission->category->name,
            'needs_double_rh'    => $submission->category->needs_double_rh,
            'pemohon'            => [
                'nama'  => $submission->applicant->nama_lengkap,
                'ktp'   => $submission->applicant->nomor_identitas,
                'no_wa' => $submission->applicant->no_wa,
                'data'  => $submission->answers_json['pemohon'] ?? [],
            ],
            'calon'              => $submission->answers_json['calon'] ?? [],
            'berkas_pess'        => [
                'pdf_drh'     => url('storage/' . $submission->generated_pdf_path),
                'attachments' => $submission->attachment_paths, // SKCK, Ijazah, Medis, Soal SC
                'skhpp_final' => url('storage/' . $submission->skhpp_path),
            ],
            'timestamp_forward'  => now()->toDateTimeString()
        ];

        try {
            // Tembakkan data ke API Endpoint Aplikasi SINKODV
            // SULTAN CONFIG: Sesuaikan URL pangkalan SINKODV di config/.env
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.sinkodv.token'),
                'Accept'        => 'application/json'
            ])->post(config('services.sinkodv.url') . '/api/v1/clearance/receive', $payload);

            if ($response->successful()) {
                $submission->update(['status' => 'sinkodv_verification']);
                Log::info('Pangkalan data PESS & Sinden berhasil mendarat di SINKODV.', ['submission_id' => $submissionId]);
                
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Paket data clearance berhasil diteruskan ke SINKODV.'
                ], 200);
            }

            throw new \Exception('SINKODV Merespon dengan kode error: ' . $response->status());

        } catch (\Exception $e) {
            Log::error('Mogok Sistem saat melempar data ke SINKODV: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal sinkronisasi ke SINKODV: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * OPERASI 2: Webhook Callback dari SINKODV (Menerima Hasil Akhir SC Asintel)
     */
    public function handleSinkodvCallback(Request $request)
    {
        // Validasi token pengirim untuk memastikan yang menembak benar-benar aplikasi SINKODV
        $incomingToken = $request->bearerToken();
        if ($incomingToken !== config('services.sinkodv.webhook_token')) {
            return response()->json(['message' => 'Radar mendeteksi akses tidak sah (Unauthorized)'], 401);
        }

        $request->validate([
            'pess_submission_id' => 'required|exists:submissions,id',
            'sc_pdf_url'         => 'required|url',
            'status_final'       => 'required|in:APPROVED,REJECTED',
            'catatan_asintel'    => 'nullable|string'
        ]);

        $submission = Submission::findOrFail($request->pess_submission_id);
        $applicant = $submission->applicant;

        if ($request->status_final === 'APPROVED') {
            // Download file SC dari SINKODV dan amankan di storage lokal Sinden
            $fileContent = file_get_contents($request->sc_pdf_url);
            $fileName = "SC_FINAL_{$applicant->nomor_identitas}_" . time() . ".pdf";
            $localPath = "submissions/{$applicant->nomor_identitas}/sc/{$fileName}";
            
            Storage::disk('public')->put($localPath, $fileContent);

            $submission->update([
                'status'      => 'sc_published',
                'sc_pdf_path' => $localPath
            ]);

            // Tembakkan Notifikasi Kemenangan/Kelulusan via WhatsApp Pemohon
            $pesan = "🎉 *SECURITY CLEARANCE (SC) RESMI TERBIT*\nHalo *{$applicant->nama_lengkap}*,\nKami informasikan dari pangkalan *SINKODV* bahwa Surat Security Clearance (SC) Anda telah resmi *DITERBITKAN* dan ditandatangani oleh Asintel.\n\nBerkas fisik dapat diambil di kantor Detasemen Intelijen atau diunduh melalui portal PESS Anda.";
            WhatsappService::sendMessage($applicant->no_wa, $pesan);

            return response()->json(['status' => 'inkrah', 'message' => 'Pangkalan data Sinden berhasil diupdate, SC terbit.'], 200);
        } else {
            $submission->update(['status' => 'sinkodv_rejected']);
            
            $pesan = "❌ *NOTIFIKASI SINKODV*\nHalo *{$applicant->nama_lengkap}*,\nBerdasarkan hasil verifikasi akhir staf SINKODV, pengajuan Anda dinyatakan *DITOLAK/DITANGGUHKAN*.\nCatatan: {$request->catatan_asintel}";
            WhatsappService::sendMessage($applicant->no_wa, $pesan);

            return response()->json(['status' => 'rejected', 'message' => 'Status penolakan berhasil dicatat.'], 200);
        }
    }
}