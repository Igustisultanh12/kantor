<?php

namespace App\Http\Controllers;

use App\Models\ScSubmission;
use App\Models\ScSubmissionLog;
use App\Models\Skhpp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\WhatsappService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ScSubmissionController extends Controller
{
    /**
     * Daftar Berkas Pengajuan SC untuk Admin & Petugas
     */
    public function index(Request $request)
    {
        ScSubmission::ensureSchema();
        if (!Schema::hasTable('sc_submissions')) {
            return Inertia::render('ScSubmission/Index', [
                'submissions' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15),
                'stats' => [
                    'total' => 0,
                    'in_progress' => 0,
                    'at_denintel' => 0,
                    'at_sintel' => 0,
                    'completed' => 0,
                ],
                'filters' => $request->only(['search', 'stage', 'status']),
                'stages' => array_values(ScSubmission::STAGES),
            ]);
        }

        // Bersihkan pratinjau yang telah melewati batas 2x24 jam (48 jam) jika kolom tersedia
        if (Schema::hasColumn('sc_submissions', 'file_sc_preview')) {
            $withPreviews = ScSubmission::whereNotNull('file_sc_preview')->get();
            foreach ($withPreviews as $subItem) {
                $subItem->checkAndPurgeExpiredPreview();
            }
        }

        $relations = ['creator'];
        if (Schema::hasTable('sc_submission_logs')) {
            $relations[] = 'logs';
        }
        if (Schema::hasColumn('sc_submissions', 'skhpp_id')) {
            $relations[] = 'skhpp';
        }

        $query = ScSubmission::with($relations)->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $cleanSearch = str_replace([' ', '-', '.'], '', $search);
            $query->where(function ($q) use ($search, $cleanSearch) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('identifier_number', 'like', "%{$search}%")
                  ->orWhereRaw("REPLACE(REPLACE(REPLACE(identifier_number, ' ', ''), '-', ''), '.', '') LIKE ?", ["%{$cleanSearch}%"])
                  ->orWhere('tracking_code', 'like', "%{$search}%")
                  ->orWhere('kesatuan', 'like', "%{$search}%");
                if (Schema::hasColumn('sc_submissions', 'nomor_sc')) {
                    $q->orWhere('nomor_sc', 'like', "%{$search}%");
                }
            });
        }

        if ($request->filled('stage') && $request->stage !== 'all' && Schema::hasColumn('sc_submissions', 'current_stage')) {
            $query->where('current_stage', (int)$request->stage);
        }

        if ($request->filled('status') && $request->status !== 'all' && Schema::hasColumn('sc_submissions', 'status')) {
            $query->where('status', $request->status);
        }

        $submissions = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => ScSubmission::count(),
            'in_progress' => Schema::hasColumn('sc_submissions', 'current_stage') ? ScSubmission::where('current_stage', '<', 10)->where('status', '!=', 'ditolak')->count() : 0,
            'at_denintel' => Schema::hasColumn('sc_submissions', 'current_stage') ? ScSubmission::where('current_stage', '<=', 5)->where('status', '!=', 'ditolak')->count() : 0,
            'at_sintel' => Schema::hasColumn('sc_submissions', 'current_stage') ? ScSubmission::whereBetween('current_stage', [6, 9])->where('status', '!=', 'ditolak')->count() : 0,
            'completed' => Schema::hasColumn('sc_submissions', 'current_stage') ? ScSubmission::where('current_stage', 10)->orWhere('status', 'selesai')->count() : 0,
        ];

        return Inertia::render('ScSubmission/Index', [
            'submissions' => $submissions,
            'stats' => $stats,
            'filters' => $request->only(['search', 'stage', 'status']),
            'stages' => array_values(ScSubmission::STAGES),
        ]);
    }

    /**
     * Mendaftarkan Pengajuan SC Baru secara Manual oleh Petugas (Termasuk Upload PDF SKHPP Basah)
     */
    public function store(Request $request)
    {
        // Jalankan self-healing skema basis data sebelum DDL/DML transaksi
        ScSubmission::ensureSchema();

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'pangkat_korps' => 'nullable|string|max:100',
            'identifier_type' => 'required|in:nrp,nip,nik,nim',
            'identifier_number' => 'required|string|max:50',
            'kesatuan' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'keperluan' => 'nullable|string|max:255',
            'current_stage' => 'nullable|integer|min:1|max:10',
            'status' => 'nullable|string|in:proses,selesai,perbaikan,ditolak',
            'catatan_petugas' => 'nullable|string|max:1000',
            'nomor_surat_rh' => 'nullable|string|max:100',
            'nomor_skhpp' => 'nullable|string|max:100',
            'nomor_sc' => 'nullable|string|max:100',
            'file_skhpp' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        try {
            DB::beginTransaction();

            $stage = (int)($validated['current_stage'] ?? 1);
            $stageInfo = ScSubmission::STAGES[$stage] ?? ScSubmission::STAGES[1];

            // Format kode tracking: SC-YYYYMMDD-XXXXX
            $trackingCode = 'SC-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            // Penanganan Unggah PDF SKHPP Tanda Tangan Basah
            $fileSkhppPath = null;
            if ($request->hasFile('file_skhpp')) {
                $fileSkhppPath = $request->file('file_skhpp')->store('sc_documents', 'public');
            }

            $allFields = [
                'tracking_code' => $trackingCode,
                'nomor_resi' => $trackingCode,
                'nama' => $validated['nama'],
                'identifier_type' => $validated['identifier_type'],
                'identifier_number' => trim($validated['identifier_number']),
                'current_stage' => $stage,
                'status' => $stage === 10 ? 'selesai' : ($validated['status'] ?? 'proses'),
                'pangkat_korps' => $validated['pangkat_korps'] ?? null,
                'kesatuan' => $validated['kesatuan'] ?? null,
                'jabatan' => $validated['jabatan'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'keperluan' => $validated['keperluan'] ?? null,
                'catatan_petugas' => $validated['catatan_petugas'] ?? null,
                'nomor_surat_rh' => $validated['nomor_surat_rh'] ?? null,
                'nomor_skhpp' => $validated['nomor_skhpp'] ?? null,
                'nomor_sc' => $validated['nomor_sc'] ?? null,
                'file_skhpp' => $fileSkhppPath,
                'created_by' => Auth::id(),
            ];

            $submissionData = [];
            foreach ($allFields as $key => $val) {
                if ($val !== null && Schema::hasColumn('sc_submissions', $key)) {
                    $submissionData[$key] = $val;
                }
            }

            // Sanitasi cerdas tipe data dinamis & pengisian nilai bawaan otomatis
            try {
                $dbColumns = DB::select("SHOW COLUMNS FROM sc_submissions");
                foreach ($dbColumns as $col) {
                    $name = $col->Field ?? $col->field ?? null;
                    if (!$name || in_array($name, ['id', 'created_at', 'updated_at'])) {
                        continue;
                    }

                    $type = strtolower($col->Type ?? $col->type ?? '');
                    $isNull = ($col->Null ?? $col->null ?? 'YES') === 'NO';
                    $hasDefault = ($col->Default ?? $col->default ?? null) !== null;

                    // 1. Jika nilai terisi di submissionData, pastikan tipe datanya cocok dengan tipe kolom MySQL
                    if (array_key_exists($name, $submissionData)) {
                        $val = $submissionData[$name];
                        if ($val !== null) {
                            if (str_contains($type, 'int') && !is_numeric($val)) {
                                if ($name === 'status') {
                                    $submissionData[$name] = match ($val) {
                                        'selesai' => 2,
                                        'perbaikan' => 3,
                                        'ditolak' => 4,
                                        default => 1,
                                    };
                                } else {
                                    $submissionData[$name] = (int)$val;
                                }
                            }
                        }
                    } else {
                        // 2. Jika nilai belum terisi dan kolom MySQL berstatus NOT NULL tanpa default
                        if ($isNull && !$hasDefault) {
                            if (str_contains($type, 'int') || str_contains($type, 'decimal') || str_contains($type, 'float')) {
                                $submissionData[$name] = ($name === 'status') ? 1 : 0;
                            } elseif (str_contains($type, 'date') || str_contains($type, 'time')) {
                                $submissionData[$name] = now();
                            } else {
                                if ($name === 'nomor_resi') {
                                    $submissionData[$name] = $trackingCode;
                                } elseif ($name === 'tipe_permohonan') {
                                    $submissionData[$name] = 'baru';
                                } else {
                                    $submissionData[$name] = '-';
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Type-safety sanitizer error: ' . $e->getMessage());
            }

            $submission = ScSubmission::create($submissionData);

            // Catat riwayat log inisialisasi jika tabel logs ada
            if (Schema::hasTable('sc_submission_logs')) {
                ScSubmissionLog::create([
                    'sc_submission_id' => $submission->id,
                    'stage' => $stage,
                    'stage_title' => $stageInfo['title'],
                    'notes' => $validated['catatan_petugas'] ?: 'Pendaftaran pengajuan berkas Security Clearance di sistem.',
                    'user_id' => Auth::id(),
                    'user_name' => Auth::user()->name ?? 'Petugas Kedinasan',
                ]);
            }

            DB::commit();

            // Kirim Notifikasi WhatsApp ke Pemohon (Hanya diawal saat pendaftaran, tidak berlaku saat update status)
            $targetPhone = $submission->phone ?? ($validated['phone'] ?? null);
            if (empty($targetPhone)) {
                $personel = User::where('nrp', $submission->identifier_number)->first();
                $targetPhone = $personel?->phone;
            }

            if (!empty($targetPhone)) {
                try {
                    $pangkatNama = trim(($submission->pangkat_korps ? $submission->pangkat_korps . ' ' : '') . $submission->nama);
                    $identitasLabel = strtoupper($submission->identifier_type ?? 'NRP');
                    $trackingUrl = route('tracking-sc.index');

                    $waMessage = "*PEMBERITAHUAN PENGAJUAN SECURITY CLEARANCE (SC)*\n" .
                                 "*DENINTEL KODAERAL V*\n\n" .
                                 "Yth. *{$pangkatNama}*\n" .
                                 "{$identitasLabel}: {$submission->identifier_number}\n\n" .
                                 "Pengajuan berkas Security Clearance (SC) Anda telah berhasil didaftarkan ke dalam sistem SINDEN dengan rincian:\n\n" .
                                 "- *Kode Pelacakan:* {$trackingCode}\n" .
                                 "- *Keperluan:* " . ($submission->keperluan ?: 'Kedinasan') . "\n" .
                                 "- *Satuan/Kesatuan:* " . ($submission->kesatuan ?: '-') . "\n" .
                                 "- *Tahap Saat Ini:* Tahap {$stage}: {$stageInfo['title']}\n\n" .
                                 "Anda dapat memantau posisi dan perkembangan berkas secara transparan tanpa harus login melalui tautan resmi:\n" .
                                 "{$trackingUrl}\n\n" .
                                 "*(Cukup masukkan {$identitasLabel} Anda pada halaman tersebut untuk melihat posisi berkas)*.\n\n" .
                                 "Demikian pemberitahuan ini disampaikan. Terima kasih.";

                    WhatsappService::sendMessage($targetPhone, $waMessage);
                } catch (\Throwable $e) {
                    Log::warning("Gagal mengirim notifikasi WA pengajuan SC: " . $e->getMessage());
                }
            }

            return redirect()->back()->with('success', "Lapor! Pengajuan Security Clearance atas nama {$submission->nama} berhasil didaftarkan dengan Kode Tracking {$trackingCode}.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Gagal mendaftarkan pengajuan SC: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return redirect()->back()->withErrors(['error' => 'Gagal mendaftarkan berkas: ' . $e->getMessage()]);
        }
    }

    /**
     * Memperbarui Tahapan Operasional Berkas SC & Unggah Dokumen Petinjau
     */
    public function updateStage(Request $request, $id)
    {
        $submission = ScSubmission::findOrFail($id);

        $validated = $request->validate([
            'stage' => 'required|integer|min:1|max:10',
            'notes' => 'nullable|string|max:1000',
            'status' => 'nullable|in:proses,selesai,perbaikan,ditolak',
            'nomor_skhpp' => 'nullable|string|max:100',
            'nomor_sc' => 'nullable|string|max:100',
            'file_skhpp' => 'nullable|file|mimes:pdf|max:10240',
            'file_sc_preview' => 'nullable|file|mimes:pdf|max:15360',
        ]);

        $newStage = (int)$validated['stage'];
        $stageInfo = ScSubmission::STAGES[$newStage] ?? ScSubmission::STAGES[1];

        $status = $validated['status'] ?? $submission->status;
        if ($newStage === 10) {
            $status = 'selesai';
        }

        $updateData = [
            'current_stage' => $newStage,
            'status' => $status,
        ];

        if (!empty($validated['notes'])) {
            $updateData['catatan_petugas'] = $validated['notes'];
        }

        if (isset($validated['nomor_skhpp'])) {
            $updateData['nomor_skhpp'] = $validated['nomor_skhpp'];
        }

        if (isset($validated['nomor_sc'])) {
            $updateData['nomor_sc'] = $validated['nomor_sc'];
        }

        // Unggah PDF SKHPP Tanda Tangan Basah
        if ($request->hasFile('file_skhpp')) {
            if ($submission->file_skhpp && Storage::disk('public')->exists($submission->file_skhpp)) {
                Storage::disk('public')->delete($submission->file_skhpp);
            }
            $updateData['file_skhpp'] = $request->file('file_skhpp')->store('sc_documents', 'public');
        }

        // Unggah Softfile PDF Hasil SC oleh Petugas Sintel (Masa Berlaku 2x24 Jam)
        if ($request->hasFile('file_sc_preview')) {
            if ($submission->file_sc_preview && Storage::disk('public')->exists($submission->file_sc_preview)) {
                Storage::disk('public')->delete($submission->file_sc_preview);
            }
            $uploadedPath = $request->file('file_sc_preview')->store('sc_documents', 'public');

            // Terapkan stempel fisik watermark PETINJAU secara permanen ke dalam PDF
            $watermarkedPath = \App\Services\PdfWatermarkService::applyWatermark($uploadedPath);
            $updateData['file_sc_preview'] = $watermarkedPath ?: $uploadedPath;
            $updateData['sc_preview_uploaded_at'] = now();
            $updateData['sc_preview_expired_at'] = null;
        }

        $submission->update($updateData);

        // Catat ke riwayat log
        $logNote = $validated['notes'] ?: "Berkas berhasil dimajukan ke Tahap {$newStage}: {$stageInfo['title']}.";
        if ($request->hasFile('file_sc_preview')) {
            $logNote .= " (Softfile Petinjau SC berhasil diunggah dengan masa aktif 2x24 jam).";
        }
        if ($request->hasFile('file_skhpp')) {
            $logNote .= " (Berkas SKHPP TTD Basah berhasil diunggah).";
        }

        if (Schema::hasTable('sc_submission_logs')) {
            ScSubmissionLog::create([
                'sc_submission_id' => $submission->id,
                'stage' => $newStage,
                'stage_title' => $stageInfo['title'],
                'notes' => $logNote,
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name ?? 'Petugas Kedinasan',
            ]);
        }

        // Kirim Notifikasi WhatsApp ke Pemohon saat Tahapan Diperbarui
        $targetPhone = $submission->phone;
        if (empty($targetPhone)) {
            $personel = User::where('nrp', $submission->identifier_number)->first();
            $targetPhone = $personel?->phone;
        }

        if (!empty($targetPhone)) {
            try {
                $pangkatNama = trim(($submission->pangkat_korps ? $submission->pangkat_korps . ' ' : '') . $submission->nama);
                $identitasLabel = strtoupper($submission->identifier_type ?? 'NRP');
                $trackingUrl = route('tracking-sc.index');

                $waMessage = "*UPDATE TAHAPAN SECURITY CLEARANCE (SC)*\n" .
                             "*DENINTEL KODAERAL V*\n\n" .
                             "Halo *{$pangkatNama}*,\n\n" .
                             "Status telah di update, silahkan check di link menggunakan NRP,NIP,NIK atau NIM anda.\n\n" .
                             "- *Tahap Baru:* Tahap {$newStage}: {$stageInfo['title']}\n" .
                             "- *Status:* " . strtoupper($status) . "\n" .
                             (!empty($validated['notes']) ? "- *Catatan Petugas:* {$validated['notes']}\n" : "") .
                             "- *Tautan Pengecekan:* {$trackingUrl}\n\n" .
                             "*(Masukkan {$identitasLabel}: {$submission->identifier_number} pada formulir pelacakan)*\n\n" .
                             "Demikian pemberitahuan ini disampaikan. Terima kasih.";

                WhatsappService::sendMessage($targetPhone, $waMessage);
            } catch (\Throwable $e) {
                Log::warning("Gagal mengirim notifikasi WA update tahapan SC: " . $e->getMessage());
            }
        }

        return redirect()->back()->with('success', "Lapor! Berkas {$submission->nama} berhasil diperbarui ke Tahap {$newStage}: {$stageInfo['title']}.");
    }

    /**
     * Memperbarui Data Detail Pemohon
     */
    public function update(Request $request, $id)
    {
        $submission = ScSubmission::findOrFail($id);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'pangkat_korps' => 'nullable|string|max:100',
            'identifier_type' => 'required|in:nrp,nip,nik,nim',
            'identifier_number' => 'required|string|max:50',
            'kesatuan' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'keperluan' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:proses,selesai,perbaikan,ditolak',
            'nomor_surat_rh' => 'nullable|string|max:100',
            'nomor_skhpp' => 'nullable|string|max:100',
            'nomor_sc' => 'nullable|string|max:100',
            'file_skhpp' => 'nullable|file|mimes:pdf|max:10240',
            'file_sc_preview' => 'nullable|file|mimes:pdf|max:15360',
        ]);

        if ($request->hasFile('file_skhpp')) {
            if ($submission->file_skhpp && Storage::disk('public')->exists($submission->file_skhpp)) {
                Storage::disk('public')->delete($submission->file_skhpp);
            }
            $validated['file_skhpp'] = $request->file('file_skhpp')->store('sc_documents', 'public');
        }

        if ($request->hasFile('file_sc_preview')) {
            if ($submission->file_sc_preview && Storage::disk('public')->exists($submission->file_sc_preview)) {
                Storage::disk('public')->delete($submission->file_sc_preview);
            }
            $validated['file_sc_preview'] = $request->file('file_sc_preview')->store('sc_documents', 'public');
            $validated['sc_preview_uploaded_at'] = now();
            $validated['sc_preview_expired_at'] = null;
        }

        $submission->update($validated);

        return redirect()->back()->with('success', "Lapor! Perubahan data pemohon {$submission->nama} berhasil disimpan.");
    }

    /**
     * Menghapus Berkas Pengajuan (Khusus Admin / Petugas Berwenang)
     */
    public function destroy($id)
    {
        $submission = ScSubmission::findOrFail($id);
        $nama = $submission->nama;

        if ($submission->file_skhpp && Storage::disk('public')->exists($submission->file_skhpp)) {
            Storage::disk('public')->delete($submission->file_skhpp);
        }
        if ($submission->file_sc_preview) {
            if (Storage::disk('public')->exists($submission->file_sc_preview)) {
                Storage::disk('public')->delete($submission->file_sc_preview);
            }
            $wmPath = 'sc_documents/wm_' . basename($submission->file_sc_preview);
            if (Storage::disk('public')->exists($wmPath)) {
                Storage::disk('public')->delete($wmPath);
            }
        }

        $submission->delete();

        return redirect()->back()->with('success', "Lapor! Data pengajuan SC atas nama {$nama} telah berhasil dihapus.");
    }

    /**
     * Sinkronisasi Seluruh SKHPP yang Telah Disetujui / Terbit ke Sistem Tracking SC
     */
    public function syncFromApprovedSkhpp()
    {
        $approvedSkhpps = Skhpp::where('status', 'approved')->get();
        $syncedCount = 0;

        foreach ($approvedSkhpps as $skhpp) {
            $exists = ScSubmission::where('skhpp_id', $skhpp->id)->first();
            if (!$exists) {
                $identifierType = 'nrp';
                $identifierNum = null;
                if (!empty($skhpp->pangkat_korps_nrp)) {
                    if (preg_match('/(\d{5,18})/', $skhpp->pangkat_korps_nrp, $m)) {
                        $identifierNum = $m[1];
                        $identifierType = 'nrp';
                    }
                }
                if (empty($identifierNum) && !empty($skhpp->nik)) {
                    $identifierNum = $skhpp->nik;
                    $identifierType = 'nik';
                }
                if (empty($identifierNum)) {
                    $identifierNum = 'NRP-' . $skhpp->id;
                }

                $trackingCode = 'SC-' . date('Ymd') . '-' . strtoupper(Str::random(5));
                $scSub = ScSubmission::create([
                    'skhpp_id' => $skhpp->id,
                    'tracking_code' => $trackingCode,
                    'nama' => $skhpp->nama,
                    'pangkat_korps' => $skhpp->pangkat_korps_nrp,
                    'identifier_type' => $identifierType,
                    'identifier_number' => $identifierNum,
                    'kesatuan' => $skhpp->alamat ?: 'Kodaeral V',
                    'jabatan' => $skhpp->jabatan_pekerjaan,
                    'keperluan' => $skhpp->peruntukan,
                    'nomor_skhpp' => $skhpp->nomor_skhpp,
                    'current_stage' => 5, // TAHAP 5: SKHPP TERBIT (Tahap 1, 2, 3, 4 terlewati otomatis)
                    'status' => 'proses',
                    'catatan_petugas' => "Hasil sinkronisasi otomatis dari Penerbitan SKHPP No. {$skhpp->nomor_skhpp}. Tahap 1 s/d 4 terlewati.",
                    'created_by' => Auth::id(),
                ]);

                $initialLogs = [
                    ['stage' => 1, 'stage_title' => 'Pengisian RH', 'notes' => 'Pengisian Riwayat Hidup telah diproses terintegrasi pada penerbitan SKHPP.'],
                    ['stage' => 2, 'stage_title' => 'Pengecekan Kelengkapan Dokumen', 'notes' => 'Pemeriksaan berkas dan kelengkapan dokumen telah diverifikasi oleh operator Denintel.'],
                    ['stage' => 3, 'stage_title' => 'Proses Cetak SKHPP', 'notes' => 'Proses administrasi dan pencetakan lembar SKHPP selesai.'],
                    ['stage' => 4, 'stage_title' => 'Menunggu TTD Komandan Denintel', 'notes' => 'Persetujuan dan tanda tangan dinas elektronik (TTE) Komandan Denintel telah disahkan.'],
                    ['stage' => 5, 'stage_title' => 'SKHPP Terbit', 'notes' => "SKHPP resmi disahkan dan diterbitkan dengan nomor {$skhpp->nomor_skhpp}. Berkas beralih ke Staf Intelijen."],
                ];

                foreach ($initialLogs as $logItem) {
                    ScSubmissionLog::create([
                        'sc_submission_id' => $scSub->id,
                        'stage' => $logItem['stage'],
                        'stage_title' => $logItem['stage_title'],
                        'notes' => $logItem['notes'],
                        'user_id' => Auth::id(),
                        'user_name' => Auth::user()->name ?? 'Petugas Kedinasan',
                    ]);
                }
                $syncedCount++;
            }
        }

        return redirect()->back()->with('success', "Lapor! Sebanyak {$syncedCount} berkas SKHPP yang telah terbit berhasil disinkronkan ke sistem pelacakan SC.");
    }
}
