<?php

namespace App\Http\Controllers;

use App\Models\Skhpp;
use App\Models\SkhppMember;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Models\AppNotification;
use App\Services\WhatsappService;
use App\Support\WaktuTte;
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
        return Inertia::render('Skhpp/Create', ['agendaSurat' => $this->agendaSurat()]);
    }

    /**
     * Kategori & Jenis Surat di Agenda Surat tempat SKHPP dicatat saat disahkan
     * (SKHPP-D untuk dinas, SKHPP-P untuk mitra kerja) — ditampilkan di form.
     */
    /**
     * Kolom penomoran draf dari nomor urut manual di form ("120" / "120a").
     * Kosong = semua dikosongkan supaya saat disahkan memakai nomor otomatis berikutnya.
     */
    private function nomorDraf(?string $manual, bool $tulisTangan = false): array
    {
        if ($tulisTangan) {
            // Nomor urut dikosongkan di dokumen untuk ditulis tangan; bulan & tahun tetap tercetak.
            return ['nomor_tulis_tangan' => true, 'nomor_manual' => null, 'nomor_skhpp' => null, 'nomor_urut' => null,
                'bulan_romawi' => $this->getRomanMonth(date('n')), 'tahun' => (int) date('Y')];
        }
        if (!filled($manual)) {
            return ['nomor_manual' => null, 'nomor_skhpp' => null, 'nomor_urut' => null];
        }
        $roman = $this->getRomanMonth(date('n'));
        return [
            'nomor_manual' => $manual,
            'nomor_skhpp' => "R / {$manual} / SKHPP / {$roman} / " . date('Y'),
            'nomor_urut' => (int) $manual,
            'bulan_romawi' => $roman,
            'tahun' => (int) date('Y'),
        ];
    }

    /** Teks nomor untuk pesan/notifikasi bila nomornya ditulis tangan. */
    private function labelTulisTangan(string $roman, int $tahun): string
    {
        return "R / … / SKHPP / {$roman} / {$tahun} (nomor ditulis tangan)";
    }

    private function agendaSurat(): array
    {
        $cats = \App\Models\Category::with('subCategories')->whereIn('code', ['SKHPP-D', 'SKHPP-P'])->get()->keyBy('code');
        $out = [];
        foreach (['dinas' => ['SKHPP-D', 'SKHPP Dinas Militer & PNS'], 'perusahaan' => ['SKHPP-P', 'SKHPP Mitra Kerja / Perusahaan']] as $format => [$code, $default]) {
            $cat = $cats->get($code);
            $out[$format] = [
                'kode' => $code,
                'nama' => $cat->name ?? $default,
                'jenis' => $cat ? $cat->subCategories->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values() : [],
            ];
        }
        return $out;
    }

    /**
     * Simpan Permohonan SKHPP Baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'kategori_personel' => 'required|in:militer,sipil_dinas,perusahaan',
            'is_pernikahan' => 'required|boolean',
            'surat_pengantar' => 'required|string',
            'nama' => 'required|string',
            'pangkat_korps_nrp' => 'nullable|string',
            'nik' => 'nullable|string',
            'jenis_identitas' => 'nullable|in:NRP,NIP,NIM,NIK',
            'letter_sub_category_id' => 'nullable|integer|exists:sub_categories,id',
            'nomor_manual' => ['nullable', 'regex:/^\d{1,6}[A-Za-z]{0,2}$/'], // hanya nomor urut, boleh berhuruf: 120 / 120a
            'ttd_mode' => 'nullable|in:tte,manual', // pilihan di form: TTE (QR) atau TTD manual (tanda tangan basah)
            'jabatan_pekerjaan' => 'required|string',
            'tempat_lahir' => 'required|string',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'agama' => 'required|string',
            'alamat' => 'required|string',
            'peruntukan' => 'required|string',
            'nomor_tulis_tangan' => 'nullable|boolean',
            'foto_1' => 'required|image|mimes:jpeg,jpg,png|max:10240',
            'foto_2' => 'nullable|required_if:is_pernikahan,true|image|mimes:jpeg,jpg,png|max:10240',
            'has_pengikut' => 'boolean',
            'satuan' => 'nullable|required_if:has_pengikut,true,1|string|max:150',
            'kegiatan' => 'nullable|string|max:255',
            'members' => 'nullable|array',
            'members.*.nama' => 'required|string',
            'members.*.pangkat_nrp_nik' => 'required|string',
            'members.*.jabatan' => 'required|string',
            'is_mitra_pks' => 'nullable|boolean',
            'members.*.usaha' => 'nullable|string|max:150',
            'members.*.lokasi_usaha' => 'nullable|string|max:255',
        ]);

        // Mitra / PKS hanya berlaku untuk SKHPP-P (perusahaan).
        $mitraPks = $request->kategori_personel === 'perusahaan' && $request->boolean('is_mitra_pks');

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
            'is_mitra_pks' => $mitraPks,
            'surat_pengantar' => $request->surat_pengantar,
            'nama' => $request->nama,
            'pangkat_korps_nrp' => $request->pangkat_korps_nrp,
            'nik' => $request->nik,
            'jenis_identitas' => $request->jenis_identitas,
            'letter_sub_category_id' => $request->letter_sub_category_id,
            'jabatan_pekerjaan' => $request->jabatan_pekerjaan,
            'tempat_lahir' => $request->tempat_lahir,
            'tanggal_lahir' => $request->tanggal_lahir,
            'jenis_kelamin' => $request->jenis_kelamin,
            'agama' => $request->agama,
            'alamat' => $request->alamat,
            'has_pengikut' => $request->has_pengikut ?? false,
            'satuan' => $request->satuan,
            'kegiatan' => $request->kegiatan,
            'peruntukan' => $request->peruntukan,
            'foto_1' => $foto1Path,
            'foto_2' => $foto2Path,
            'label_foto_1' => $request->is_pernikahan ? 'Foto Calon Suami' : 'Foto Utama Personel',
            'label_foto_2' => $request->is_pernikahan ? 'Foto Calon Istri' : null,
            // Nomor urut manual (mis. 120 / 120a) langsung dibooking di draf; dipakai lagi saat disahkan (lihat approve()).
            'nomor_tulis_tangan' => false,
            ...$this->nomorDraf($request->nomor_manual, $request->boolean('nomor_tulis_tangan')),
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
                    'usaha' => $mitraPks ? ($m['usaha'] ?? null) : null,
                    'lokasi_usaha' => $mitraPks ? ($m['lokasi_usaha'] ?? null) : null,
                ]);
            }
        }

        // Dipilih TTD manual di form: nomor langsung dibooking untuk dicetak; tidak masuk antrean TTE Komandan.
        if ($request->ttd_mode === 'manual') {
            $nomor = $this->bookingTtdManual($skhpp, $request);
            return redirect()->route('skhpp.index')->with('success', "Permohonan SKHPP tersimpan untuk TTD manual dengan nomor {$nomor}. Cetak, mintakan tanda tangan Komandan, lalu unggah hasilnya.");
        }

        // TTE: nomor langsung dibooking & tercatat di Agenda Surat selama menunggu TTE Komandan.
        $this->bookingTte($skhpp, $request);

        // Sinkronisasi ke Antrean TTD Komandan (SignatureRequest)
        try {
            SignatureRequest::create([
                'user_id' => $user->id,
                'subject' => "Pengajuan SKHPP - {$skhpp->nama} (" . strtoupper(str_replace('_', ' ', $skhpp->kategori_personel)) . ")",
                'letter_number' => "DRAFT SKHPP #" . $skhpp->id,
                'file_path' => $foto1Path,
                'status' => 'pending',
                'note' => "Peruntukan: {$skhpp->peruntukan}"
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal auto-sync SKHPP ke SignatureRequest: " . $e->getMessage());
        }

        // Kirim Notifikasi Sistem In-App Bell & WA ke Komandan & Admin
        try {
            $komandan = User::where('role', 'komandan')->whereNotNull('phone')->first() 
                     ?? User::where('role', 'admin')->whereNotNull('phone')->first();
            $katName = ($skhpp->kategori_personel === 'perusahaan') ? 'SKHPP-P (Mitra Kerja/Perusahaan)' : 'SKHPP-D (Dinas Militer & PNS)';
            $directUrl = url('/signature-requests?open_skhpp=' . $skhpp->id . '&tab=skhpp');
            $pesanKomandan = " *SI SINDEN: PENGAJUAN SKHPP BARU*\n\n" .
                             "Mohon izin Komandan, terdapat pengajuan penerbitan SKHPP baru:\n\n" .
                             " *Nama:* {$skhpp->nama}\n" .
                             " *Pangkat/NRP/NIK:* " . ($skhpp->pangkat_korps_nrp ?: ($skhpp->nik ?: '-')) . "\n" .
                             " *Kategori:* {$katName}\n" .
                             " *Peruntukan:* {$skhpp->peruntukan}\n" .
                             " *Operator Pengaju:* {$user->name}\n\n" .
                             "Mohon izin untuk memeriksa & menyetujui berkas langsung di: {$directUrl}";

            AppNotification::notify(
                $komandan?->id,
                'komandan',
                'Pengajuan SKHPP Baru',
                "Pengajuan SKHPP baru atas nama {$skhpp->nama} ({$katName}) oleh {$user->name}.",
                'primary',
                '/signature-requests?open_skhpp=' . $skhpp->id . '&tab=skhpp',
                $pesanKomandan,
                $komandan?->phone
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal kirim notif SKHPP baru: " . $e->getMessage());
        }

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
        $this->authorizeSkhppEdit($skhpp);

        return Inertia::render('Skhpp/Edit', [
            'skhpp' => $skhpp,
            'agendaSurat' => $this->agendaSurat(),
        ]);
    }

    /**
     * Simpan Perubahan & Ajukan Ulang ke TTD Komandan
     */
    public function update(Request $request, $id)
    {
        $skhpp = Skhpp::findOrFail($id);
        $this->authorizeSkhppEdit($skhpp);

        $request->validate([
            'kategori_personel' => 'required|in:militer,sipil_dinas,perusahaan',
            'is_pernikahan' => 'required|boolean',
            'surat_pengantar' => 'required|string',
            'nama' => 'required|string',
            'pangkat_korps_nrp' => 'nullable|string',
            'nik' => 'nullable|string',
            'jenis_identitas' => 'nullable|in:NRP,NIP,NIM,NIK',
            'letter_sub_category_id' => 'nullable|integer|exists:sub_categories,id',
            'nomor_manual' => ['nullable', 'regex:/^\d{1,6}[A-Za-z]{0,2}$/'], // hanya nomor urut, boleh berhuruf: 120 / 120a
            'ttd_mode' => 'nullable|in:tte,manual', // pilihan di form: TTE (QR) atau TTD manual (tanda tangan basah)
            'jabatan_pekerjaan' => 'required|string',
            'tempat_lahir' => 'required|string',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'agama' => 'required|string',
            'alamat' => 'required|string',
            'peruntukan' => 'required|string',
            'nomor_tulis_tangan' => 'nullable|boolean',
            'foto_1' => 'nullable|image|mimes:jpeg,jpg,png|max:10240',
            'foto_2' => 'nullable|image|mimes:jpeg,jpg,png|max:10240',
            'has_pengikut' => 'boolean',
            'satuan' => 'nullable|required_if:has_pengikut,true,1|string|max:150',
            'kegiatan' => 'nullable|string|max:255',
            'members' => 'nullable|array',
            'members.*.nama' => 'required|string',
            'members.*.pangkat_nrp_nik' => 'required|string',
            'members.*.jabatan' => 'required|string',
            'is_mitra_pks' => 'nullable|boolean',
            'members.*.usaha' => 'nullable|string|max:150',
            'members.*.lokasi_usaha' => 'nullable|string|max:255',
        ]);

        // Mitra / PKS hanya berlaku untuk SKHPP-P; permintaan tanpa isian ini (aplikasi lama) mempertahankan nilai tersimpan.
        $mitraPks = $request->kategori_personel === 'perusahaan'
            && ($request->has('is_mitra_pks') ? $request->boolean('is_mitra_pks') : (bool) $skhpp->is_mitra_pks);

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

        // Nomor yang sudah dibooking di Agenda Surat untuk SKHPP ini (TTE/TTD manual), dicatat sebelum nomornya berubah.
        $nomorAgendaLama = $skhpp->nomor_skhpp;

        // Nomor urut manual diubah/dihapus: nomor draf ikut (dihapus = kembali ke penomoran otomatis).
        // Centang "nomor ditulis tangan" diubah juga membuat nomor draf disusun ulang.
        $tulisTangan = $request->has('nomor_tulis_tangan') ? $request->boolean('nomor_tulis_tangan') : (bool) $skhpp->nomor_tulis_tangan;
        if ($tulisTangan !== (bool) $skhpp->nomor_tulis_tangan || (!$tulisTangan && $request->nomor_manual !== $skhpp->nomor_manual)) {
            $skhpp->fill(['nomor_tulis_tangan' => false, ...$this->nomorDraf($request->nomor_manual, $tulisTangan)]);
        }

        $skhpp->update([
            'kategori_personel' => $request->kategori_personel,
            'is_pernikahan' => $request->is_pernikahan,
            'is_mitra_pks' => $mitraPks,
            'surat_pengantar' => $request->surat_pengantar,
            'nama' => $request->nama,
            'pangkat_korps_nrp' => $request->pangkat_korps_nrp,
            'nik' => $request->nik,
            'jenis_identitas' => $request->jenis_identitas,
            'letter_sub_category_id' => $request->letter_sub_category_id,
            'jabatan_pekerjaan' => $request->jabatan_pekerjaan,
            'tempat_lahir' => $request->tempat_lahir,
            'tanggal_lahir' => $request->tanggal_lahir,
            'jenis_kelamin' => $request->jenis_kelamin,
            'agama' => $request->agama,
            'alamat' => $request->alamat,
            'has_pengikut' => $request->has_pengikut ?? false,
            'satuan' => $request->satuan,
            'kegiatan' => $request->kegiatan,
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
                    'usaha' => $mitraPks ? ($m['usaha'] ?? null) : null,
                    'lokasi_usaha' => $mitraPks ? ($m['lokasi_usaha'] ?? null) : null,
                ]);
            }
        }

        // Pilihan TTE / TTD manual di form Revisi (permintaan tanpa ttd_mode, mis. dari aplikasi lama, tidak mengubah apa pun).
        if ($request->ttd_mode === 'manual') {
            $nomor = $this->bookingTtdManual($skhpp, $request, $nomorAgendaLama);
            return redirect()->route('skhpp.index')->with('success', "Data SKHPP diperbarui untuk TTD manual dengan nomor {$nomor}. Cetak, mintakan tanda tangan Komandan, lalu unggah hasilnya.");
        }
        if ($request->ttd_mode === 'tte' && $skhpp->ttd_mode === 'manual') {
            $skhpp->update(['ttd_mode' => null]);
        }
        // Jalur TTE: nomor (tetap/baru) dibooking ulang di Agenda Surat; entri lama diperbarui, bukan dobel.
        if ($skhpp->ttd_mode !== 'manual') {
            $this->bookingTte($skhpp, $request, $nomorAgendaLama);
        }

        // Kirim Notifikasi WA ke Komandan
        try {
            $komandan = User::where('role', 'komandan')->whereNotNull('phone')->first() 
                     ?? User::where('role', 'admin')->whereNotNull('phone')->first();
            if ($komandan && $komandan->phone) {
                $katName = ($skhpp->kategori_personel === 'perusahaan') ? 'SKHPP-P (Mitra Kerja/Perusahaan)' : 'SKHPP-D (Dinas Militer & PNS)';
                $directUrl = url('/signature-requests?open_skhpp=' . $skhpp->id . '&tab=skhpp');
                $pesanKomandan = " *SI SINDEN: PERBAIKAN & PENGAJUAN ULANG SKHPP*\n\n" .
                                 "Mohon izin Komandan, terdapat perbaikan data SKHPP oleh Operator yang diajukan ulang:\n\n" .
                                 " *Nama:* {$skhpp->nama}\n" .
                                 " *Pangkat/NRP/NIK:* " . ($skhpp->pangkat_korps_nrp ?: ($skhpp->nik ?: '-')) . "\n" .
                                 " *Kategori:* {$katName}\n" .
                                 " *Peruntukan:* {$skhpp->peruntukan}\n" .
                                 " *Operator:* {$user->name}\n\n" .
                                 "Mohon izin untuk memeriksa & menyetujui berkas langsung di: {$directUrl}";

                WhatsappService::sendMessage($komandan->phone, $pesanKomandan);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal kirim WA notif Komandan perbaikan SKHPP: " . $e->getMessage());
        }

        return redirect()->route('skhpp.index')->with('success', 'Data SKHPP berhasil diperbarui & diajukan ulang ke TTD Komandan.');
    }

    /**
     * Persetujuan Komandan (TTD & Penomoran Terintegrasi Buku Agenda SINDEN)
     */
    public function approve(Request $request, $id)
    {
        $user = auth()->user();
        $isCommander = \App\Support\MobileAccess::canApproveAsCommander($user);

        if (!$isCommander) {
            return back()->with('error', 'Otoritas ditolak! Hanya Komandan yang berwenang menandatangani SKHPP.');
        }

        // TTE Komandan: mode TTD manual (bila sebelumnya diajukan) dibatalkan agar QR tercetak.
        $formattedNo = $this->terbitkan(Skhpp::findOrFail($id), $request, ['ttd_mode' => null]);

        return back()->with('success', "SKHPP Resmi disetujui Komandan! Nomor: {$formattedNo} (Tersinkron ke Buku Nomor Agenda)");
    }

    /**
     * Penomoran SKHPP (dipakai pengesahan TTE, pengajuan TTD manual, dan unggah TTD manual).
     * @return array{0:int,1:string,2:string,3:string,4:\App\Models\Category,5:string,6:int} [urut, label urut, nomor lengkap, prioritas, kategori agenda, bulan romawi, tahun]
     */
    private function nomorResmi(Skhpp $skhpp, Request $request): array
    {
        $currentYear = date('Y');
        $currentMonth = date('n');
        $romanMonth = $this->getRomanMonth($currentMonth);

        $isPerusahaan = ($skhpp->kategori_personel === 'perusahaan');
        $targetCode = $isPerusahaan ? 'SKHPP-P' : 'SKHPP-D';
        $categoryName = $isPerusahaan
            ? 'SKHPP Mitra Kerja / Perusahaan'
            : 'SKHPP Dinas Militer & PNS';

        $skhppCategory = \App\Models\Category::firstOrCreate(
            ['code' => $targetCode],
            ['name' => $categoryName, 'start_number' => 1]
        );

        // TTD MANUAL: nomor yang sudah tercetak & ditandatangani di kertas tidak boleh berubah lagi.
        if ($skhpp->ttd_mode === 'manual' && $skhpp->nomor_skhpp && (int)$skhpp->nomor_urut > 0) {
            $label = ($skhpp->nomor_manual && (int)$skhpp->nomor_manual === (int)$skhpp->nomor_urut) ? $skhpp->nomor_manual : (string)$skhpp->nomor_urut;
            return [(int)$skhpp->nomor_urut, $label, $skhpp->nomor_skhpp, trim(explode('/', $skhpp->nomor_skhpp)[0]) ?: 'R', $skhppCategory, $skhpp->bulan_romawi ?: $romanMonth, (int)($skhpp->tahun ?: $currentYear)];
        }

        // PENOMORAN TERPISAH KATEGORI (SKHPP-P & SKHPP-D INDEPENDEN) & PERTAHANKAN NOMOR SAAT PENGAJUAN ULANG
        if ($request->filled('custom_nomor_urut') && (int)$request->custom_nomor_urut > 0) {
            $nextSeq = (int)$request->custom_nomor_urut;
        } elseif (!empty($skhpp->nomor_urut) && (int)$skhpp->nomor_urut > 0) {
            // PERTAHANKAN NOMOR LAMA JIKA SUDAH ADA / DIATUR ADMIN SEBELUMNYA
            $nextSeq = (int)$skhpp->nomor_urut;
        } else {
            $seqQuery = Skhpp::where('tahun', $currentYear);
            if ($isPerusahaan) {
                $seqQuery->where('kategori_personel', 'perusahaan');
            } else {
                $seqQuery->where('kategori_personel', '!=', 'perusahaan');
            }
            $lastSeq = $seqQuery->max('nomor_urut') ?? 0;
            $nextSeq = $lastSeq + 1;
        }

        // Nomor urut manual dari form boleh berhuruf (120a): hurufnya dipertahankan selama angkanya masih sama.
        $seqLabel = ($skhpp->nomor_manual && (int)$skhpp->nomor_manual === $nextSeq) ? $skhpp->nomor_manual : (string)$nextSeq;

        $priority = $request->input('priority', 'R'); // R (RAHASIA), B (BIASA), K (KILAT)

        $cleanCode = explode('-', $skhppCategory->code)[0]; // 'SKHPP'

        if ($request->filled('custom_nomor_skhpp')) {
            $formattedNo = trim($request->custom_nomor_skhpp);
        } else {
            // EXACT FORMAT: {$priority} / {$sequence} / {$cleanCode} / {$romanMonth} / {$year}
            $formattedNo = "{$priority} / {$seqLabel} / {$cleanCode} / {$romanMonth} / {$currentYear}";
        }

        return [$nextSeq, $seqLabel, $formattedNo, $priority, $skhppCategory, $romanMonth, (int)$currentYear];
    }

    /**
     * Sahkan SKHPP: beri nomor, tandai terbit, lalu sinkron ke Agenda Surat, Tracking SC, dan notifikasi.
     * $extra menimpa kolom bawaan (mis. data TTD manual). Mengembalikan nomor lengkap.
     */
    private function terbitkan(Skhpp $skhpp, Request $request, array $extra = []): string
    {
        $user = auth()->user();
        $nomorSebelum = $skhpp->nomor_skhpp; // nomor yang dibooking saat pengajuan (bila ada)

        // Nomor tulis tangan yang sudah diisikan saat unggah TTD manual diperlakukan seperti nomor biasa.
        $tulisTanganKosong = $skhpp->nomor_tulis_tangan && !$skhpp->nomor_skhpp;

        if ($tulisTanganKosong) {
            // Nomor ditulis tangan di kertas: tidak ada nomor sistem & tidak dicatat di Agenda Surat.
            $this->lepasNomorAgenda($nomorSebelum);
            $nomorKolom = $this->nomorDraf(null, true);
            $formattedNo = $this->labelTulisTangan($nomorKolom['bulan_romawi'], $nomorKolom['tahun']);
        } else {
            [$nextSeq, $seqLabel, $formattedNo, $priority, $skhppCategory, $romanMonth, $currentYear] = $this->nomorResmi($skhpp, $request);
            $nomorKolom = [
                'nomor_skhpp' => $formattedNo,
                'nomor_urut' => $nextSeq,
                'bulan_romawi' => $romanMonth,
                'tahun' => (int)$currentYear,
            ];
        }

        $skhpp->update(array_merge([
            'status' => 'approved',
            ...$nomorKolom,
            'tanggal_skhpp' => now(),
            'approved_by' => $user->id,
            'approved_at' => now(),
            'catatan_revisi' => null,
        ], $extra));

        // SINKRONISASI OTOMATIS KE BUKU AGENDA SURAT SINDEN (LETTER_LOGS)
        // Nomor yang dibooking saat pengajuan diperbarui (bukan dobel) bila nomornya berubah saat disahkan.
        if (!$tulisTanganKosong) {
            $this->catatAgenda($skhpp, $formattedNo, $seqLabel, $priority, $skhppCategory, $nomorSebelum);
            // Disahkan lewat TTE (bukan unggah TTD manual): naskah ber-QR langsung jadi arsip.
            if (!array_key_exists('signed_file', $extra)) {
                $this->arsipkanTte($skhpp);
            }
        }

        // SINKRONISASI OTOMATIS KE FITUR TRACKING PENGAJUAN SECURITY CLEARANCE (SC)
        // Tahap 1 (Pengisian RH), 2 (Pengecekan Dokumen), 3 (Cetak SKHPP), dan 4 (Menunggu TTD) otomatis terlewati!
        try {
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

            $scSub = \App\Models\ScSubmission::where('skhpp_id', $skhpp->id)->first();
            if (!$scSub) {
                $trackingCode = 'SC-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(5));
                $scSub = \App\Models\ScSubmission::create([
                    'skhpp_id' => $skhpp->id,
                    'tracking_code' => $trackingCode,
                    'nama' => $skhpp->nama,
                    'pangkat_korps' => $skhpp->pangkat_korps_nrp,
                    'identifier_type' => $identifierType,
                    'identifier_number' => $identifierNum,
                    'kesatuan' => $skhpp->alamat ?: 'Kodaeral V',
                    'jabatan' => $skhpp->jabatan_pekerjaan,
                    'keperluan' => $skhpp->peruntukan,
                    'nomor_skhpp' => $formattedNo,
                    'current_stage' => 5, // TAHAP 5: SKHPP TERBIT (Tahap 1, 2, 3, 4 terlewati otomatis)
                    'status' => 'proses',
                    'catatan_petugas' => "SKHPP resmi diterbitkan oleh Komandan Denintel (TTE) No. {$formattedNo}. Tahap 1 s/d 4 otomatis terlewati.",
                    'created_by' => $user->id,
                ]);

                $initialLogs = [
                    ['stage' => 1, 'stage_title' => 'Pengisian RH', 'notes' => 'Pengisian Riwayat Hidup telah diproses terintegrasi pada penerbitan SKHPP.'],
                    ['stage' => 2, 'stage_title' => 'Pengecekan Kelengkapan Dokumen', 'notes' => 'Pemeriksaan berkas dan kelengkapan dokumen telah diverifikasi oleh operator Denintel.'],
                    ['stage' => 3, 'stage_title' => 'Proses Cetak SKHPP', 'notes' => 'Proses administrasi dan pencetakan lembar SKHPP selesai.'],
                    ['stage' => 4, 'stage_title' => 'Menunggu TTD Komandan Denintel', 'notes' => 'Persetujuan dan tanda tangan dinas elektronik (TTE) Komandan Denintel telah disahkan.'],
                    ['stage' => 5, 'stage_title' => 'SKHPP Terbit', 'notes' => "SKHPP resmi disahkan dan diterbitkan dengan nomor {$formattedNo}. Berkas beralih ke Staf Intelijen."],
                ];

                foreach ($initialLogs as $logItem) {
                    \App\Models\ScSubmissionLog::create([
                        'sc_submission_id' => $scSub->id,
                        'stage' => $logItem['stage'],
                        'stage_title' => $logItem['stage_title'],
                        'notes' => $logItem['notes'],
                        'user_id' => $user->id,
                        'user_name' => $user->name ?? 'Komandan Denintel',
                    ]);
                }
            } else {
                $scSub->update([
                    'nomor_skhpp' => $formattedNo,
                    'current_stage' => max(5, $scSub->current_stage),
                    'catatan_petugas' => "Pembaruan nomor SKHPP: {$formattedNo} oleh Komandan Denintel.",
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal auto-sync SKHPP ke ScSubmission: " . $e->getMessage());
        }

        // Kirim Notifikasi Sistem In-App Bell & WA ke Operator / Pengaju
        try {
            $operator = User::find($skhpp->submitted_by);
            $katName = ($skhpp->kategori_personel === 'perusahaan') ? 'SKHPP-P' : 'SKHPP-D';
            $pesanOperator = " *SI SINDEN: SKHPP RESMI DISAHKAN & DITANDATANGANI*\n\n" .
                             "Laporan untuk Operator/Pengaju, SKHPP telah disetujui & ditandatangani Komandan:\n\n" .
                             " *Nama:* {$skhpp->nama}\n" .
                             " *Nomor SKHPP:* {$formattedNo}\n" .
                             " *Kategori:* {$katName}\n\n" .
                             "Dokumen resmi & QR Code TTD sudah terbit dan dapat diunduh di Laman : https://sisinden.my.id/skhpp";

            AppNotification::notify(
                $skhpp->submitted_by,
                null,
                'SKHPP Resmi Disahkan & TTD',
                "SKHPP atas nama {$skhpp->nama} telah disetujui & ditandatangani Komandan (Nomor: {$formattedNo}).",
                'success',
                '/skhpp',
                $pesanOperator,
                $operator?->phone
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal kirim notif SKHPP disetujui: " . $e->getMessage());
        }

        return $formattedNo;
    }

    /**
     * Catat (atau perbarui) nomor SKHPP di Agenda Surat. Entri baru berstatus "belum ada PDF"
     * (is_archived = false) sampai arsipnya diunggah. $nomorLama: nomor sebelumnya bila nomornya berubah,
     * supaya entri lama diperbarui dan tidak tertinggal sebagai nomor ganda.
     */
    private function catatAgenda(Skhpp $skhpp, string $formattedNo, string $seqLabel, string $priority, \App\Models\Category $category, ?string $nomorLama = null): ?\App\Models\LetterLog
    {
        try {
            $log = \App\Models\LetterLog::where('category_id', $category->id)
                ->whereIn('full_number', array_filter([$formattedNo, $nomorLama]))
                ->orderByRaw('full_number = ? desc', [$formattedNo])
                ->first() ?? new \App\Models\LetterLog(['is_archived' => false, 'user_id' => auth()->id()]);

            $log->fill([
                'full_number' => $formattedNo,
                'sequence' => $seqLabel,
                'priority' => $priority,
                'category_id' => $category->id,
                // Jenis Surat yang dipilih di form SKHPP (hanya bila memang milik kategori ini)
                'sub_category_id' => $skhpp->letter_sub_category_id
                    ? \App\Models\LetterSubCategory::where('id', $skhpp->letter_sub_category_id)->where('category_id', $category->id)->value('id')
                    : null,
                'subject' => "SKHPP - {$skhpp->nama} (" . strtoupper($skhpp->kategori_personel) . ") - " . $skhpp->peruntukan,
                'recipient' => 'Yth. Asintel Dankodaeral V',
                'date' => ($skhpp->tanggal_skhpp ?? now())->toDateString(),
            ])->save();

            return $log;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal auto-sync SKHPP ke LetterLog: " . $e->getMessage());
            return null;
        }
    }

    /**
     * SKHPP bertanda tangan manual diunggah: simpan juga sebagai arsip surat keluar dan
     * tandai entri Agenda Surat-nya sudah ber-PDF (sama seperti unggah PDF dari Agenda Surat).
     */
    private function arsipkanTtdManual(Skhpp $skhpp, string $signedPath): void
    {
        $this->arsipkan($skhpp, 'TTD manual', function () use ($signedPath) {
            // Salinan terpisah di folder arsip, supaya menghapus arsip tidak menghilangkan berkas di SKHPP.
            $arsipPath = 'archives/' . basename($signedPath);
            if (!Storage::disk('local')->copy($signedPath, $arsipPath)) {
                throw new \RuntimeException("Gagal menyalin berkas ke {$arsipPath} (izin folder storage?)");
            }
            return $arsipPath;
        });
    }

    /**
     * SKHPP disahkan lewat TTE: naskah PDF ber-QR dibuat dan disimpan sebagai arsip surat keluar,
     * lalu entri Agenda Surat-nya ditandai sudah ber-PDF. Dipakai juga oleh perintah skhpp:sinkron-agenda.
     */
    public function arsipkanTte(Skhpp $skhpp): void
    {
        $this->arsipkan($skhpp, 'TTE', function () use ($skhpp) {
            $arsipPath = 'archives/SKHPP_' . $skhpp->id . '_' . Str::random(10) . '.pdf';
            // Disk 'local' tidak melempar error saat gagal menulis: periksa hasilnya supaya
            // arsip tidak tercatat tanpa berkas (pratinjau jadi 404).
            if (!Storage::disk('local')->put($arsipPath, \App\Support\SkhppPdf::buat($skhpp)->output())) {
                throw new \RuntimeException("Gagal menulis {$arsipPath} (izin folder storage?)");
            }
            return $arsipPath;
        });
    }

    /** Simpan berkas SKHPP sebagai arsip surat keluar dan tandai entri Agenda Surat-nya ber-PDF. */
    private function arsipkan(Skhpp $skhpp, string $jenis, \Closure $simpanBerkas): void
    {
        if (!$skhpp->nomor_skhpp) {
            return; // nomor ditulis tangan: tidak ada entri Agenda Surat
        }
        try {
            $log = \App\Models\LetterLog::where('full_number', $skhpp->nomor_skhpp)->latest('id')->first();
            if (!$log || $log->is_archived) {
                return;
            }

            $arsipPath = $simpanBerkas();

            \App\Models\Letter::create([
                'type' => 'keluar',
                'category_id' => $log->category_id,
                'sub_category_id' => $log->sub_category_id,
                'letter_number' => $log->full_number,
                'subject' => Str::limit($log->subject, 250, ''),
                'date' => $log->date,
                'file_path' => $arsipPath,
                'security_level' => $log->priority === 'B' ? 'biasa' : 'rahasia',
                'issuer' => 'Internal Denintel',
            ]);

            $log->update(['is_archived' => true]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal mengarsipkan SKHPP {$jenis} ke Agenda Surat: " . $e->getMessage());
        }
    }

    /** TTD manual hanya untuk SKHPP yang belum terbit, oleh pengajunya atau Admin. */
    private function authorizeTtdManual(Skhpp $skhpp): void
    {
        $this->authorizeSkhppEdit($skhpp);
        abort_if(strtolower((string) $skhpp->status) === 'approved', 422, 'SKHPP ini sudah terbit.');
    }

    /**
     * TTD Manual langkah 1: nomor dibooking supaya tercetak di kertas, lalu petugas mencetak
     * dan memintakan tanda tangan basah Komandan. Status tetap pending sampai hasilnya diunggah.
     */
    public function ajukanTtdManual(Request $request, $id)
    {
        $skhpp = Skhpp::findOrFail($id);
        $this->authorizeTtdManual($skhpp);

        $formattedNo = $this->bookingTtdManual($skhpp, $request);

        return back()->with('success', "SKHPP {$skhpp->nama} diajukan TTD manual dengan nomor {$formattedNo}. Cetak, mintakan tanda tangan Komandan, lalu unggah hasilnya.");
    }

    /** Tandai SKHPP menunggu tanda tangan basah dan booking nomornya (dipakai tombol di daftar & pilihan di form). */
    private function bookingTtdManual(Skhpp $skhpp, Request $request, ?string $nomorLama = null): string
    {
        // Nomor yang sebelumnya sudah dibooking di Agenda Surat untuk SKHPP ini (TTE maupun TTD manual).
        $nomorLama ??= $skhpp->getOriginal('nomor_skhpp');

        if ($skhpp->nomor_tulis_tangan) {
            // Nomor ditulis tangan: tidak dibooking. Nomor yang dulu sempat dibooking (belum ber-PDF) dilepas dari Agenda Surat.
            $this->lepasNomorAgenda($nomorLama);
            $nomorKolom = $this->nomorDraf(null, true);
            $skhpp->update(['ttd_mode' => 'manual', 'status' => 'pending', 'catatan_revisi' => null, ...$nomorKolom]);
            return $this->labelTulisTangan($nomorKolom['bulan_romawi'], $nomorKolom['tahun']);
        }

        [$nextSeq, $seqLabel, $formattedNo, $priority, $category, $romanMonth, $year] = $this->nomorResmi($skhpp, $request);

        $skhpp->update([
            'ttd_mode' => 'manual',
            'status' => 'pending',
            'catatan_revisi' => null,
            'nomor_skhpp' => $formattedNo,
            'nomor_urut' => $nextSeq,
            'bulan_romawi' => $romanMonth,
            'tahun' => $year,
        ]);

        // Langsung masuk Agenda Surat dengan status belum ada PDF; terisi otomatis saat TTD diunggah.
        $this->catatAgenda($skhpp, $formattedNo, $seqLabel, $priority, $category, $nomorLama);

        return $formattedNo;
    }

    /**
     * SKHPP jalur TTE yang menunggu tanda tangan Komandan: nomornya langsung dibooking dan tercatat
     * di Agenda Surat (status belum ada PDF), lalu otomatis ber-PDF saat disahkan. Nomor tulis tangan
     * tidak dibooking. Dipakai juga oleh perintah skhpp:sinkron-agenda.
     */
    public function bookingTte(Skhpp $skhpp, Request $request, ?string $nomorLama = null): ?string
    {
        if ($skhpp->nomor_tulis_tangan) {
            $this->lepasNomorAgenda($nomorLama);
            return null;
        }

        [$nextSeq, $seqLabel, $formattedNo, $priority, $category, $romanMonth, $year] = $this->nomorResmi($skhpp, $request);

        $skhpp->update([
            'nomor_skhpp' => $formattedNo,
            'nomor_urut' => $nextSeq,
            'bulan_romawi' => $romanMonth,
            'tahun' => $year,
        ]);

        $this->catatAgenda($skhpp, $formattedNo, $seqLabel, $priority, $category, $nomorLama);

        return $formattedNo;
    }

    /**
     * SKHPP TTE yang sudah terbit sebelum sinkronisasi ini ada: pastikan nomornya tercatat di Agenda Surat
     * (nomor tidak diubah) lalu arsipkan naskahnya. Dipakai perintah skhpp:sinkron-agenda.
     */
    public function sinkronTerbitKeAgenda(Skhpp $skhpp): void
    {
        if (!$skhpp->nomor_skhpp || $skhpp->nomor_tulis_tangan) {
            return;
        }
        if (!\App\Models\LetterLog::where('full_number', $skhpp->nomor_skhpp)->exists()) {
            $perusahaan = $skhpp->kategori_personel === 'perusahaan';
            $category = \App\Models\Category::firstOrCreate(
                ['code' => $perusahaan ? 'SKHPP-P' : 'SKHPP-D'],
                ['name' => $perusahaan ? 'SKHPP Mitra Kerja / Perusahaan' : 'SKHPP Dinas Militer & PNS', 'start_number' => 1]
            );
            $label = ($skhpp->nomor_manual && (int) $skhpp->nomor_manual === (int) $skhpp->nomor_urut) ? $skhpp->nomor_manual : (string) $skhpp->nomor_urut;
            $this->catatAgenda($skhpp, $skhpp->nomor_skhpp, $label, trim(explode('/', $skhpp->nomor_skhpp)[0]) ?: 'R', $category);
        }
        if ($skhpp->ttd_mode !== 'manual') {
            $this->arsipkanTte($skhpp);
        }
    }

    /** Lepas nomor yang pernah dibooking di Agenda Surat (hanya yang belum ber-PDF). */
    private function lepasNomorAgenda(?string $nomor): void
    {
        if ($nomor) {
            \App\Models\LetterLog::where('full_number', $nomor)->where('is_archived', false)->delete();
        }
    }

    /**
     * TTD Manual langkah 2: unggah SKHPP yang sudah ditandatangani → SKHPP terbit
     * dan tersinkron seperti pengesahan TTE (tanpa QR, karena tanda tangannya di kertas).
     */
    public function unggahTtdManual(Request $request, $id)
    {
        $skhpp = Skhpp::findOrFail($id);
        $this->authorizeTtdManual($skhpp);
        abort_unless($skhpp->ttd_mode === 'manual', 422, 'SKHPP ini belum diajukan TTD manual.');

        $tulisTangan = (bool) $skhpp->nomor_tulis_tangan;
        $request->validate([
            'berkas_ttd' => 'required|file|mimes:pdf|max:20480', // PDF saja: berkas ini juga menjadi arsip di Agenda Surat
            'tanggal_ttd' => 'required|date|before_or_equal:today',
            // Nomor ditulis tangan: nomor urut yang tertulis di kertas wajib diisi supaya tercatat di Agenda Surat & arsip.
            'nomor_urut_tulis' => [$tulisTangan ? 'required' : 'nullable', 'regex:/^\d{1,6}[A-Za-z]{0,2}$/'],
        ], [
            'nomor_urut_tulis.required' => 'Isi nomor urut yang ditulis tangan di naskah.',
            'nomor_urut_tulis.regex' => 'Nomor urut hanya angka, boleh diikuti huruf (contoh: 120 atau 120a).',
        ]);

        if ($tulisTangan) {
            // Bulan & tahun mengikuti yang tercetak di naskah; bila kosong, ikut tanggal tanda tangan.
            $tgl = $request->date('tanggal_ttd');
            $roman = $skhpp->bulan_romawi ?: $this->getRomanMonth($tgl->month);
            $tahun = (int) ($skhpp->tahun ?: $tgl->year);
            $urut = $request->input('nomor_urut_tulis');
            $nomor = "R / {$urut} / SKHPP / {$roman} / {$tahun}";

            // SKHPP-D & SKHPP-P bernomor sendiri-sendiri: bentrok hanya dicek di kategori yang sama.
            $perusahaan = $skhpp->kategori_personel === 'perusahaan';
            $kategoriId = \App\Models\Category::where('code', $perusahaan ? 'SKHPP-P' : 'SKHPP-D')->value('id');
            $bentrok = Skhpp::where('id', '!=', $skhpp->id)->where('nomor_skhpp', $nomor)
                    ->where('kategori_personel', $perusahaan ? '=' : '!=', 'perusahaan')->exists()
                || ($kategoriId && \App\Models\LetterLog::where('category_id', $kategoriId)->where('full_number', $nomor)->exists());
            if ($bentrok) {
                return back()->withErrors(['nomor_urut_tulis' => "Nomor {$nomor} sudah dipakai di SKHPP / Agenda Surat lain."]);
            }

            // Nomor tetap berstatus "ditulis tangan" (naskah aslinya kosong), tapi kini sudah diketahui.
            $skhpp->update([
                'nomor_manual' => $urut,
                'nomor_urut' => (int) $urut,
                'nomor_skhpp' => $nomor,
                'bulan_romawi' => $roman,
                'tahun' => $tahun,
            ]);
        }

        // Berkas bertanda tangan disimpan di disk privat; hanya bisa dibuka lewat route berizin.
        $path = $request->file('berkas_ttd')->store('skhpp_signed', 'local');

        $formattedNo = $this->terbitkan($skhpp, $request, [
            'tanggal_skhpp' => $request->date('tanggal_ttd'),
            'approved_by' => null, // disahkan lewat tanda tangan basah, bukan akun Komandan
            'signed_file' => $path,
            'signed_uploaded_by' => auth()->id(),
            'signed_uploaded_at' => now(),
        ]);

        $this->arsipkanTtdManual($skhpp, $path);

        return back()->with('success', "SKHPP bertanda tangan berhasil diunggah. SKHPP terbit dengan nomor {$formattedNo} (tersinkron ke Agenda Surat).");
    }

    /** Buka berkas SKHPP bertanda tangan manual. */
    public function berkasTtdManual($id)
    {
        $skhpp = Skhpp::findOrFail($id);
        abort_unless($skhpp->signed_file && Storage::disk('local')->exists($skhpp->signed_file), 404);

        return response()->file(Storage::disk('local')->path($skhpp->signed_file));
    }

    /**
     * Penolakan SKHPP / Minta Revisi
     */
    public function reject(Request $request, $id)
    {
        $user = auth()->user();
        $isCommander = \App\Support\MobileAccess::canApproveAsCommander($user);

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

        // Kirim Notifikasi Sistem In-App Bell & WA ke Operator / Pengaju
        try {
            $operator = User::find($skhpp->submitted_by);
            $pesanRevisi = " *SI SINDEN: PERMOHONAN SKHPP MEMERLUKAN REVISI*\n\n" .
                           "Laporan untuk Operator/Pengaju, pengajuan SKHPP dikembalikan Komandan untuk direvisi:\n\n" .
                           " *Nama:* {$skhpp->nama}\n" .
                           " *Catatan Revisi Komandan:* {$request->catatan_revisi}\n\n" .
                           "Silakan lakukan perbaikan data pada Laman : https://sisinden.my.id/skhpp";

            AppNotification::notify(
                $skhpp->submitted_by,
                null,
                'Revisi SKHPP Memerlukan Perbaikan',
                "Permohonan SKHPP atas nama {$skhpp->nama} dikembalikan Komandan untuk direvisi: \"{$request->catatan_revisi}\".",
                'warning',
                '/skhpp',
                $pesanRevisi,
                $operator?->phone
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal kirim notif SKHPP revisi: " . $e->getMessage());
        }

        return back()->with('warning', 'Pengajuan SKHPP ditolak / dikembalikan untuk revisi.');
    }

    /**
     * Halaman Publik Hasil Scan QR Code TTD Komandan (Validasi Legilitas Resmi SKHPP & Dokumen Dinas)
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

    /** Audit T04: ubah SKHPP hanya oleh pengaju atau Admin; yang sudah disahkan hanya Admin. */
    private function authorizeSkhppEdit(Skhpp $skhpp): void
    {
        $u = auth()->user();
        if (\App\Support\MobileAccess::isSuperAdmin($u)) {
            return;
        }
        abort_if(strtolower((string) $skhpp->status) === 'approved', 403, 'SKHPP yang sudah disahkan hanya dapat diubah Admin.');
        abort_unless((int) $skhpp->submitted_by === (int) $u->id, 403, 'Hanya pengaju yang dapat mengubah SKHPP ini.');
    }

    public function verify($code)
    {
        $code = trim($code);
        // Hanya pengaturan publik (audit K02).
        $settings = \App\Http\Middleware\HandleInertiaRequests::publicSettings(\App\Models\Setting::pluck('value', 'key')->all());

        // 1. Jika kode ternyata milik SignatureRequest (Berkas Dinas), alihkan ke Halaman Verifikasi Berkas Dinas
        $sigReqExists = \App\Models\SignatureRequest::where('verification_code', $code)->exists();

        if ($sigReqExists) {
            return redirect()->route('doc.verify', $code);
        }

        // 2. Cari Berkas SKHPP di tabel Skhpp
        // Hanya kode verifikasi (nomor SKHPP mudah ditebak) - audit K03.
        $skhpp = Skhpp::with(['members', 'approver'])->where('verification_code', $code)->first();

        // 3. Tampilkan Halaman Verifikasi SKHPP Resmi
        if (!$skhpp || strtolower($skhpp->status) !== 'approved') {
            return Inertia::render('Skhpp/Verify', [
                'skhpp' => null,
                'verify_code' => $code,
                'settings' => $settings
            ]);
        }

        // Logika a.n. Komandan vs Komandan Langsung pada SKHPP
        $approverUser = $skhpp->approver;
        $isKomandanLangsung = false;

        if ($approverUser) {
            $isKomandanLangsung = ($approverUser->role === 'komandan' || str_contains(strtolower($approverUser->name), 'hari bagio'));
        }

        if ($isKomandanLangsung) {
            $skhpp->signer_name = 'HARI BAGIO WIJAYANTO, M.TR.OPSLA.';
            $skhpp->signer_title = 'Komandan Detasemen Intelijen Kodaeral V - Kolonel Laut (E) NRP 16085/P';
        } else {
            $aName = $approverUser?->name ?? 'Administrator SINDEN';
            $aRank = $approverUser?->pangkat ?? 'Letnan Dua Laut (KC)';
            $aNrp = ($approverUser?->nrp && $approverUser->nrp !== '00000000000000') ? $approverUser->nrp : '12000018012200216';

            $skhpp->signer_name = 'a.n. KOMANDAN DETASEMEN INTELIJEN KODAERAL V';
            $skhpp->signer_title = "{$aRank} {$aName} NRP {$aNrp}";
        }

        // Waktu TTE memakai jam pengesahan sebenarnya (tanggal_skhpp hanya tanggal → dulu tampil 00.00).
        // TTD manual: tanggal ditandatangani di kertas, tanpa jam.
        $skhpp->setAttribute('waktu_tte', $skhpp->ttd_mode === 'manual'
            ? WaktuTte::teks(null, $skhpp->tanggal_skhpp)
            : WaktuTte::teks($skhpp->approved_at, $skhpp->tanggal_skhpp));

        // Data akun pemberi persetujuan/pengaju tidak dikirim ke halaman publik (audit K03).
        $skhpp->unsetRelation('approver')->unsetRelation('submitter');

        return Inertia::render('Skhpp/Verify', [
            'skhpp' => $skhpp,
            'verify_code' => $code,
            'settings' => $settings
        ]);
    }

    /**
     * Hapus Data Permohonan SKHPP
     */
    public function destroy($id)
    {
        abort_unless(\App\Support\MobileAccess::can(auth()->user(), 'skhpp.delete'), 403, 'Penghapusan SKHPP hanya oleh Admin.');
        $skhpp = Skhpp::findOrFail($id);

        if ($skhpp->foto_1 && Storage::disk('public')->exists($skhpp->foto_1)) {
            Storage::disk('public')->delete($skhpp->foto_1);
        }
        if ($skhpp->foto_2 && Storage::disk('public')->exists($skhpp->foto_2)) {
            Storage::disk('public')->delete($skhpp->foto_2);
        }

        // AuditLog pencabutan/penghapusan SKHPP
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'admin_name' => auth()->user()->name,
            'action' => 'DELETE_SKHPP',
            'target_personnel' => $skhpp->nama,
            'description' => "Penghapusan / Pencabutan SKHPP Nomor: " . ($skhpp->nomor_skhpp || $skhpp->id),
            'ip_address' => request()->ip(),
        ]);

        // Nomor yang masih dibooking (belum ber-PDF) dilepas dari Agenda Surat; yang sudah terarsip tetap.
        $this->lepasNomorAgenda($skhpp->nomor_skhpp);

        $skhpp->delete();

        return back()->with('success', 'Data SKHPP dan status validasi legalitas berhasil dihapus/dicabut.');
    }

    /**
     * Export Dokumen Resmi SKHPP ke Format PDF (DomPDF SINDEN Standard)
     */
    public function exportPdf($id)
    {
        $skhpp = Skhpp::with(['members', 'submitter', 'approver'])->findOrFail($id);

        $safeName = \Illuminate\Support\Str::slug($skhpp->nama);
        return \App\Support\SkhppPdf::buat($skhpp)->stream("SKHPP_{$safeName}.pdf");
    }

    /**
     * Export naskah SKHPP ke Word (.docx) yang bisa disunting; tata letak sama dengan PDF (F4, Arial 12pt).
     */
    public function exportDocx($id)
    {
        $skhpp = Skhpp::with(['members', 'submitter', 'approver'])->findOrFail($id);
        $bytes = (new \App\Services\SkhppDocx())->build($skhpp, \App\Support\SkhppPdf::bahan($skhpp));

        $safeName = \Illuminate\Support\Str::slug($skhpp->nama);
        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => "attachment; filename=\"SKHPP_{$safeName}.docx\"",
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Khusus Admin: Atur / Loncati Nomor Urut SKHPP Tanpa Menyetujui / TTD
     */
    public function updateNumber(Request $request, $id)
    {
        $user = auth()->user();
        if ($user->role !== 'admin') {
            return back()->with('error', 'Hanya Admin yang berwenang mengoperasikan fitur Atur Nomor.');
        }

        $request->validate([
            'custom_nomor_urut' => 'required|integer|min:1',
        ]);

        $skhpp = Skhpp::findOrFail($id);
        $nextSeq = (int)$request->custom_nomor_urut;

        $currentYear = date('Y');
        $currentMonth = date('n');
        $romanMonth = $this->getRomanMonth($currentMonth);

        $priority = $skhpp->priority ?? 'R';
        $isPerusahaan = ($skhpp->kategori_personel === 'perusahaan');
        $targetCode = $isPerusahaan ? 'SKHPP-P' : 'SKHPP-D';
        $cleanCode = 'SKHPP';

        $formattedNo = "{$priority} / {$nextSeq} / {$cleanCode} / {$romanMonth} / {$currentYear}";
        $nomorLama = $skhpp->nomor_skhpp;

        $skhpp->update([
            'nomor_skhpp' => $formattedNo,
            'nomor_urut' => $nextSeq,
            'bulan_romawi' => $romanMonth,
            'tahun' => (int)$currentYear,
        ]);

        // Entri Agenda Surat ikut pindah ke nomor baru (bukan dobel).
        if (!$skhpp->nomor_tulis_tangan) {
            $this->bookingTte($skhpp, $request, $nomorLama);
        }

        // Kirim Notifikasi Sistem In-App Bell & WA ke Operator & Komandan bahwa Nomor SKHPP telah dibooking
        try {
            $operator = User::find($skhpp->submitted_by);
            $komandan = User::where('role', 'komandan')->whereNotNull('phone')->first() 
                     ?? User::where('role', 'admin')->whereNotNull('phone')->first();

            $pesanBooking = " *SI SINDEN: NOMOR SKHPP BERHASIL DIBOOKING*\n\n" .
                            "Laporan untuk Operator & Komandan, Admin telah mengatur/booking nomor urut SKHPP:\n\n" .
                            " *Nama Personel:* {$skhpp->nama}\n" .
                            " *Kategori:* {$targetCode}\n" .
                            " *Nomor SKHPP Dibooking:* {$formattedNo}\n" .
                            " *Peruntukan:* {$skhpp->peruntukan}\n" .
                            " *Status Berkas:* Pending TTD Komandan\n" .
                            " *Diatur Oleh Admin:* {$user->name}\n\n" .
                            "Dokumen dapat dipantau di Laman : https://sisinden.my.id/skhpp";

            AppNotification::notify(
                $skhpp->submitted_by,
                null,
                'Nomor SKHPP Berhasil Dibooking',
                "Admin {$user->name} telah mengatur nomor urut {$targetCode} menjadi {$formattedNo} untuk {$skhpp->nama}.",
                'info',
                '/skhpp',
                $pesanBooking,
                $operator?->phone
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal kirim notif Booking Nomor: " . $e->getMessage());
        }

        return back()->with('success', "Nomor urut {$targetCode} berhasil diatur ke {$nextSeq} ({$formattedNo}) tanpa menyetujui TTD.");
    }

    /**
     * Khusus Admin: Ajukan Ulang TTE ke Komandan (Reset status ke pending)
     */
    public function reSubmitTte($id)
    {
        $user = auth()->user();
        if ($user->role !== 'admin') {
            return back()->with('error', 'Hanya Admin yang berwenang mengoperasikan fitur Ajukan TTE Ulang.');
        }

        $skhpp = Skhpp::findOrFail($id);
        $skhpp->update([
            'status' => 'pending',
            'ttd_mode' => null, // kembali ke jalur TTE
            'catatan_revisi' => null,
            'submitted_at' => now(),
        ]);
        $this->bookingTte($skhpp, request(), $skhpp->nomor_skhpp); // tetap tercatat di Agenda Surat selama menunggu

        // Kirim Notifikasi Sistem In-App Bell & WA ke Komandan
        try {
            $komandan = User::where('role', 'komandan')->whereNotNull('phone')->first()
                     ?? User::where('role', 'admin')->whereNotNull('phone')->first();
            $katName = ($skhpp->kategori_personel === 'perusahaan') ? 'SKHPP-P (Mitra Kerja/Perusahaan)' : 'SKHPP-D (Dinas Militer & PNS)';
            $directUrl = url('/signature-requests?open_skhpp=' . $skhpp->id . '&tab=skhpp');
            $pesan = " *SI SINDEN: PENGAJUAN ULANG TTE SKHPP*\n\n" .
                     "Mohon izin Komandan, terdapat permohonan SKHPP yang DIAJUKAN ULANG oleh Admin untuk otorisasi TTE Komandan:\n\n" .
                     " *Nama:* {$skhpp->nama}\n" .
                     " *Pangkat/NRP/NIK:* " . ($skhpp->pangkat_korps_nrp ?: ($skhpp->nik ?: '-')) . "\n" .
                     " *Kategori:* {$katName}\n" .
                     " *Peruntukan:* {$skhpp->peruntukan}\n" .
                     " *Pengaju (Admin):* {$user->name}\n\n" .
                     "Mohon izin untuk memeriksa & menyetujui berkas langsung di: {$directUrl}";
            
            AppNotification::notify(
                $komandan?->id,
                'komandan',
                'Pengajuan Ulang TTE SKHPP',
                "Admin {$user->name} mengajukan ulang TTE SKHPP atas nama {$skhpp->nama} ke Komandan.",
                'primary',
                '/signature-requests?open_skhpp=' . $skhpp->id . '&tab=skhpp',
                $pesan,
                $komandan?->phone
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Notif Ajukan Ulang failed: " . $e->getMessage());
        }

        return back()->with('success', "Permohonan SKHPP {$skhpp->nama} berhasil diajukan ulang ke TTD Komandan.");
    }
}
