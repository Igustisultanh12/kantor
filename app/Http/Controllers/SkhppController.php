<?php

namespace App\Http\Controllers;

use App\Models\Skhpp;
use App\Models\SkhppMember;
use App\Models\SignatureRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class SkhppController extends Controller
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
     * List Halaman Utama Pengajuan SKHPP
     */
    public function index(Request $request)
    {
        $query = Skhpp::with(['members', 'submitter', 'approver'])
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = strtolower(trim($request->search));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(nama) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(nik) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(pangkat_korps_nrp) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(nomor_skhpp) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('kategori') && $request->kategori !== 'all') {
            $query->where('kategori_personel', $request->kategori);
        }

        $skhpps = $query->paginate(15)->withQueryString();

        return Inertia::render('Skhpp/Index', [
            'skhpps' => $skhpps,
            'filters' => $request->only(['search', 'status', 'kategori'])
        ]);
    }

    /**
     * Halaman Form Pembuatan SKHPP Baru (Operator Input)
     */
    public function create()
    {
        return Inertia::render('Skhpp/Create');
    }

    /**
     * Simpan Permohonan SKHPP Baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'kategori_personel' => 'required|in:militer,sipil',
            'is_pernikahan' => 'required|boolean',
            'surat_pengantar' => 'required|string',
            'nama' => 'required|string',
            'pangkat_korps_nrp' => 'nullable|required_if:kategori_personel,militer|string',
            'nik' => 'nullable|required_if:kategori_personel,sipil|string',
            'jabatan_pekerjaan' => 'required|string',
            'tempat_lahir' => 'required|string',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'agama' => 'required|string',
            'alamat' => 'required|string',
            'peruntukan' => 'required|string',
            'foto_1' => 'required|image|mimes:jpeg,jpg,png|max:10240',
            'foto_2' => 'nullable|required_if:is_pernikahan,true|image|mimes:jpeg,jpg,png|max:10240',
            'has_pengikut' => 'boolean',
            'members' => 'nullable|array',
            'members.*.nama' => 'required|string',
            'members.*.pangkat_nrp_nik' => 'required|string',
            'members.*.jabatan' => 'required|string',
        ]);

        $foto1Path = null;
        if ($request->hasFile('foto_1')) {
            $foto1Path = $request->file('foto_1')->store('skhpp_photos', 'public');
        }

        $foto2Path = null;
        if ($request->hasFile('foto_2')) {
            $foto2Path = $request->file('foto_2')->store('skhpp_photos', 'public');
        }

        $user = auth()->user();
        $code = 'SKHPP-' . strtoupper(Str::random(10));

        $skhpp = Skhpp::create([
            'kategori_personel' => $request->kategori_personel,
            'is_pernikahan' => $request->is_pernikahan,
            'surat_pengantar' => $request->surat_pengantar,
            'nama' => $request->nama,
            'pangkat_korps_nrp' => $request->pangkat_korps_nrp,
            'nik' => $request->nik,
            'jabatan_pekerjaan' => $request->jabatan_pekerjaan,
            'tempat_lahir' => $request->tempat_lahir,
            'tanggal_lahir' => $request->tanggal_lahir,
            'jenis_kelamin' => $request->jenis_kelamin,
            'agama' => $request->agama,
            'alamat' => $request->alamat,
            'has_pengikut' => $request->has_pengikut ?? false,
            'peruntukan' => $request->peruntukan,
            'foto_1' => $foto1Path,
            'foto_2' => $foto2Path,
            'label_foto_1' => $request->is_pernikahan ? 'Foto Calon Suami' : 'Foto Utama Personel',
            'label_foto_2' => $request->is_pernikahan ? 'Foto Calon Istri' : null,
            'status' => 'pending',
            'verification_code' => $code,
            'submitted_by' => $user->id,
            'operator_name' => $user->name,
            'operator_nrp_pangkat' => ($user->pangkat ? $user->pangkat . ' ' : '') . ($user->nrp ? 'NRP. ' . $user->nrp : ''),
            'submitted_at' => now(),
        ]);

        // Simpan Anggota Pengikut jika ada
        if ($request->has_pengikut && !empty($request->members)) {
            foreach ($request->members as $idx => $m) {
                SkhppMember::create([
                    'skhpp_id' => $skhpp->id,
                    'no_urut' => $idx + 1,
                    'nama' => $m['nama'],
                    'pangkat_nrp_nik' => $m['pangkat_nrp_nik'],
                    'jabatan' => $m['jabatan'],
                ]);
            }
        }

        // Sinkronisasi ke Antrean TTD Komandan (SignatureRequest)
        SignatureRequest::create([
            'title' => "Pengajuan SKHPP - {$skhpp->nama} (" . strtoupper($skhpp->kategori_personel) . ")",
            'applicant_id' => $user->id,
            'document_path' => $foto1Path, // Path referensi
            'status' => 'pending',
            'notes' => "Peruntukan: {$skhpp->peruntukan}"
        ]);

        return redirect()->route('skhpp.index')->with('success', 'Permohonan SKHPP berhasil diterbitkan & dikirim ke Antrean TTD Komandan.');
    }

    /**
     * Tampilkan Detail SKHPP & Pratinjau PDF Cetak
     */
    public function show($id)
    {
        $skhpp = Skhpp::with(['members', 'submitter', 'approver'])->findOrFail($id);

        return Inertia::render('Skhpp/Show', [
            'skhpp' => $skhpp,
            'verificationUrl' => route('skhpp.verify', $skhpp->verification_code)
        ]);
    }

    /**
     * Halaman Edit Permohonan SKHPP (Operator Revisi Data)
     */
    public function edit($id)
    {
        $skhpp = Skhpp::with('members')->findOrFail($id);

        return Inertia::render('Skhpp/Edit', [
            'skhpp' => $skhpp
        ]);
    }

    /**
     * Simpan Perubahan & Ajukan Ulang ke TTD Komandan
     */
    public function update(Request $request, $id)
    {
        $skhpp = Skhpp::findOrFail($id);

        $request->validate([
            'kategori_personel' => 'required|in:militer,sipil',
            'is_pernikahan' => 'required|boolean',
            'surat_pengantar' => 'required|string',
            'nama' => 'required|string',
            'pangkat_korps_nrp' => 'nullable|required_if:kategori_personel,militer|string',
            'nik' => 'nullable|required_if:kategori_personel,sipil|string',
            'jabatan_pekerjaan' => 'required|string',
            'tempat_lahir' => 'required|string',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'agama' => 'required|string',
            'alamat' => 'required|string',
            'peruntukan' => 'required|string',
            'foto_1' => 'nullable|image|mimes:jpeg,jpg,png|max:10240',
            'foto_2' => 'nullable|image|mimes:jpeg,jpg,png|max:10240',
            'has_pengikut' => 'boolean',
            'members' => 'nullable|array',
            'members.*.nama' => 'required|string',
            'members.*.pangkat_nrp_nik' => 'required|string',
            'members.*.jabatan' => 'required|string',
        ]);

        if ($request->hasFile('foto_1')) {
            if ($skhpp->foto_1 && Storage::disk('public')->exists($skhpp->foto_1)) {
                Storage::disk('public')->delete($skhpp->foto_1);
            }
            $skhpp->foto_1 = $request->file('foto_1')->store('skhpp_photos', 'public');
        }

        if ($request->hasFile('foto_2')) {
            if ($skhpp->foto_2 && Storage::disk('public')->exists($skhpp->foto_2)) {
                Storage::disk('public')->delete($skhpp->foto_2);
            }
            $skhpp->foto_2 = $request->file('foto_2')->store('skhpp_photos', 'public');
        }

        $user = auth()->user();

        $skhpp->update([
            'kategori_personel' => $request->kategori_personel,
            'is_pernikahan' => $request->is_pernikahan,
            'surat_pengantar' => $request->surat_pengantar,
            'nama' => $request->nama,
            'pangkat_korps_nrp' => $request->pangkat_korps_nrp,
            'nik' => $request->nik,
            'jabatan_pekerjaan' => $request->jabatan_pekerjaan,
            'tempat_lahir' => $request->tempat_lahir,
            'tanggal_lahir' => $request->tanggal_lahir,
            'jenis_kelamin' => $request->jenis_kelamin,
            'agama' => $request->agama,
            'alamat' => $request->alamat,
            'has_pengikut' => $request->has_pengikut ?? false,
            'peruntukan' => $request->peruntukan,
            'label_foto_1' => $request->is_pernikahan ? 'Foto Calon Suami' : 'Foto Utama Personel',
            'label_foto_2' => $request->is_pernikahan ? 'Foto Calon Istri' : null,
            'status' => 'pending', // Reset kembali ke pending setelah revisi operator
            'catatan_revisi' => null,
            'submitted_by' => $user->id,
            'operator_name' => $user->name,
            'operator_nrp_pangkat' => ($user->pangkat ? $user->pangkat . ' ' : '') . ($user->nrp ? 'NRP. ' . $user->nrp : ''),
            'submitted_at' => now(),
        ]);

        // Re-sync Anggota Pengikut
        SkhppMember::where('skhpp_id', $skhpp->id)->delete();
        if ($request->has_pengikut && !empty($request->members)) {
            foreach ($request->members as $idx => $m) {
                SkhppMember::create([
                    'skhpp_id' => $skhpp->id,
                    'no_urut' => $idx + 1,
                    'nama' => $m['nama'],
                    'pangkat_nrp_nik' => $m['pangkat_nrp_nik'],
                    'jabatan' => $m['jabatan'],
                ]);
            }
        }

        return redirect()->route('skhpp.index')->with('success', 'Data SKHPP berhasil diperbarui & diajukan ulang ke TTD Komandan.');
    }

    /**
     * Persetujuan Komandan (TTD & Penomoran Fleksibel SINDEN)
     */
    public function approve(Request $request, $id)
    {
        $user = auth()->user();
        $isCommander = $user->role === 'admin' || $user->role === 'komandan' || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';

        if (!$isCommander) {
            return back()->with('error', 'Otoritas ditolak! Hanya Komandan yang berwenang menandatangani SKHPP.');
        }

        $skhpp = Skhpp::findOrFail($id);

        $currentYear = date('Y');
        $currentMonth = date('n');
        $romanMonth = $this->getRomanMonth($currentMonth);

        // Penomoran Manual / Melewati Nomor yang Terlewat
        if ($request->filled('custom_nomor_urut') && (int)$request->custom_nomor_urut > 0) {
            $nextSeq = (int)$request->custom_nomor_urut;
        } else {
            $lastSeq = Skhpp::where('tahun', $currentYear)->max('nomor_urut') ?? 0;
            $nextSeq = $lastSeq + 1;
        }

        // Format Penomoran SKHPP SINDEN
        if ($request->filled('custom_nomor_skhpp')) {
            $formattedNo = trim($request->custom_nomor_skhpp);
        } else if ($request->input('tipe_format') === 'mitra' || $skhpp->kategori_personel === 'sipil') {
            $formattedNo = "R/{$nextSeq}/SKHPP/MITRA/{$romanMonth}/{$currentYear}";
        } else {
            $formattedNo = "R/{$nextSeq}/SKHPP/{$romanMonth}/{$currentYear}";
        }

        $skhpp->update([
            'status' => 'approved',
            'nomor_skhpp' => $formattedNo,
            'nomor_urut' => $nextSeq,
            'bulan_romawi' => $romanMonth,
            'tahun' => (int)$currentYear,
            'tanggal_skhpp' => now(),
            'approved_by' => $user->id,
            'approved_at' => now(),
            'catatan_revisi' => null,
        ]);

        return back()->with('success', "SKHPP Resmi disetujui & ditandatangani Komandan! Nomor SKHPP: {$formattedNo}");
    }

    /**
     * Penolakan SKHPP / Minta Revisi
     */
    public function reject(Request $request, $id)
    {
        $user = auth()->user();
        $isCommander = $user->role === 'admin' || $user->role === 'komandan' || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';

        if (!$isCommander) {
            return back()->with('error', 'Otoritas ditolak!');
        }

        $request->validate([
            'catatan_revisi' => 'required|string'
        ]);

        $skhpp = Skhpp::findOrFail($id);
        $skhpp->update([
            'status' => 'rejected',
            'catatan_revisi' => $request->catatan_revisi
        ]);

        return back()->with('warning', 'Pengajuan SKHPP ditolak / dikembalikan untuk revisi.');
    }

    /**
     * Halaman Publik Hasil Scan QR Code TTD Komandan (Validasi Legilitas Resmi)
     */
    public function verify($code)
    {
        $skhpp = Skhpp::with(['members', 'submitter', 'approver'])
            ->where('verification_code', $code)
            ->firstOrFail();

        return Inertia::render('Skhpp/Verify', [
            'skhpp' => $skhpp
        ]);
    }

    /**
     * Hapus Data Permohonan SKHPP
     */
    public function destroy($id)
    {
        $skhpp = Skhpp::findOrFail($id);

        if ($skhpp->foto_1 && Storage::disk('public')->exists($skhpp->foto_1)) {
            Storage::disk('public')->delete($skhpp->foto_1);
        }
        if ($skhpp->foto_2 && Storage::disk('public')->exists($skhpp->foto_2)) {
            Storage::disk('public')->delete($skhpp->foto_2);
        }

        $skhpp->delete();

        return back()->with('success', 'Data permohonan SKHPP berhasil dihapus.');
    }
}
