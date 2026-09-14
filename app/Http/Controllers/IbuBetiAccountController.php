<?php

namespace App\Http\Controllers;

use App\Models\IbuBetiAccount;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class IbuBetiAccountController extends Controller
{
    /**
     * Memeriksa otorisasi hak akses Rekening Ibu Beti
     */
    private function checkAccess()
    {
        $user = Auth::user();
        $isSuper = $user->role === 'admin' || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';
        $hasPermission = (bool)$user->can_access_ibu_beti;

        if (!$isSuper && !$hasPermission) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki otoritas untuk mengakses Rekening Ibu Beti.');
        }

        return [
            'user' => $user,
            'isSuper' => $isSuper,
        ];
    }

    /**
     * Menampilkan daftar mutasi dan pembukuan Rekening Ibu Beti
     */
    public function index(Request $request)
    {
        $access = $this->checkAccess();

        // Ambil daftar bulan yang tersedia dari database transaksi
        $availableMonths = IbuBetiAccount::selectRaw("DISTINCT DATE_FORMAT(tanggal, '%Y-%m') as ym")
            ->orderBy('ym', 'desc')
            ->pluck('ym')
            ->toArray();

        $currentYm = date('Y-m');
        if (!in_array($currentYm, $availableMonths)) {
            array_unshift($availableMonths, $currentYm);
        }

        $query = IbuBetiAccount::query();

        // Filter Pencarian Keterangan
        if ($request->filled('search')) {
            $query->where('keterangan', 'like', '%' . $request->search . '%');
        }

        // Filter Jenis Mutasi
        if ($request->filled('jenis') && in_array($request->jenis, ['MASUK', 'KELUAR'])) {
            $query->where('jenis', $request->jenis);
        }

        $saldoAwal = 0;
        $totalMasukKeseluruhan = (float) IbuBetiAccount::where('jenis', 'MASUK')->sum('jumlah');
        $totalKeluarKeseluruhan = (float) IbuBetiAccount::where('jenis', 'KELUAR')->sum('jumlah');
        $saldoKeseluruhan = $totalMasukKeseluruhan - $totalKeluarKeseluruhan;

        if ($request->filled('month') && preg_match('/^\d{4}-\d{2}$/', $request->month)) {
            $carbon = \Carbon\Carbon::parse($request->month . '-01');
            $startOfMonth = $carbon->copy()->startOfMonth()->toDateString();
            $endOfMonth = $carbon->copy()->endOfMonth()->toDateString();

            // Saldo Awal sebelum bulan ini
            $prevMasuk = (float) IbuBetiAccount::where('tanggal', '<', $startOfMonth)->where('jenis', 'MASUK')->sum('jumlah');
            $prevKeluar = (float) IbuBetiAccount::where('tanggal', '<', $startOfMonth)->where('jenis', 'KELUAR')->sum('jumlah');
            $saldoAwal = $prevMasuk - $prevKeluar;

            $query->whereBetween('tanggal', [$startOfMonth, $endOfMonth]);

            $totalMasukPeriode = (float) (clone $query)->where('jenis', 'MASUK')->sum('jumlah');
            $totalKeluarPeriode = (float) (clone $query)->where('jenis', 'KELUAR')->sum('jumlah');
            $totalMasuk = $totalMasukPeriode;
            $totalKeluar = $totalKeluarPeriode;
            $saldoAkhir = $saldoAwal + $totalMasukPeriode - $totalKeluarPeriode;
        } else {
            $totalMasuk = $totalMasukKeseluruhan;
            $totalKeluar = $totalKeluarKeseluruhan;
            $saldoAkhir = $saldoKeseluruhan;
        }

        // Hitung Saldo Berjalan (Running Balance)
        $runningBalance = $saldoAwal;
        $periodLogs = (clone $query)->orderBy('tanggal', 'asc')->orderBy('id', 'asc')->get();
        $mapped = [];

        foreach ($periodLogs as $item) {
            if ($item->jenis === 'MASUK') {
                $runningBalance += (float) $item->jumlah;
            } else {
                $runningBalance -= (float) $item->jumlah;
            }

            $mapped[] = [
                'id' => $item->id,
                'tanggal' => $item->tanggal ? $item->tanggal->format('Y-m-d') : null,
                'keterangan' => $item->keterangan,
                'jenis' => $item->jenis,
                'jumlah' => (float) $item->jumlah,
                'saldo_berjalan' => $runningBalance,
                'bukti' => $item->bukti ? asset('storage/' . $item->bukti) : null,
                'bukti_path' => $item->bukti,
                'is_pdf' => $item->bukti ? str_ends_with(strtolower($item->bukti), '.pdf') : false,
                'petugas_input' => $item->petugas_input,
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i') : null,
            ];
        }

        // Urutkan transaksi terbaru di atas untuk antarmuka tabel web
        $logs = array_reverse($mapped);

        return Inertia::render('IbuBetiAccount/Index', [
            'logs' => $logs,
            'available_months' => $availableMonths,
            'stats' => [
                'saldo_awal' => (float) $saldoAwal,
                'total_masuk' => (float) $totalMasuk,
                'total_keluar' => (float) $totalKeluar,
                'saldo_akhir' => (float) $saldoAkhir,
                'saldo_keseluruhan' => (float) $saldoKeseluruhan,
                'is_monthly' => $request->filled('month'),
            ],
            'filters' => [
                'search' => $request->search ?? '',
                'jenis' => $request->jenis ?? '',
                'month' => $request->month ?? '',
            ],
        ]);
    }

    /**
     * Mencatat mutasi transaksi baru pada Rekening Ibu Beti
     */
    public function store(Request $request)
    {
        $access = $this->checkAccess();

        $request->validate([
            'tanggal' => 'required|date',
            'keterangan' => 'required|string|max:255',
            'jenis' => 'required|in:MASUK,KELUAR',
            'jumlah' => 'required|numeric|min:0',
            'bukti' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], [
            'tanggal.required' => 'Tanggal transaksi wajib diisi.',
            'keterangan.required' => 'Keterangan mutasi wajib diisi.',
            'jenis.required' => 'Jenis mutasi (MASUK/KELUAR) wajib dipilih.',
            'jumlah.required' => 'Nominal jumlah transaksi wajib diisi.',
            'bukti.mimes' => 'Bukti transaksi harus berformat JPG, PNG, atau PDF.',
            'bukti.max' => 'Ukuran berkas bukti maksimal 5 MB.',
        ]);

        $buktiPath = null;
        if ($request->hasFile('bukti')) {
            $buktiPath = $request->file('bukti')->store('ibu_beti_receipts', 'public');
        }

        $account = IbuBetiAccount::create([
            'tanggal' => $request->tanggal,
            'keterangan' => strtoupper($request->keterangan),
            'jenis' => $request->jenis,
            'jumlah' => $request->jumlah,
            'bukti' => $buktiPath,
            'petugas_input' => $access['user']->name,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'admin_name' => auth()->user()->name,
            'action' => 'CATAT REKENING IBU BETI',
            'target_personnel' => 'Rekening Ibu Beti',
            'description' => "Mencatat mutasi {$request->jenis} sebesar Rp " . number_format($request->jumlah, 0, ',', '.') . " ({$request->keterangan})",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Lapor! Mutasi transaksi Rekening Ibu Beti berhasil dicatat.');
    }

    /**
     * Memperbarui catatan mutasi transaksi
     */
    public function update(Request $request, $id)
    {
        $access = $this->checkAccess();

        $request->validate([
            'tanggal' => 'required|date',
            'keterangan' => 'required|string|max:255',
            'jenis' => 'required|in:MASUK,KELUAR',
            'jumlah' => 'required|numeric|min:0',
            'bukti' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $item = IbuBetiAccount::findOrFail($id);

        $updateData = [
            'tanggal' => $request->tanggal,
            'keterangan' => strtoupper($request->keterangan),
            'jenis' => $request->jenis,
            'jumlah' => $request->jumlah,
            'petugas_input' => $access['user']->name . ' (EDIT)',
        ];

        if ($request->hasFile('bukti')) {
            // Hapus bukti lama jika ada
            if ($item->bukti && Storage::disk('public')->exists($item->bukti)) {
                Storage::disk('public')->delete($item->bukti);
            }
            $updateData['bukti'] = $request->file('bukti')->store('ibu_beti_receipts', 'public');
        }

        $item->update($updateData);

        AuditLog::create([
            'user_id' => auth()->id(),
            'admin_name' => auth()->user()->name,
            'action' => 'UPDATE REKENING IBU BETI',
            'target_personnel' => 'Rekening Ibu Beti',
            'description' => "Memperbarui mutasi ID #{$id} ({$request->keterangan})",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Lapor! Identitas transaksi Rekening Ibu Beti berhasil diperbarui.');
    }

    /**
     * Menghapus catatan transaksi
     */
    public function destroy($id)
    {
        $access = $this->checkAccess();

        $item = IbuBetiAccount::findOrFail($id);

        if ($item->bukti && Storage::disk('public')->exists($item->bukti)) {
            Storage::disk('public')->delete($item->bukti);
        }

        $keterangan = $item->keterangan;
        $jumlah = $item->jumlah;
        $item->delete();

        AuditLog::create([
            'user_id' => auth()->id(),
            'admin_name' => auth()->user()->name,
            'action' => 'HAPUS REKENING IBU BETI',
            'target_personnel' => 'Rekening Ibu Beti',
            'description' => "Menghapus mutasi ID #{$id}: {$keterangan} (Rp " . number_format($jumlah, 0, ',', '.') . ")",
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', 'Lapor! Transaksi telah dihapus dari log Rekening Ibu Beti.');
    }

    /**
     * Cetak Laporan Rekapitulasi Format PDF Rekening Koran (Bank Statement)
     */
    public function exportPdf(Request $request)
    {
        $this->checkAccess();
        $month = $request->query('month');

        $saldoAwal = 0;
        $periodLabel = 'Semua Periode Transaksi';
        $periodDates = 'Keseluruhan Log Mutasi';

        $monthsIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $carbon = \Carbon\Carbon::parse($month . '-01');
            $startOfMonth = $carbon->copy()->startOfMonth()->toDateString();
            $endOfMonth = $carbon->copy()->endOfMonth()->toDateString();

            $periodLabel = strtoupper($monthsIndo[(int)$carbon->format('n')] . ' ' . $carbon->format('Y'));
            $periodDates = '01 ' . $monthsIndo[(int)$carbon->format('n')] . ' ' . $carbon->format('Y') . ' s/d ' . $carbon->copy()->endOfMonth()->format('d') . ' ' . $monthsIndo[(int)$carbon->format('n')] . ' ' . $carbon->format('Y');

            // Saldo Awal sebelum bulan ini
            $prevMasuk = (float) IbuBetiAccount::where('tanggal', '<', $startOfMonth)->where('jenis', 'MASUK')->sum('jumlah');
            $prevKeluar = (float) IbuBetiAccount::where('tanggal', '<', $startOfMonth)->where('jenis', 'KELUAR')->sum('jumlah');
            $saldoAwal = $prevMasuk - $prevKeluar;

            $rawLogs = IbuBetiAccount::whereBetween('tanggal', [$startOfMonth, $endOfMonth])
                ->orderBy('tanggal', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $totalMasuk = (float) IbuBetiAccount::whereBetween('tanggal', [$startOfMonth, $endOfMonth])->where('jenis', 'MASUK')->sum('jumlah');
            $totalKeluar = (float) IbuBetiAccount::whereBetween('tanggal', [$startOfMonth, $endOfMonth])->where('jenis', 'KELUAR')->sum('jumlah');
        } else {
            $rawLogs = IbuBetiAccount::orderBy('tanggal', 'asc')->orderBy('id', 'asc')->get();
            $totalMasuk = (float) IbuBetiAccount::where('jenis', 'MASUK')->sum('jumlah');
            $totalKeluar = (float) IbuBetiAccount::where('jenis', 'KELUAR')->sum('jumlah');
        }

        // Kalkulasi Saldo Berjalan (Running Balance) untuk tiap baris
        $runningBalance = $saldoAwal;
        $logs = [];
        $countMasuk = 0;
        $countKeluar = 0;

        foreach ($rawLogs as $item) {
            if ($item->jenis === 'MASUK') {
                $runningBalance += (float) $item->jumlah;
                $countMasuk++;
            } else {
                $runningBalance -= (float) $item->jumlah;
                $countKeluar++;
            }
            $item->saldo_berjalan = $runningBalance;
            $logs[] = $item;
        }

        $saldoAkhir = $saldoAwal + $totalMasuk - $totalKeluar;
        $accountNo = 'SINDEN-BETI-02';
        $accountHolder = 'REKENING IBU BETI';

        $pdf = Pdf::loadView('pdf.ibu_beti_cash', compact(
            'logs', 'totalMasuk', 'totalKeluar', 'saldoAwal', 'saldoAkhir',
            'periodLabel', 'periodDates', 'month', 'accountNo', 'accountHolder',
            'countMasuk', 'countKeluar'
        ))->setPaper('a4', 'portrait');

        $filename = 'REKENING_KORAN_IBU_BETI_' . ($month ? str_replace('-', '', $month) : date('Ymd_His')) . '.pdf';
        return $pdf->stream($filename);
    }
}
