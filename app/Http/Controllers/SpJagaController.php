<?php

namespace App\Http\Controllers;

use App\Models\SpJaga;
use App\Models\SpJagaPerwira;
use App\Models\SpJagaAnggota;
use App\Models\User;
use App\Services\WhatsappService;
use App\Support\DutyRoster;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SpJagaController extends Controller
{
    private function ensureNomorUrutColumnType(): void
    {
        try {
            static $checked = false;
            if (!$checked) {
                DB::statement("ALTER TABLE sp_jagas MODIFY COLUMN nomor_urut VARCHAR(50) NULL DEFAULT NULL");
                $checked = true;
            }
        } catch (\Throwable $e) {
            // Abaikan jika sudah VARCHAR(50) atau database tidak mengizinkan ALTER langsung
        }
    }
    private function getRomanMonth($month)
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        return $map[(int)$month] ?? 'I';
    }

    private function getNamaBulanIndo($month)
    {
        $map = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        return $map[(int)$month] ?? 'Januari';
    }

    private function formatTanggalIndo($dateStr)
    {
        if (!$dateStr) return '';
        $bulanIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $c = Carbon::parse($dateStr);
        $m = (int)$c->format('n');
        $namaBulan = $bulanIndo[$m] ?? $c->format('F');
        return $c->format('j') . ' ' . $namaBulan . ' ' . $c->format('Y');
    }

    private function terbilangAngka($angka)
    {
        $angka = (int)$angka;
        $bilangan = [
            '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima',
            'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'
        ];
        
        if ($angka < 12) {
            return $bilangan[$angka];
        } elseif ($angka < 20) {
            return $this->terbilangAngka($angka - 10) . ' Belas';
        } elseif ($angka < 100) {
            $hasil = $this->terbilangAngka((int)($angka / 10)) . ' Puluh';
            if ($angka % 10 > 0) {
                $hasil .= ' ' . $bilangan[$angka % 10];
            }
            return $hasil;
        }
        return (string)$angka;
    }

    /**
     * Halaman Utama Menu SP Jaga
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isPasops = \App\Support\MobileAccess::isPasops($user);
        $isAdmin = \App\Support\MobileAccess::isSpJagaAdmin($user);

        // 1. Ambil Jadwal Jaga Saya (Bulan Berjalan / Terbaru yang Published)
        $activeSp = SpJaga::with(['perwiras', 'anggotas'])
            ->where('status', 'published')
            ->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc')
            ->orderBy('id', 'desc') // revisi terbaru (nomor sama) menggantikan SP sebelumnya
            ->first();

        $mySchedule = null;
        if ($activeSp) {
            // Cek di Perwira Jaga
            $perwiraMatch = $activeSp->perwiras->first(function($p) use ($user) {
                return $p->user_id == $user->id 
                    || (!empty($user->nrp) && $p->nrp == $user->nrp)
                    || (str_contains(strtolower($p->nama), strtolower($user->name)));
            });

            if ($perwiraMatch) {
                $dates = [];
                for ($i = 1; $i <= 7; $i++) {
                    $val = $perwiraMatch->{"tgl_{$i}"};
                    if (!empty($val) && $val !== '-') {
                        $dates[] = $val;
                    }
                }
                $mySchedule = [
                    'sp_id' => $activeSp->id,
                    'nomor_sprin' => $activeSp->nomor_sprin,
                    'bulan' => $activeSp->bulan,
                    'tahun' => $activeSp->tahun,
                    'peran' => 'Perwira Siaga Sintel',
                    'pangkat_korps' => $perwiraMatch->pangkat_korps,
                    'tanggal_jaga' => implode(', ', $dates),
                    'pdf_url' => route('sp-jaga.download-pdf', $activeSp->id)
                ];
            } else {
                // Cek di Anggota Jaga Divisi
                foreach ($activeSp->anggotas as $divisi) {
                    $items = is_array($divisi->anggota_items) ? $divisi->anggota_items : json_decode($divisi->anggota_items, true) ?? [];
                    foreach ($items as $item) {
                        $isMatch = ($item['user_id'] ?? null) == $user->id
                                || (!empty($user->nrp) && ($item['nrp_nip'] ?? '') == $user->nrp)
                                || str_contains(strtolower($item['nama'] ?? ''), strtolower($user->name));
                        
                        if ($isMatch) {
                            $mySchedule = [
                                'sp_id' => $activeSp->id,
                                'nomor_sprin' => $activeSp->nomor_sprin,
                                'bulan' => $activeSp->bulan,
                                'tahun' => $activeSp->tahun,
                                'divisi_no' => $divisi->divisi_no,
                                'peran' => ($item['role_jaga'] ?? 'ANGGOTA') === 'BAGA' ? 'Bintara Jaga (BAGA)' : 'Anggota Siaga Sintel',
                                'pangkat_korps' => $item['pangkat_korps'] ?? '',
                                'tanggal_jaga' => $divisi->tanggal_list_text,
                                'pdf_url' => route('sp-jaga.download-pdf', $activeSp->id)
                            ];
                            break 2;
                        }
                    }
                }
            }
        }

        // 2. Daftar Semua SP Jaga
        // Daftar utama hanya SP asli; revisinya tampil di bawah masing-masing SP.
        $query = SpJaga::with(['creator', 'approver', 'perwiras', 'anggotas', 'revisions.creator', 'revisions.approver'])
            ->whereNull('revisi_dari_id')
            ->orderBy('created_at', 'desc');

        if (!$isAdmin && !$isPasops) {
            $query->where('status', 'published');
        }

        $spJagas = $query->paginate(15)->withQueryString();
        $spJagas->getCollection()->each(function (SpJaga $sp) use ($user, $isAdmin, $isPasops) {
            if (!$isAdmin && !$isPasops) {
                $sp->setRelation('revisions', $sp->revisions->where('status', 'published')->values());
            }
            $sp->setAttribute('can_revise', $this->alasanTidakBisaRevisi($sp, $user) === null);
        });

        // 3. Antrean Verifikasi Pasops (Khusus Pasops & Admin)
        $pendingSignatures = SpJaga::with(['creator', 'perwiras', 'anggotas', 'induk'])
            ->where('status', 'pending_signature')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('SpJaga/Index', [
            'mySchedule' => $mySchedule,
            'spJagas' => $spJagas,
            'pendingSignatures' => $pendingSignatures,
            'isPasops' => $isPasops,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Form Pembuatan SP Jaga Baru
     */
    public function create()
    {
        $personels = User::select('id', 'name', 'pangkat', 'nrp', 'role', 'phone')
            ->orderBy('name', 'asc')
            ->get();

        $currentMonth = (int)date('n');
        $currentYear = (int)date('Y');

        // SP baru otomatis untuk bulan setelah SP terakhir & melanjutkan rotasinya
        $previousSp = DutyRoster::previous();
        $previous = null;
        if ($previousSp) {
            [$prevMonth, $prevYear] = DutyRoster::period($previousSp);
            $currentMonth = $prevMonth === 12 ? 1 : $prevMonth + 1;
            $currentYear = $prevMonth === 12 ? $prevYear + 1 : $prevYear;

            $previous = [
                'anchor' => DutyRoster::rotationAnchor($previousSp),
                'perwira_tertua_user_id' => $previousSp->perwira_tertua_user_id,
                'perwira_tertua_nama' => $previousSp->perwira_tertua_nama,
                'perwira_tertua_pangkat_nrp' => $previousSp->perwira_tertua_pangkat_nrp,
                'perwira_tertua_jabatan' => $previousSp->perwira_tertua_jabatan,
                'perwiras' => $previousSp->perwiras->map(fn ($p) => [
                    'user_id' => $p->user_id ?? '',
                    'nama' => $p->nama,
                    'pangkat_korps' => $p->pangkat_korps ?? '',
                    'nrp' => $p->nrp ?? '',
                ])->values(),
                'anggotas' => $previousSp->anggotas->map(fn ($a) => [
                    'divisi_no' => (int) $a->divisi_no,
                    'anggota_items' => is_array($a->anggota_items) ? $a->anggota_items : (json_decode((string) $a->anggota_items, true) ?: []),
                ])->values(),
            ];
        }

        return Inertia::render('SpJaga/Create', [
            'personels' => $personels,
            'defaultMonth' => $currentMonth,
            'defaultYear' => $currentYear,
            'previous' => $previous,
        ]);
    }

    /**
     * Simpan SP Jaga Baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2024',
            'nomor_urut' => 'nullable|string|max:50',
            'tmt_mulai' => 'required|date',
            'tmt_selesai' => 'required|date',
            'tanggal_surat' => 'required|date',
            'ttd_type' => 'required|in:tte,manual',
            'perwiras' => 'required|array|min:1',
            'anggotas' => 'required|array|min:1',
        ]);

        $user = auth()->user();

        $bulanInput = (int)$request->bulan;
        $tahunInput = (int)$request->tahun;
        $bulanRomawi = $this->getRomanMonth($bulanInput);
        $nomorUrut = trim((string)($request->nomor_urut ?: '29'));
        $nomorSprin = "Sprin/ {$nomorUrut} /{$bulanRomawi}/{$tahunInput}";

        // Hitung total personel
        $totalPersonel = count($request->perwiras);
        foreach ($request->anggotas as $div) {
            $items = $div['anggota_items'] ?? [];
            $totalPersonel += count($items);
        }
        $terbilang = $this->terbilangAngka($totalPersonel);

        // Perwira Tertua (Pater / Penerima Perintah)
        $firstPerwira = $request->perwiras[0] ?? [];
        $perwiraTertuaUserId = $request->perwira_tertua_user_id ?: ($firstPerwira['user_id'] ?? null);
        $perwiraTertuaNama = $request->perwira_tertua_nama ?: ($firstPerwira['nama'] ?? 'Kapten Laut (P) Indra Gunawan T.Z');
        $perwiraTertuaPangkatNrp = $request->perwira_tertua_pangkat_nrp ?: (($firstPerwira['pangkat_korps'] ?? 'Kapten Laut (P)') . ' NRP ' . ($firstPerwira['nrp'] ?? '19739/P'));
        $perwiraTertuaJabatan = $request->perwira_tertua_jabatan ?: 'Dan Unit 1 Lid Den Intel Kodaeral V';

        $uniqueCode = 'TTE-SPJAGA-' . $tahunInput . str_pad($bulanInput, 2, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        $status = ($request->ttd_type === 'tte') ? 'pending_signature' : 'draft';

        $this->ensureNomorUrutColumnType();

        $createData = [
            'nomor_sprin' => $nomorSprin,
            'nomor_urut' => $nomorUrut,
            'bulan' => $bulanInput,
            'tahun' => $tahunInput,
            'bulan_romawi' => $bulanRomawi,
            'tmt_mulai' => $request->tmt_mulai,
            'tmt_selesai' => $request->tmt_selesai,
            'tanggal_surat' => $request->tanggal_surat,
            'perwira_tertua_user_id' => $perwiraTertuaUserId,
            'perwira_tertua_nama' => $perwiraTertuaNama,
            'perwira_tertua_pangkat_nrp' => $perwiraTertuaPangkatNrp,
            'perwira_tertua_jabatan' => $perwiraTertuaJabatan,
            'total_personel_count' => $totalPersonel,
            'total_personel_terbilang' => $terbilang,
            'ttd_type' => $request->ttd_type,
            'status' => $status,
            'verification_code' => $uniqueCode,
            'penandatangan_nama' => 'Roni Sumantri',
            'penandatangan_pangkat_nrp' => 'Mayor Laut (P) NRP 17456/P',
            'penandatangan_jabatan' => 'Pasiops',
            'created_by' => $user->id,
        ];

        try {
            $spJaga = SpJaga::create($createData);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'nomor_urut') || str_contains($e->getMessage(), '1265')) {
                DB::statement("ALTER TABLE sp_jagas MODIFY COLUMN nomor_urut VARCHAR(50) NULL DEFAULT NULL");
                $spJaga = SpJaga::create($createData);
            } else {
                throw $e;
            }
        }

        $this->simpanJadwal($spJaga, $request->perwiras, $request->anggotas);

        // Jika TTE, kirim notifikasi ke Pasops
        if ($spJaga->ttd_type === 'tte') {
            $this->notifPasops($spJaga, $user);
        }

        return redirect()->route('sp-jaga.index')->with('success', 'Surat Perintah Jaga berhasil dibuat.');
    }

    /** Simpan Perwira Jaga (Lampiran 1) & Anggota Jaga Divisi (Lampiran 2) sebuah SP. */
    private function simpanJadwal(SpJaga $spJaga, array $perwiras, array $anggotas): void
    {
        foreach ($perwiras as $idx => $p) {
            SpJagaPerwira::create([
                'sp_jaga_id' => $spJaga->id,
                'user_id' => $p['user_id'] ?? null,
                'no_urut' => $idx + 1,
                'nama' => $p['nama'],
                'pangkat_korps' => $p['pangkat_korps'] ?? '',
                'nrp' => $p['nrp'] ?? '',
                'tgl_1' => $p['tgl_1'] ?? '-',
                'tgl_2' => $p['tgl_2'] ?? '-',
                'tgl_3' => $p['tgl_3'] ?? '-',
                'tgl_4' => $p['tgl_4'] ?? '-',
                'tgl_5' => $p['tgl_5'] ?? '-',
                'tgl_6' => $p['tgl_6'] ?? '-',
                'tgl_7' => $p['tgl_7'] ?? '-',
                'tgl_list' => $p['tgl_list'] ?? [],
            ]);
        }

        foreach ($anggotas as $idx => $a) {
            SpJagaAnggota::create([
                'sp_jaga_id' => $spJaga->id,
                'divisi_no' => $idx + 1,
                'tanggal_list_text' => $a['tanggal_list_text'] ?? '',
                'tanggal_array' => $a['tanggal_array'] ?? [],
                'anggota_items' => $a['anggota_items'] ?? [],
            ]);
        }
    }

    /** Notifikasi in-app + WhatsApp ke Pasops bahwa SP (atau revisinya) menunggu TTE. */
    private function notifPasops(SpJaga $spJaga, User $user): void
    {
        \App\Support\SpJagaNotifier::pendingSignature($spJaga, $user);
        try {
            $pasops = User::where('role', 'pasops')->whereNotNull('phone')->first()
                   ?? User::where('name', 'like', '%Roni Sumantri%')->first()
                   ?? User::where('role', 'admin')->whereNotNull('phone')->first();

            if ($pasops && $pasops->phone) {
                $bulanNama = strtoupper($this->getNamaBulanIndo($spJaga->bulan) . ' ' . $spJaga->tahun);
                $judul = $spJaga->isRevisi() ? "PENGAJUAN TTE REVISI KE-{$spJaga->revisi_ke} SP JAGA" : 'PENGAJUAN TTE SP JAGA BARU';
                $pesan = "*SI SINDEN: {$judul}*\n\n" .
                         "Mohon izin Pasops, terdapat pengajuan Surat Perintah Jaga Siaga Sintel " . ($spJaga->isRevisi() ? 'yang direvisi' : 'baru') . " yang memerlukan pemeriksaan & tanda tangan elektronik (TTE):\n\n" .
                         "- *Nomor Sprin:* " . $spJaga->nomor_sprin . ($spJaga->isRevisi() ? " (Revisi ke-{$spJaga->revisi_ke})" : '') . "\n" .
                         "- *Bulan/Tahun:* " . $bulanNama . "\n" .
                         "- *Total Personel:* " . $spJaga->total_personel_count . " Orang\n" .
                         ($spJaga->isRevisi() && $spJaga->revisi_alasan ? "- *Alasan Revisi:* " . $spJaga->revisi_alasan . "\n" : '') .
                         "- *Operator Pengaju:* " . $user->name . "\n\n" .
                         "Silakan periksa & bubuhkan TTE digital melalui Portal SINDEN:\n" .
                         route('sp-jaga.index');

                WhatsappService::sendMessage($pasops->phone, $pesan);
            }
        } catch (\Exception $e) {
            Log::warning("Gagal kirim notif Pasops TTE SP Jaga: " . $e->getMessage());
        }
    }

    /* =====================================================================
       REVISI SP JAGA YANG SUDAH DITANDATANGANI
       Syarat: hanya pengaju SP asli, lolos OTP WhatsApp, lalu menunggu TTE Pasops.
       Revisi = SP baru dengan nomor Sprin sama; tanggalnya mengikuti tanggal TTE.
       ===================================================================== */

    /** SP asli dari sebuah SP / revisinya. */
    private function spAsli($id): SpJaga
    {
        $sp = SpJaga::findOrFail($id);
        return $sp->isRevisi() ? SpJaga::findOrFail($sp->revisi_dari_id) : $sp;
    }

    /** Alasan sebuah SP asli belum bisa direvisi oleh pengguna ini (null = boleh). */
    private function alasanTidakBisaRevisi(SpJaga $asli, User $user): ?string
    {
        if ((int) $asli->created_by !== (int) $user->id) {
            return 'Hanya pengaju SP Jaga ini yang dapat merevisinya.';
        }
        if ($asli->status !== 'published') {
            return 'SP Jaga ini belum ditandatangani; gunakan Edit untuk mengubahnya.';
        }
        if (SpJaga::where('revisi_dari_id', $asli->id)->where('status', '!=', 'published')->exists()) {
            return 'Masih ada revisi SP Jaga ini yang menunggu TTE Pasops.';
        }
        return null;
    }

    private function authorizeRevisi(SpJaga $asli): void
    {
        $alasan = $this->alasanTidakBisaRevisi($asli, auth()->user());
        abort_if($alasan !== null, str_starts_with($alasan, 'Hanya pengaju') ? 403 : 422, $alasan);
    }

    /** Versi terbaru yang sudah terbit (SP asli atau revisi terakhir) — dasar isian form revisi. */
    private function versiTerbit(SpJaga $asli): SpJaga
    {
        return SpJaga::with(['perwiras', 'anggotas'])
            ->where(fn ($q) => $q->where('id', $asli->id)->orWhere('revisi_dari_id', $asli->id))
            ->where('status', 'published')
            ->orderByRaw('COALESCE(revisi_ke, 0) desc')
            ->first() ?? $asli->load(['perwiras', 'anggotas']);
    }

    /** Langkah 1: kirim OTP WhatsApp ke pengaju. */
    public function revisiKirimOtp($id)
    {
        $asli = $this->spAsli($id);
        $this->authorizeRevisi($asli);

        $hasil = \App\Support\SpJagaRevisiOtp::kirim(auth()->user(), $asli);
        return response()->json($hasil, $hasil['ok'] ? 200 : 422);
    }

    /** Langkah 2: periksa OTP; bila benar, pengaju boleh membuka form revisi. */
    public function revisiVerifikasiOtp(Request $request, $id)
    {
        $request->validate(['kode' => 'required|digits:6']);
        $asli = $this->spAsli($id);
        $this->authorizeRevisi($asli);

        $hasil = \App\Support\SpJagaRevisiOtp::verifikasi(auth()->user(), $asli, $request->kode);
        if ($hasil['ok']) {
            $hasil['redirect'] = route('sp-jaga.revisi', $asli->id);
        }
        return response()->json($hasil, $hasil['ok'] ? 200 : 422);
    }

    /** Langkah 3: form revisi (form Koreksi dengan nomor terkunci). */
    public function revisiForm($id)
    {
        $asli = $this->spAsli($id);
        $this->authorizeRevisi($asli);
        if (!\App\Support\SpJagaRevisiOtp::lolos(auth()->user(), $asli)) {
            return redirect()->route('sp-jaga.index')->with('error', 'Verifikasi OTP WhatsApp diperlukan sebelum merevisi SP Jaga.');
        }

        $dasar = $this->versiTerbit($asli);
        $previousSp = DutyRoster::previous($asli);

        return Inertia::render('SpJaga/Edit', [
            'spJaga' => $dasar,
            'personels' => User::select('id', 'name', 'pangkat', 'nrp', 'role', 'phone')->orderBy('name', 'asc')->get(),
            'rotationAnchor' => $previousSp ? DutyRoster::rotationAnchor($previousSp) : null,
            'revisi' => [
                'asli_id' => $asli->id,
                'nomor_sprin' => $asli->nomor_sprin,
                'revisi_ke' => (int) SpJaga::where('revisi_dari_id', $asli->id)->max('revisi_ke') + 1,
            ],
        ]);
    }

    /** Langkah 4: simpan revisi sebagai SP baru (nomor sama) yang menunggu TTE Pasops. */
    public function revisiStore(Request $request, $id)
    {
        $asli = $this->spAsli($id);
        $this->authorizeRevisi($asli);
        $user = auth()->user();
        abort_unless(\App\Support\SpJagaRevisiOtp::lolos($user, $asli), 403, 'Verifikasi OTP WhatsApp diperlukan sebelum merevisi SP Jaga.');

        $request->validate([
            'alasan_revisi' => 'required|string|max:500',
            'tmt_mulai' => 'required|date',
            'tmt_selesai' => 'required|date',
            'perwiras' => 'required|array|min:1',
            'anggotas' => 'required|array|min:1',
        ]);

        $totalPersonel = count($request->perwiras);
        foreach ($request->anggotas as $div) {
            $totalPersonel += count($div['anggota_items'] ?? []);
        }

        $firstPerwira = $request->perwiras[0] ?? [];

        $revisi = DB::transaction(function () use ($request, $asli, $user, $totalPersonel, $firstPerwira) {
            $revisiKe = (int) SpJaga::where('revisi_dari_id', $asli->id)->lockForUpdate()->max('revisi_ke') + 1;

            $revisi = SpJaga::create([
                'revisi_dari_id' => $asli->id,
                'revisi_ke' => $revisiKe,
                'revisi_alasan' => trim($request->alasan_revisi),
                // Nomor & periode tetap mengikuti SP asli.
                'nomor_sprin' => $asli->nomor_sprin,
                'nomor_urut' => $asli->nomor_urut,
                'bulan' => $asli->bulan,
                'tahun' => $asli->tahun,
                'bulan_romawi' => $asli->bulan_romawi,
                'tmt_mulai' => $request->tmt_mulai,
                'tmt_selesai' => $request->tmt_selesai,
                // Sementara; diganti tanggal TTE saat Pasops mengesahkan.
                'tanggal_surat' => now()->toDateString(),
                'perwira_tertua_user_id' => $request->perwira_tertua_user_id ?: ($firstPerwira['user_id'] ?? null),
                'perwira_tertua_nama' => $request->perwira_tertua_nama ?: ($firstPerwira['nama'] ?? $asli->perwira_tertua_nama),
                'perwira_tertua_pangkat_nrp' => $request->perwira_tertua_pangkat_nrp ?: $asli->perwira_tertua_pangkat_nrp,
                'perwira_tertua_jabatan' => $request->perwira_tertua_jabatan ?: $asli->perwira_tertua_jabatan,
                'total_personel_count' => $totalPersonel,
                'total_personel_terbilang' => $this->terbilangAngka($totalPersonel),
                'ttd_type' => 'tte',
                'status' => 'pending_signature',
                'verification_code' => 'TTE-SPJAGA-' . $asli->tahun . str_pad($asli->bulan, 2, '0', STR_PAD_LEFT) . '-R' . $revisiKe . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6)),
                'penandatangan_nama' => $asli->penandatangan_nama,
                'penandatangan_pangkat_nrp' => $asli->penandatangan_pangkat_nrp,
                'penandatangan_jabatan' => $asli->penandatangan_jabatan,
                'created_by' => $user->id,
            ]);

            $this->simpanJadwal($revisi, $request->perwiras, $request->anggotas);
            return $revisi;
        });

        \App\Support\SpJagaRevisiOtp::habiskan($asli);

        // Lolos OTP pengaju = disahkan: TTE Pasops langsung tertempel dengan tanggal hari ini.
        $this->sahkanTte($revisi->load(['perwiras', 'anggotas']), $user);
        \App\Support\SpJagaNotifier::revisiDisahkan($revisi, $user);

        return redirect()->route('sp-jaga.index')->with('success', "Revisi ke-{$revisi->revisi_ke} {$revisi->nomor_sprin} disahkan dengan TTE Pasops dan notifikasi telah dikirim ke personel.");
    }

    /**
     * Halaman Edit & Koreksi Jadwal Jaga
     */
    public function edit($id)
    {
        $spJaga = SpJaga::with(['perwiras', 'anggotas'])->findOrFail($id);
        $personels = User::select('id', 'name', 'pangkat', 'nrp', 'role', 'phone')
            ->orderBy('name', 'asc')
            ->get();

        $previousSp = DutyRoster::previous($spJaga);

        return Inertia::render('SpJaga/Edit', [
            'spJaga' => $spJaga,
            'personels' => $personels,
            'rotationAnchor' => $previousSp ? DutyRoster::rotationAnchor($previousSp) : null,
        ]);
    }

    /**
     * Simpan Pembaruan / Koreksi Jadwal Jaga
     */
    public function update(Request $request, $id)
    {
        $spJaga = SpJaga::findOrFail($id);

        $request->validate([
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2024',
            'nomor_urut' => 'nullable|string|max:50',
            'tmt_mulai' => 'required|date',
            'tmt_selesai' => 'required|date',
            'tanggal_surat' => 'required|date',
            'ttd_type' => 'required|in:tte,manual',
            'perwiras' => 'required|array|min:1',
            'anggotas' => 'required|array|min:1',
        ]);

        $bulanInput = (int)$request->bulan;
        $tahunInput = (int)$request->tahun;
        $bulanRomawi = $this->getRomanMonth($bulanInput);
        $nomorUrut = trim((string)($request->nomor_urut ?: $spJaga->nomor_urut ?: '29'));
        $nomorSprin = "Sprin/ {$nomorUrut} /{$bulanRomawi}/{$tahunInput}";

        $totalPersonel = count($request->perwiras);
        foreach ($request->anggotas as $div) {
            $items = $div['anggota_items'] ?? [];
            $totalPersonel += count($items);
        }
        $terbilang = $this->terbilangAngka($totalPersonel);

        // Perwira Tertua (Pater / Penerima Perintah)
        $firstPerwira = $request->perwiras[0] ?? [];
        $perwiraTertuaUserId = $request->perwira_tertua_user_id ?: ($firstPerwira['user_id'] ?? null);
        $perwiraTertuaNama = $request->perwira_tertua_nama ?: ($firstPerwira['nama'] ?? 'Kapten Laut (P) Indra Gunawan T.Z');
        $perwiraTertuaPangkatNrp = $request->perwira_tertua_pangkat_nrp ?: (($firstPerwira['pangkat_korps'] ?? 'Kapten Laut (P)') . ' NRP ' . ($firstPerwira['nrp'] ?? '19739/P'));
        $perwiraTertuaJabatan = $request->perwira_tertua_jabatan ?: 'Dan Unit 1 Lid Den Intel Kodaeral V';

        $this->ensureNomorUrutColumnType();

        $updateData = [
            'nomor_sprin' => $nomorSprin,
            'nomor_urut' => $nomorUrut,
            'bulan' => $bulanInput,
            'tahun' => $tahunInput,
            'bulan_romawi' => $bulanRomawi,
            'tmt_mulai' => $request->tmt_mulai,
            'tmt_selesai' => $request->tmt_selesai,
            'tanggal_surat' => $request->tanggal_surat,
            'perwira_tertua_user_id' => $perwiraTertuaUserId,
            'perwira_tertua_nama' => $perwiraTertuaNama,
            'perwira_tertua_pangkat_nrp' => $perwiraTertuaPangkatNrp,
            'perwira_tertua_jabatan' => $perwiraTertuaJabatan,
            'total_personel_count' => $totalPersonel,
            'total_personel_terbilang' => $terbilang,
            'ttd_type' => $request->ttd_type,
        ];

        // Koreksi pada baris revisi: nomor, periode, dan jalur TTE tetap mengikuti SP asli.
        if ($spJaga->isRevisi() && ($asli = SpJaga::find($spJaga->revisi_dari_id))) {
            $updateData = array_merge($updateData, [
                'nomor_sprin' => $asli->nomor_sprin,
                'nomor_urut' => $asli->nomor_urut,
                'bulan' => $asli->bulan,
                'tahun' => $asli->tahun,
                'bulan_romawi' => $asli->bulan_romawi,
                'ttd_type' => 'tte',
            ]);
        }

        try {
            $spJaga->update($updateData);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'nomor_urut') || str_contains($e->getMessage(), '1265')) {
                DB::statement("ALTER TABLE sp_jagas MODIFY COLUMN nomor_urut VARCHAR(50) NULL DEFAULT NULL");
                $spJaga->update($updateData);
            } else {
                throw $e;
            }
        }

        // Sync Perwira
        SpJagaPerwira::where('sp_jaga_id', $spJaga->id)->delete();
        foreach ($request->perwiras as $idx => $p) {
            SpJagaPerwira::create([
                'sp_jaga_id' => $spJaga->id,
                'user_id' => $p['user_id'] ?? null,
                'no_urut' => $idx + 1,
                'nama' => $p['nama'],
                'pangkat_korps' => $p['pangkat_korps'] ?? '',
                'nrp' => $p['nrp'] ?? '',
                'tgl_1' => $p['tgl_1'] ?? '-',
                'tgl_2' => $p['tgl_2'] ?? '-',
                'tgl_3' => $p['tgl_3'] ?? '-',
                'tgl_4' => $p['tgl_4'] ?? '-',
                'tgl_5' => $p['tgl_5'] ?? '-',
                'tgl_6' => $p['tgl_6'] ?? '-',
                'tgl_7' => $p['tgl_7'] ?? '-',
                'tgl_list' => $p['tgl_list'] ?? [],
            ]);
        }

        // Sync Anggota Divisi
        SpJagaAnggota::where('sp_jaga_id', $spJaga->id)->delete();
        foreach ($request->anggotas as $idx => $a) {
            SpJagaAnggota::create([
                'sp_jaga_id' => $spJaga->id,
                'divisi_no' => $idx + 1,
                'tanggal_list_text' => $a['tanggal_list_text'] ?? '',
                'tanggal_array' => $a['tanggal_array'] ?? [],
                'anggota_items' => $a['anggota_items'] ?? [],
            ]);
        }

        return redirect()->route('sp-jaga.index')->with('success', 'Perubahan jadwal SP Jaga berhasil disimpan.');
    }

    /**
     * Persetujuan & TTE Digital oleh Pasops
     */
    public function approveTte(Request $request, $id)
    {
        $spJaga = SpJaga::with(['perwiras', 'anggotas'])->findOrFail($id);

        if ($spJaga->status !== 'pending_signature') {
            return back()->with('error', 'SP Jaga ini tidak sedang menunggu TTE.');
        }

        $this->sahkanTte($spJaga, auth()->user());

        return back()->with('success', 'Surat Perintah Jaga berhasil disahkan atas nama Pasiops & Notifikasi WA telah dikirimkan ke seluruh personel.');
    }

    /**
     * Tempelkan TTE Pasops (QR) pada SP: status terbit, waktu TTE = sekarang, lalu notifikasi in-app
     * dan WhatsApp ke seluruh personel. $oleh = yang menjalankan pengesahan (Pasops, atau pengaju revisi).
     */
    private function sahkanTte(SpJaga $spJaga, User $oleh): void
    {
        $pasopsUser = User::where('role', 'pasops')->first()
                   ?? User::where('name', 'like', '%Roni Sumantri%')->first()
                   ?? ($oleh->role === 'pasops' ? $oleh : null);

        $pName = $pasopsUser ? $pasopsUser->name : 'Roni Sumantri';
        $pRank = $pasopsUser ? $pasopsUser->pangkat : 'Mayor Laut (P)';
        $pNrp = ($pasopsUser && $pasopsUser->nrp) ? 'NRP ' . $pasopsUser->nrp : 'NRP 17456/P';

        $spJaga->update([
            // Revisi: tanggal surat = tanggal TTE (nomor tetap sama dengan SP asli).
            ...($spJaga->isRevisi() ? ['tanggal_surat' => now()->toDateString()] : []),
            'ttd_type' => 'tte',
            'status' => 'published',
            'approved_by' => $oleh->id,
            'approved_at' => now(),
            'penandatangan_user_id' => $pasopsUser?->id ?: $oleh->id,
            'penandatangan_nama' => $pName,
            'penandatangan_pangkat_nrp' => trim("{$pRank} {$pNrp}"),
            'penandatangan_jabatan' => 'Pasiops',
        ]);

        // Notifikasi in-app (web + aplikasi Android) untuk seluruh personel
        \App\Support\SpJagaNotifier::published($spJaga);

        // Kirim Broadcast Notifikasi WhatsApp ke Semua Personel Terdaftar
        $this->sendBroadcastWa($spJaga);
    }

    /**
     * Unggah Berkas PDF Scan TTD Basah (Manual)
     */
    public function uploadManualSigned(Request $request, $id)
    {
        $request->validate([
            'signed_file' => 'required|mimes:pdf|max:20480'
        ]);

        $spJaga = SpJaga::with(['perwiras', 'anggotas'])->findOrFail($id);

        $path = $request->file('signed_file')->storeAs(
            'sp_jaga_signed',
            'SP_JAGA_' . $spJaga->tahun . '_' . $spJaga->bulan . '_' . time() . '.pdf',
            'public'
        );

        $spJaga->update([
            'status' => 'published',
            'signed_file_path' => $path,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Kirim Broadcast Notifikasi WhatsApp ke Semua Personel Terdaftar
        $this->sendBroadcastWa($spJaga);

        return back()->with('success', 'Berkas PDF bertanda tangan basah berhasil diunggah & Notifikasi WA telah dikirimkan ke seluruh personel.');
    }

    /**
     * Cetak & Unduh PDF Resmi 3 Halaman
     */
    public function downloadPdf($id)
    {
        $spJaga = SpJaga::with(['perwiras', 'anggotas'])->findOrFail($id);

        // Jika mode manual dan sudah ada file scan fisik yang diupload
        if ($spJaga->ttd_type === 'manual' && $spJaga->signed_file_path && Storage::disk('public')->exists($spJaga->signed_file_path)) {
            return response()->download(storage_path('app/public/' . $spJaga->signed_file_path), "SP_JAGA_{$spJaga->tahun}_{$spJaga->bulan}.pdf");
        }

        // Generate QR Code TTD Image
        $qr_base64 = null;
        if ($spJaga->ttd_type === 'tte' && $spJaga->status === 'published' && $spJaga->verification_code) {
            try {
                $verifyUrl = route('doc.verify', $spJaga->verification_code);
                $qrData = @file_get_contents('https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($verifyUrl));
                if ($qrData) {
                    $qr_base64 = 'data:image/png;base64,' . base64_encode($qrData);
                }
            } catch (\Exception $e) {
                Log::warning("Gagal fetch QR image: " . $e->getMessage());
            }
        }

        // Ambil Data Pejabat Perwira Tertua (Pater / Penerima Perintah)
        $perwiraTertuaNama = $spJaga->perwira_tertua_nama ?: 'Indra Gunawan';
        $perwiraTertuaPangkatNrp = $spJaga->perwira_tertua_pangkat_nrp ?: 'Kapten Laut (P) NRP 19739/P';
        $perwiraTertuaJabatan = $spJaga->perwira_tertua_jabatan ?: 'Dan Unit 1 Lid Den Intel Kodaeral V';

        // Hitung personel pengikut (total dikurangi 1 perwira tertua)
        $totalPengikut = ($spJaga->total_personel_count > 1) ? ($spJaga->total_personel_count - 1) : 26;
        $totalPengikutTerbilang = $this->terbilangAngka($totalPengikut);

        // Format penerima Diktum Kepada
        if (stripos($perwiraTertuaPangkatNrp, 'NRP') !== false) {
            [$pangkatPart, $nrpPart] = explode('NRP', $perwiraTertuaPangkatNrp, 2);
            $pangkatPart = trim($pangkatPart);
            $nrpPart = trim($nrpPart);
            if ($pangkatPart && stripos($perwiraTertuaNama, $pangkatPart) === 0) {
                $penerimaDiktumKepada = $perwiraTertuaNama . ($nrpPart ? ' NRP ' . $nrpPart : '');
            } else {
                $penerimaDiktumKepada = ($pangkatPart ? $pangkatPart . ' ' : '') . $perwiraTertuaNama . ($nrpPart ? ' NRP ' . $nrpPart : '');
            }
        } else {
            $penerimaDiktumKepada = ($perwiraTertuaPangkatNrp ? $perwiraTertuaPangkatNrp . ' ' : '') . $perwiraTertuaNama;
        }

        // Kompatibilitas variabel lama
        $danunitPangkat = $perwiraTertuaPangkatNrp;
        $danunitNama = $perwiraTertuaNama;
        $danunitNrp = '';
        $danunitJabatan = $perwiraTertuaJabatan;

        // Ambil Data Pejabat Pasops / Pasiops dari Database Users (role: pasops)
        $pasopsUser = User::where('role', 'pasops')->first()
                   ?? User::where('role', 'like', '%pasops%')->first()
                   ?? User::where('role', 'like', '%pasiops%')->first()
                   ?? User::where('name', 'like', '%Roni%')->first();

        $pasiopsPangkat = $pasopsUser?->pangkat ?? 'Mayor Laut (P)';
        $pasiopsNama = $pasopsUser?->name ?? 'Roni Sumantri';
        $pasiopsNrp = $pasopsUser?->nrp ? 'NRP ' . $pasopsUser->nrp : 'NRP 17456/P';
        $pasiopsPangkatNrp = "$pasiopsPangkat $pasiopsNrp";

        // Generate PDF Dinamis 3 Halaman via DomPDF
        $pdf = Pdf::loadView('pdf.sp_jaga', compact(
            'spJaga', 
            'qr_base64', 
            'perwiraTertuaNama',
            'perwiraTertuaPangkatNrp',
            'perwiraTertuaJabatan',
            'penerimaDiktumKepada',
            'totalPengikut',
            'totalPengikutTerbilang',
            'danunitPangkat', 
            'danunitNama', 
            'danunitNrp', 
            'danunitJabatan',
            'pasiopsNama',
            'pasiopsPangkatNrp'
        ));
        $pdf->setPaper([0, 0, 609.45, 935.43], 'portrait'); // Ukuran Folio / F4

        $blnAngka = (int)$spJaga->bulan;
        $tahunAngka = (int)$spJaga->tahun;
        if (!empty($spJaga->tmt_mulai)) {
            $tmtCarbon = \Carbon\Carbon::parse($spJaga->tmt_mulai);
            $blnAngka = (int)$tmtCarbon->format('n');
            $tahunAngka = (int)$tmtCarbon->format('Y');
        }

        $filename = "SP_JAGA_" . strtoupper($this->getNamaBulanIndo($blnAngka) . '_' . $tahunAngka) . ".pdf";

        return $pdf->stream($filename);
    }

    /**
     * Hapus SP Jaga
     */
    public function destroy($id)
    {
        $spJaga = SpJaga::findOrFail($id);
        // Menghapus SP asli ikut menghapus seluruh revisinya.
        $daftar = $spJaga->isRevisi() ? collect([$spJaga]) : SpJaga::where('revisi_dari_id', $spJaga->id)->get()->push($spJaga);
        foreach ($daftar as $sp) {
            if ($sp->signed_file_path && Storage::disk('public')->exists($sp->signed_file_path)) {
                Storage::disk('public')->delete($sp->signed_file_path);
            }
            $sp->delete();
        }
        return back()->with('success', 'Data SP Jaga berhasil dihapus.');
    }

    /**
     * Broadcast WhatsApp Massal ke Seluruh Personel Terdaftar
     */
    private function sendBroadcastWa($spJaga)
    {
        $blnAngka = (int)$spJaga->bulan;
        $tahunAngka = (int)$spJaga->tahun;
        if (!empty($spJaga->tmt_mulai)) {
            $tmtCarbon = \Carbon\Carbon::parse($spJaga->tmt_mulai);
            $blnAngka = (int)$tmtCarbon->format('n');
            $tahunAngka = (int)$tmtCarbon->format('Y');
        }
        $namaBulanTahun = strtoupper($this->getNamaBulanIndo($blnAngka) . ' ' . $tahunAngka);
        $nomorLabel = $spJaga->nomor_sprin . ($spJaga->isRevisi() ? ' Revisi ke-' . $spJaga->revisi_ke : '');
        $notifiedPhones = [];

        // 1. Broadcast ke Perwira Jaga
        foreach ($spJaga->perwiras as $perwira) {
            $user = $perwira->user ?: User::where('name', $perwira->nama)->orWhere('nrp', $perwira->nrp)->first();
            $phone = $user?->phone;

            if ($phone && !in_array($phone, $notifiedPhones)) {
                $notifiedPhones[] = $phone;
                $dates = [];
                for ($i = 1; $i <= 7; $i++) {
                    $val = $perwira->{"tgl_{$i}"};
                    if (!empty($val) && $val !== '-') {
                        $dates[] = $val;
                    }
                }
                $tanggalText = implode(', ', $dates) . ' ' . $namaBulanTahun;

                $pesan = "*DETASEMEN INTELIJEN KODAERAL V*\n" .
                         "*PEMBERITAHUAN JADWAL JAGA SIAGA SINTEL*\n\n" .
                         "Yth. {$perwira->pangkat_korps} {$perwira->nama} (NRP: {$perwira->nrp})\n\n" .
                         "Diberitahukan bahwa Surat Perintah Jaga Siaga Sintel Bulan {$namaBulanTahun} ({$nomorLabel}) telah resmi diterbitkan.\n\n" .
                         "*Rincian Jadwal Jaga Anda:*\n" .
                         "- *Peran:* Perwira Siaga Sintel\n" .
                         "- *Tanggal Jaga:* {$tanggalText}\n" .
                         "- *Lokasi:* Kantor Sintel / Tim Intel Kodaeral V\n\n" .
                         "Untuk rincian jadwal lengkap dan mengunduh berkas PDF resmi, silakan login ke Portal SINDEN:\n" .
                         route('sp-jaga.index') . "\n\n" .
                         "Demikian untuk dipedomani dan dilaksanakan dengan penuh rasa tanggung jawab.";

                WhatsappService::sendMessage($phone, $pesan);
            }
        }

        // 2. Broadcast ke Anggota Jaga Divisi
        foreach ($spJaga->anggotas as $divisi) {
            $items = is_array($divisi->anggota_items) ? $divisi->anggota_items : json_decode($divisi->anggota_items, true) ?? [];
            foreach ($items as $item) {
                $user = !empty($item['user_id']) ? User::find($item['user_id']) : User::where('name', $item['nama'] ?? '')->orWhere('nrp', $item['nrp_nip'] ?? '')->first();
                $phone = $user?->phone;

                if ($phone && !in_array($phone, $notifiedPhones)) {
                    $notifiedPhones[] = $phone;
                    $roleLabel = ($item['role_jaga'] ?? 'ANGGOTA') === 'BAGA' ? 'Bintara Jaga (BAGA)' : 'Anggota Siaga Sintel';

                    $pesan = "*DETASEMEN INTELIJEN KODAERAL V*\n" .
                             "*PEMBERITAHUAN JADWAL JAGA SIAGA SINTEL*\n\n" .
                             "Yth. " . ($item['pangkat_korps'] ?? '') . " " . ($item['nama'] ?? '') . " (NRP/NIP: " . ($item['nrp_nip'] ?? '-') . ")\n\n" .
                             "Diberitahukan bahwa Surat Perintah Jaga Siaga Sintel Bulan {$namaBulanTahun} ({$nomorLabel}) telah resmi diterbitkan.\n\n" .
                             "*Rincian Jadwal Jaga Anda:*\n" .
                             "- *Peran:* {$roleLabel}\n" .
                             "- *Divisi:* Kelompok Divisi {$divisi->divisi_no}\n" .
                             "- *Tanggal Jaga:* {$divisi->tanggal_list_text}\n" .
                             "- *Lokasi:* Kantor Sintel / Tim Intel Kodaeral V\n\n" .
                             "Untuk rincian jadwal lengkap dan mengunduh berkas PDF resmi, silakan login ke Portal SINDEN:\n" .
                             route('sp-jaga.index') . "\n\n" .
                             "Demikian untuk dipedomani dan dilaksanakan dengan penuh rasa tanggung jawab.";

                    WhatsappService::sendMessage($phone, $pesan);
                }
            }
        }
    }
}