<?php

namespace App\Http\Controllers;

use App\Models\Skhpp;
use App\Models\SkhppMember;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Models\AppNotification;
use App\Services\WhatsappService;
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
    private function nomorDraf(?string $manual): array
    {
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
            'jenis_identitas' => $request->jenis_identitas,
            'letter_sub_category_id' => $request->letter_sub_category_id,
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
            // Nomor urut manual (mis. 120 / 120a) langsung dibooking di draf; dipakai lagi saat disahkan (lihat approve()).
            ...$this->nomorDraf($request->nomor_manual),
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

        // Nomor urut manual diubah/dihapus: nomor draf ikut (dihapus = kembali ke penomoran otomatis).
        if ($request->nomor_manual !== $skhpp->nomor_manual) {
            $skhpp->fill($this->nomorDraf($request->nomor_manual));
        }

        $skhpp->update([
            'kategori_personel' => $request->kategori_personel,
            'is_pernikahan' => $request->is_pernikahan,
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
        [$nextSeq, $seqLabel, $formattedNo, $priority, $skhppCategory, $romanMonth, $currentYear] = $this->nomorResmi($skhpp, $request);

        $skhpp->update(array_merge([
            'status' => 'approved',
            'nomor_skhpp' => $formattedNo,
            'nomor_urut' => $nextSeq,
            'bulan_romawi' => $romanMonth,
            'tahun' => (int)$currentYear,
            'tanggal_skhpp' => now(),
            'approved_by' => $user->id,
            'approved_at' => now(),
            'catatan_revisi' => null,
        ], $extra));

        // SINKRONISASI OTOMATIS KE BUKU AGENDA SURAT SINDEN (LETTER_LOGS)
        try {
            \App\Models\LetterLog::create([
                'full_number' => $formattedNo,
                'sequence' => $seqLabel,
                'priority' => $priority,
                'category_id' => $skhppCategory->id,
                // Jenis Surat yang dipilih di form SKHPP (hanya bila memang milik kategori ini)
                'sub_category_id' => $skhpp->letter_sub_category_id
                    ? \App\Models\LetterSubCategory::where('id', $skhpp->letter_sub_category_id)->where('category_id', $skhppCategory->id)->value('id')
                    : null,
                'subject' => "SKHPP - {$skhpp->nama} (" . strtoupper($skhpp->kategori_personel) . ") - " . $skhpp->peruntukan,
                'recipient' => 'Yth. Asintel Dankodaeral V',
                'date' => now()->toDateString(),
                'is_archived' => false,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal auto-sync SKHPP ke LetterLog: " . $e->getMessage());
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

        [$nextSeq, , $formattedNo, , , $romanMonth, $year] = $this->nomorResmi($skhpp, $request);

        $skhpp->update([
            'ttd_mode' => 'manual',
            'status' => 'pending',
            'catatan_revisi' => null,
            'nomor_skhpp' => $formattedNo,
            'nomor_urut' => $nextSeq,
            'bulan_romawi' => $romanMonth,
            'tahun' => $year,
        ]);

        return back()->with('success', "SKHPP {$skhpp->nama} diajukan TTD manual dengan nomor {$formattedNo}. Cetak, mintakan tanda tangan Komandan, lalu unggah hasilnya.");
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

        $request->validate([
            'berkas_ttd' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'tanggal_ttd' => 'required|date|before_or_equal:today',
        ]);

        // Berkas bertanda tangan disimpan di disk privat; hanya bisa dibuka lewat route berizin.
        $path = $request->file('berkas_ttd')->store('skhpp_signed', 'local');

        $formattedNo = $this->terbitkan($skhpp, $request, [
            'tanggal_skhpp' => $request->date('tanggal_ttd'),
            'approved_by' => null, // disahkan lewat tanda tangan basah, bukan akun Komandan
            'signed_file' => $path,
            'signed_uploaded_by' => auth()->id(),
            'signed_uploaded_at' => now(),
        ]);

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

        $skhpp->delete();

        return back()->with('success', 'Data SKHPP dan status validasi legalitas berhasil dihapus/dicabut.');
    }

    /**
     * Export Dokumen Resmi SKHPP ke Format PDF (DomPDF SINDEN Standard)
     */
    public function exportPdf($id)
    {
        $skhpp = Skhpp::with(['members', 'submitter', 'approver'])->findOrFail($id);

        $foto1_base64 = null;
        if ($skhpp->foto_1 && Storage::disk('public')->exists($skhpp->foto_1)) {
            $foto1Path = Storage::disk('public')->path($skhpp->foto_1);
            $foto1Type = pathinfo($foto1Path, PATHINFO_EXTENSION);
            $foto1Data = file_get_contents($foto1Path);
            $foto1_base64 = 'data:image/' . $foto1Type . ';base64,' . base64_encode($foto1Data);
        }

        $foto2_base64 = null;
        if ($skhpp->foto_2 && Storage::disk('public')->exists($skhpp->foto_2)) {
            $foto2Path = Storage::disk('public')->path($skhpp->foto_2);
            $foto2Type = pathinfo($foto2Path, PATHINFO_EXTENSION);
            $foto2Data = file_get_contents($foto2Path);
            $foto2_base64 = 'data:image/' . $foto2Type . ';base64,' . base64_encode($foto2Data);
        }

        // Generate QR Code TTD Image
        $qr_base64 = null;
        $manual = $skhpp->ttd_mode === 'manual'; // tanda tangan basah: tanpa QR
        if ($skhpp->status === 'approved' && !$manual) {
            try {
                $verifyUrl = route('skhpp.verify', $skhpp->verification_code);
                $qrData = file_get_contents('https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($verifyUrl));
                if ($qrData) {
                    $qr_base64 = 'data:image/png;base64,' . base64_encode($qrData);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Gagal fetch QR image: " . $e->getMessage());
            }
        }

        $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $tglLahir = $skhpp->tanggal_lahir ? new \DateTime($skhpp->tanggal_lahir) : null;
        $tanggal_lahir_indo = $tglLahir ? ($tglLahir->format('d') . ' ' . $months[(int)$tglLahir->format('n') - 1] . ' ' . $tglLahir->format('Y')) : '';

        $tglSkhpp = $skhpp->tanggal_skhpp ? new \DateTime($skhpp->tanggal_skhpp) : ($skhpp->approved_at ? new \DateTime($skhpp->approved_at) : new \DateTime());
        $tanggal_skhpp_indo = $tglSkhpp ? ($tglSkhpp->format('j') . ' ' . $months[(int)$tglSkhpp->format('n') - 1] . ' ' . $tglSkhpp->format('Y')) : '';
        $bulan_tahun_indo = $tglSkhpp ? ($months[(int)$tglSkhpp->format('n') - 1] . ' ' . $tglSkhpp->format('Y')) : '';
        // Naskah untuk TTD manual: hari dikosongkan (diisi tangan saat ditandatangani), bulan & tahun tetap tercetak.
        if ($manual && $skhpp->status !== 'approved') {
            $tanggal_skhpp_indo = ' ' . $bulan_tahun_indo;
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.skhpp', compact(
            'skhpp', 'foto1_base64', 'foto2_base64', 'qr_base64',
            'tanggal_lahir_indo', 'tanggal_skhpp_indo', 'bulan_tahun_indo'
        ))->setPaper([0, 0, 609.45, 935.43], 'portrait'); // EXACT F4 / FOLIO SIZE (21.5cm x 33cm)

        $safeName = \Illuminate\Support\Str::slug($skhpp->nama);
        return $pdf->stream("SKHPP_{$safeName}.pdf");
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

        $skhpp->update([
            'nomor_skhpp' => $formattedNo,
            'nomor_urut' => $nextSeq,
            'bulan_romawi' => $romanMonth,
            'tahun' => (int)$currentYear,
        ]);

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
