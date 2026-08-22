<?php

namespace App\Http\Controllers;

use App\Models\SignatureRequest;
use App\Models\Skhpp;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DocVerificationController extends Controller
{
    /**
     * Helper Konversi Angka Bulan ke Romawi
     */
    private function getRomanMonth($month)
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        return $map[(int)$month] ?? 'I';
    }

    /**
     * Halaman Publik Verifikasi TTE Berkas Dinas Lainnya
     */
    public function verify($code)
    {
        $code = trim($code);
        $settings = Setting::pluck('value', 'key')->all();

        // 1. Cari Berkas di Tabel SignatureRequest (Naskah Dinas / Dokumen Lainnya)
        $sigReq = SignatureRequest::with('user')
            ->where('verification_code', $code)
            ->orWhere('verification_code', 'like', "%{$code}%")
            ->orWhere('letter_number', $code)
            ->first();

        // 2. Data Pejabat Penandatangan (Komandan Detasemen Intelijen) dari Database
        $commander = User::where('role', 'komandan')->first() 
                  ?? User::where('role', 'admin')->where('name', 'like', '%Hari Bagio%')->first()
                  ?? User::where('role', 'admin')->first();

        $commanderName = $commander?->name ?? 'Hari Bagio Wijayanto, M.Tr.Opsla.';
        $commanderRank = $commander?->pangkat ?? 'Kolonel Laut (E)';
        $commanderNrp = ($commander?->nrp && $commander->nrp !== '00000000000000') ? $commander->nrp : '16085/P';
        $commanderTitle = "Komandan Detasemen Intelijen Kodaeral V - {$commanderRank} NRP {$commanderNrp}";

        if ($sigReq) {
            // Tarik identitas asli pengaju/pemohon dari tabel users di database
            $applicantUser = $sigReq->user ?: ($sigReq->user_id ? User::find($sigReq->user_id) : null);
            $applicantName = $sigReq->person_name ?: ($applicantUser?->name ?? 'Personel Denintel Kodaeral V');
            
            $applicantRank = $applicantUser?->pangkat ?: 'Prajurit TNI AL';
            $applicantNrp = ($applicantUser?->nrp && $applicantUser->nrp !== '00000000000000') ? $applicantUser->nrp : ($applicantUser?->username ?? '-');
            
            $applicantIdentity = $sigReq->pangkat_nrp ?: "{$applicantRank} / NRP {$applicantNrp}";
            $applicantPosition = $sigReq->jabatan ?: ($applicantUser?->jabatan ?? 'Personel Detasemen Intelijen Kodaeral V');

            $signedDate = $sigReq->approved_at ?: $sigReq->updated_at;
            $isValid = in_array(strtolower($sigReq->status), ['approved', 'signed', 'selesai']);

            $docData = (object) [
                'id' => $sigReq->id,
                'verification_code' => $sigReq->verification_code ?: $code,
                'document_title' => $sigReq->document_title ?: 'NASKAH DINAS / DOKUMEN RESMI KEDINASAN',
                'letter_number' => $sigReq->letter_number ?: ("B/" . $sigReq->id . "/VIII/" . date('Y') . "/Denintel"),
                'subject' => $sigReq->subject ?: 'Surat Dinas Resmi Kedinasan',
                'peruntukan' => $sigReq->peruntukan ?: $sigReq->subject,
                'nama' => $applicantName,
                'pangkat_korps_nrp' => $applicantIdentity,
                'jabatan_pekerjaan' => $applicantPosition,
                'tanggal_dokumen' => $signedDate,
                'status' => $sigReq->status,
                'is_valid' => $isValid,
                'signer_name' => $commanderName,
                'signer_title' => $commanderTitle,
                'sha256_hash' => hash('sha256', ($sigReq->verification_code ?: $code) . ($signedDate ?? now())),
                'file_url' => $sigReq->file_path ? asset('storage/' . $sigReq->file_path) : null,
            ];

            return Inertia::render('Verify/DocVerify', [
                'doc' => $docData,
                'verify_code' => $code,
                'settings' => $settings
            ]);
        }

        // Jika tidak ditemukan di SignatureRequest, periksa apakah merupakan token SKHPP
        $skhpp = Skhpp::where('verification_code', $code)->first();
        if ($skhpp) {
            return redirect()->route('skhpp.verify', $code);
        }

        // Dokumen tidak ditemukan / Palsu
        return Inertia::render('Verify/DocVerify', [
            'doc' => null,
            'verify_code' => $code,
            'settings' => $settings
        ]);
    }

    /**
     * Rute Verifikasi Pintar Umum (/verify/{code})
     */
    public function verifyGeneral($code)
    {
        $code = trim($code);

        if (str_starts_with(strtoupper($code), 'SKHPP-') || Skhpp::where('verification_code', $code)->exists()) {
            return redirect()->route('skhpp.verify', $code);
        }

        return $this->verify($code);
    }
}