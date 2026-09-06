<?php

namespace App\Http\Controllers;

use App\Models\ScSubmission;
use App\Models\ScSubmissionLog;
use App\Models\Skhpp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        // Bersihkan pratinjau yang telah melewati batas 2x24 jam (48 jam)
        $withPreviews = ScSubmission::whereNotNull('file_sc_preview')->get();
        foreach ($withPreviews as $subItem) {
            $subItem->checkAndPurgeExpiredPreview();
        }

        $query = ScSubmission::with(['logs', 'creator', 'skhpp'])->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $cleanSearch = str_replace([' ', '-', '.'], '', $search);
            $query->where(function ($q) use ($search, $cleanSearch) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('identifier_number', 'like', "%{$search}%")
                  ->orWhereRaw("REPLACE(REPLACE(REPLACE(identifier_number, ' ', ''), '-', ''), '.', '') LIKE ?", ["%{$cleanSearch}%"])
                  ->orWhere('tracking_code', 'like', "%{$search}%")
                  ->orWhere('kesatuan', 'like', "%{$search}%")
                  ->orWhere('nomor_sc', 'like', "%{$search}%");
            });
        }

        if ($request->filled('stage') && $request->stage !== 'all') {
            $query->where('current_stage', (int)$request->stage);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $submissions = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => ScSubmission::count(),
            'in_progress' => ScSubmission::where('current_stage', '<', 10)->where('status', '!=', 'ditolak')->count(),
            'at_denintel' => ScSubmission::where('current_stage', '<=', 5)->where('status', '!=', 'ditolak')->count(),
            'at_sintel' => ScSubmission::whereBetween('current_stage', [6, 9])->where('status', '!=', 'ditolak')->count(),
            'completed' => ScSubmission::where('current_stage', 10)->orWhere('status', 'selesai')->count(),
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
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'pangkat_korps' => 'nullable|string|max:100',
            'identifier_type' => 'required|in:nrp,nip,nik',
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

        $stage = (int)($validated['current_stage'] ?? 1);
        $stageInfo = ScSubmission::STAGES[$stage] ?? ScSubmission::STAGES[1];

        // Format kode tracking: SC-YYYYMMDD-XXXXX
        $trackingCode = 'SC-' . date('Ymd') . '-' . strtoupper(Str::random(5));

        // Penanganan Unggah PDF SKHPP Tanda Tangan Basah
        $fileSkhppPath = null;
        if ($request->hasFile('file_skhpp')) {
            $fileSkhppPath = $request->file('file_skhpp')->store('sc_documents', 'public');
        }

        $submission = ScSubmission::create([
            'tracking_code' => $trackingCode,
            'nama' => $validated['nama'],
            'pangkat_korps' => $validated['pangkat_korps'] ?? null,
            'identifier_type' => $validated['identifier_type'],
            'identifier_number' => trim($validated['identifier_number']),
            'kesatuan' => $validated['kesatuan'] ?? null,
            'jabatan' => $validated['jabatan'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'keperluan' => $validated['keperluan'] ?? null,
            'current_stage' => $stage,
            'status' => $stage === 10 ? 'selesai' : ($validated['status'] ?? 'proses'),
            'catatan_petugas' => $validated['catatan_petugas'] ?? null,
            'nomor_surat_rh' => $validated['nomor_surat_rh'] ?? null,
            'nomor_skhpp' => $validated['nomor_skhpp'] ?? null,
            'file_skhpp' => $fileSkhppPath,
            'nomor_sc' => $validated['nomor_sc'] ?? null,
            'created_by' => Auth::id(),
        ]);

        // Catat riwayat log inisialisasi
        ScSubmissionLog::create([
            'sc_submission_id' => $submission->id,
            'stage' => $stage,
            'stage_title' => $stageInfo['title'],
            'notes' => $validated['catatan_petugas'] ?: 'Pendaftaran pengajuan berkas Security Clearance di sistem.',
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name ?? 'Petugas Kedinasan',
        ]);

        return redirect()->back()->with('success', "Lapor! Pengajuan Security Clearance atas nama {$submission->nama} berhasil didaftarkan dengan Kode Tracking {$trackingCode}.");
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
            $updateData['file_sc_preview'] = $request->file('file_sc_preview')->store('sc_documents', 'public');
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

        ScSubmissionLog::create([
            'sc_submission_id' => $submission->id,
            'stage' => $newStage,
            'stage_title' => $stageInfo['title'],
            'notes' => $logNote,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name ?? 'Petugas Kedinasan',
        ]);

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
            'identifier_type' => 'required|in:nrp,nip,nik',
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
        if ($submission->file_sc_preview && Storage::disk('public')->exists($submission->file_sc_preview)) {
            Storage::disk('public')->delete($submission->file_sc_preview);
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
                    ['stage' => 3, 'stage_title' => 'Proses Cetak RH', 'notes' => 'Proses administrasi dan pencetakan lembar SKHPP selesai.'],
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
