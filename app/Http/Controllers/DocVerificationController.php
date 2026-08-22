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
     * Helper Formatter Jabatan Personel Berdasarkan Role & Database
     */
    private function formatUserJabatan($user)
    {
        if (!$user) return 'PERSONEL SATUAN';
        
        if (!empty($user->jabatan) && !in_array(strtolower(trim($user->jabatan)), ['personel', 'personel satuan'])) {
            return $user->jabatan;
        }

        $role = strtolower(trim($user->role ?? ''));
        $roleMap = [
            'admin'         => 'ADMINISTRATOR SISTEM',
            'komandan'      => 'KOMANDAN',
            'wadan'         => 'WAKIL KOMANDAN',
            'pasops'        => 'PASOPS',
            'pasiops'       => 'PASIOPS',
            'pasimin'       => 'PASIMIN',
            'pasintel'      => 'PASINTEL',
            'pasilog'       => 'PASILOG',
            'dantim'        => 'DANTIM',
            'danunit'       => 'DANUNIT',
            'danunit1'      => 'DAN UNIT I / LID',
            'danunit2'      => 'DAN UNIT II / PAMGAL',
            'danunitteknis' => 'DAN UNIT TEKNIS',
            'kaurmintel'    => 'KAUR MINTEL',
            'paurset'       => 'PAUR SET',
            'staf'          => 'STAF ADMINISTRASI',
            'personel'      => 'PERSONEL SATUAN',
            'bintara'       => 'BINTARA INTEL',
            'tamtama'       => 'TAMTAMA INTEL',
            'pns'           => 'PNS INTEL',
        ];

        return $roleMap[$role] ?? (!empty($role) ? strtoupper($role) : 'PERSONEL SATUAN');
    }

    /**
     * Halaman Publik Verifikasi TTE Berkas Dinas Lainnya
     */
    public function verify($code)
    {
        $code = trim($code);
        $settings = Setting::pluck('value', 'key')->all();

        // 1. Jika kode ternyata milik SKHPP, alihkan langsung ke Verifikasi SKHPP
        if (str_starts_with(strtoupper($code), 'SKHPP-') || Skhpp::where('verification_code', $code)->orWhere('nomor_skhpp', $code)->exists()) {
            return redirect()->route('skhpp.verify', $code);
        }

        // 2. Cari Berkas di Tabel SignatureRequest (Naskah Dinas / Dokumen Lainnya)
        $sigReq = SignatureRequest::with('user')
            ->where('verification_code', $code)
            ->orWhere('verification_code', 'like', "%{$code}%")
            ->orWhere('letter_number', $code)
            ->first();

        // 3. Logika Penentuan Pejabat Penandatangan (Komandan Langsung vs a.n. Komandan)
        $komandan = User::where('role', 'komandan')->first() 
                 ?? User::where('name', 'like', '%Hari Bagio%')->first();

        $approverUser = null;
        if ($sigReq && !empty($sigReq->approved_by)) {
            $approverUser = User::find($sigReq->approved_by);
        }

        // Jika disahkan oleh Komandan Langsung
        $isKomandanLangsung = false;
        if ($approverUser) {
            $isKomandanLangsung = ($approverUser->role === 'komandan' || str_contains(strtolower($approverUser->name), 'hari bagio'));
        } elseif ($sigReq) {
            // Default untuk dokumen yang langsung diajukan/disahkan komandan
            $isKomandanLangsung = ($sigReq->user?->role === 'komandan');
        }

        if ($isKomandanLangsung) {
            $cName = $komandan?->name ?? 'Hari Bagio Wijayanto, M.Tr.Opsla.';
            $cRank = $komandan?->pangkat ?? 'Kolonel Laut (E)';
            $cNrp = ($komandan?->nrp && $komandan->nrp !== '00000000000000') ? $komandan->nrp : '16085/P';

            $signerName = strtoupper($cName);
            $signerTitle = "Komandan Detasemen Intelijen Kodaeral V - {$cRank} NRP {$cNrp}";
        } else {
            // a.n. Komandan (Disahkan oleh Admin / Administrator / Staf Otoritas)
            $aUser = $approverUser ?: ($sigReq?->user ?: auth()->user());
            $aName = $aUser?->name ?? 'Administrator SINDEN';
            $aRank = $aUser?->pangkat ?? 'Letnan Dua Laut (KC)';
            $aNrp = ($aUser?->nrp && $aUser->nrp !== '00000000000000') ? $aUser->nrp : '12000018012200216';

            $signerName = "a.n. KOMANDAN DETASEMEN INTELIJEN KODAERAL V";
            $signerTitle = "{$aRank} {$aName} NRP {$aNrp}";
        }

        if ($sigReq) {
            // Tarik identitas asli pengaju/pemohon (subjek pada berkas PDF) dari pangkalan data users
            $applicantUser = null;
            if (!empty($sigReq->person_name)) {
                $applicantUser = User::where('name', trim($sigReq->person_name))->first();
            }
            if (!$applicantUser && !empty($sigReq->pangkat_nrp)) {
                if (preg_match('/NRP\.?\s*([A-Za-z0-9\/]+)/i', $sigReq->pangkat_nrp, $matches)) {
                    $extractedNrp = trim($matches[1]);
                    $applicantUser = User::where('nrp', $extractedNrp)->first();
                }
            }
            if (!$applicantUser) {
                $applicantUser = $sigReq->user ?: ($sigReq->user_id ? User::find($sigReq->user_id) : null);
            }

            $applicantName = $sigReq->person_name ?: ($applicantUser?->name ?? 'Personel Denintel Kodaeral V');
            $applicantRank = $applicantUser?->pangkat ?: 'Prajurit TNI AL';
            $applicantNrp = ($applicantUser?->nrp && $applicantUser->nrp !== '00000000000000') ? $applicantUser->nrp : ($applicantUser?->username ?? '-');
            
            $applicantIdentity = $sigReq->pangkat_nrp ?: "{$applicantRank} / NRP {$applicantNrp}";
            
            // Sinkronisasi Jabatan & Satuan Personel Subjek Berkas
            $userJabatanName = $this->formatUserJabatan($applicantUser);
            $sigReqJabatan = trim($sigReq->jabatan ?? '');
            
            // Cek apakah jabatan pada SignatureRequest bernilai generik (Personel / Pendaftaran Otoritas)
            $isGenericJabatan = empty($sigReqJabatan) 
                             || str_contains(strtolower($sigReqJabatan), 'personel') 
                             || str_contains(strtolower($sigReqJabatan), 'pendaftaran otoritas');

            if (!$isGenericJabatan) {
                $applicantPosition = $sigReqJabatan;
            } else {
                $applicantPosition = str_contains(strtolower($userJabatanName), 'denintel') 
                                   ? $userJabatanName 
                                   : ($userJabatanName . ' / Denintel Kodaeral V');
            }

            $signedDate = $sigReq->approved_at ?: $sigReq->updated_at;
            $isValid = in_array(strtolower($sigReq->status), ['approved', 'signed', 'selesai']);

            // Jamin Kode Verifikasi adalah Kode Unik Berkas, Bukan NRP
            $uniqueDocCode = $sigReq->verification_code;
            if (empty($uniqueDocCode) || is_numeric($uniqueDocCode) || strlen($uniqueDocCode) > 25) {
                $uniqueDocCode = 'TTE-DOC-' . date('Ymd', strtotime($sigReq->created_at ?? now())) . '-' . strtoupper(substr(md5($sigReq->id . ($sigReq->created_at ?? now())), 0, 6));
            }

            $docData = (object) [
                'id' => $sigReq->id,
                'verification_code' => $uniqueDocCode,
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
                'signer_name' => $signerName,
                'signer_title' => $signerTitle,
                'sha256_hash' => hash('sha256', $uniqueDocCode . ($signedDate ?? now())),
                'file_url' => $sigReq->file_path ? asset('storage/' . $sigReq->file_path) : null,
            ];

            return Inertia::render('Verify/DocVerify', [
                'doc' => $docData,
                'verify_code' => $uniqueDocCode,
                'settings' => $settings
            ]);
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

        if (str_starts_with(strtoupper($code), 'SKHPP-') || Skhpp::where('verification_code', $code)->orWhere('nomor_skhpp', $code)->exists()) {
            return redirect()->route('skhpp.verify', $code);
        }

        return $this->verify($code);
    }
}
