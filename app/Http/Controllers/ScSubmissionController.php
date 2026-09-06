<?php

namespace App\Http\Controllers;

use App\Models\ScSubmission;
use App\Models\ScSubmissionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ScSubmissionController extends Controller
{
    /**
     * Daftar Berkas Pengajuan SC untuk Admin & Petugas
     */
    public function index(Request $request)
    {
        $query = ScSubmission::with(['logs', 'creator'])->orderBy('id', 'desc');

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
     * Mendaftarkan Pengajuan SC Baru oleh Petugas
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
        ]);

        $stage = (int)($validated['current_stage'] ?? 1);
        $stageInfo = ScSubmission::STAGES[$stage] ?? ScSubmission::STAGES[1];

        // Format kode tracking: SC-YYYYMMDD-XXXX
        $trackingCode = 'SC-' . date('Ymd') . '-' . strtoupper(Str::random(5));

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
     * Memperbarui Tahapan Operasional Berkas SC
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

        $submission->update($updateData);

        // Catat ke riwayat log
        ScSubmissionLog::create([
            'sc_submission_id' => $submission->id,
            'stage' => $newStage,
            'stage_title' => $stageInfo['title'],
            'notes' => $validated['notes'] ?: "Berkas berhasil dimajukan ke Tahap {$newStage}: {$stageInfo['title']}.",
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
        ]);

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
        $submission->delete();

        return redirect()->back()->with('success', "Lapor! Data pengajuan SC atas nama {$nama} telah berhasil dihapus.");
    }
}
