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

        $query = IbuBetiAccount::query();

        // Filter Pencarian Keterangan
        if ($request->filled('search')) {
            $query->where('keterangan', 'like', '%' . $request->search . '%');
        }

        // Filter Jenis Mutasi
        if ($request->filled('jenis') && in_array($request->jenis, ['MASUK', 'KELUAR'])) {
            $query->where('jenis', $request->jenis);
        }

        // Filter Bulan/Tahun
        if ($request->filled('month')) {
            $query->whereMonth('tanggal', date('m', strtotime($request->month)))
                  ->whereYear('tanggal', date('Y', strtotime($request->month)));
        }

        $logs = (clone $query)->orderBy('tanggal', 'desc')->orderBy('id', 'desc')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'tanggal' => $item->tanggal ? $item->tanggal->format('Y-m-d') : null,
                'keterangan' => $item->keterangan,
                'jenis' => $item->jenis,
                'jumlah' => (float)$item->jumlah,
                'bukti' => $item->bukti ? asset('storage/' . $item->bukti) : null,
                'bukti_path' => $item->bukti,
                'is_pdf' => $item->bukti ? str_ends_with(strtolower($item->bukti), '.pdf') : false,
                'petugas_input' => $item->petugas_input,
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i') : null,
            ];
        });

        // Kalkulasi Statistik Keseluruhan
        $totalMasuk = IbuBetiAccount::where('jenis', 'MASUK')->sum('jumlah');
        $totalKeluar = IbuBetiAccount::where('jenis', 'KELUAR')->sum('jumlah');
        $saldoAkhir = $totalMasuk - $totalKeluar;

        return Inertia::render('IbuBetiAccount/Index', [
            'logs' => $logs,
            'stats' => [
                'total_masuk' => (float)$totalMasuk,
                'total_keluar' => (float)$totalKeluar,
                'saldo_akhir' => (float)$saldoAkhir,
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
     * Cetak Laporan Rekapitulasi Format PDF Resmi Kedinasan
     */
    public function exportPdf(Request $request)
    {
        $this->checkAccess();

        $query = IbuBetiAccount::orderBy('tanggal', 'asc')->orderBy('id', 'asc');

        if ($request->filled('month')) {
            $query->whereMonth('tanggal', date('m', strtotime($request->month)))
                  ->whereYear('tanggal', date('Y', strtotime($request->month)));
        }

        $logs = $query->get();

        $totalMasuk = (clone $query)->where('jenis', 'MASUK')->sum('jumlah');
        $totalKeluar = (clone $query)->where('jenis', 'KELUAR')->sum('jumlah');
        $saldo = $totalMasuk - $totalKeluar;

        $pdf = Pdf::loadView('pdf.ibu_beti_cash', compact('logs', 'totalMasuk', 'totalKeluar', 'saldo'))
                  ->setPaper('a4', 'portrait');

        return $pdf->stream('LAPORAN_REKENING_IBU_BETI_' . date('Ymd_His') . '.pdf');
    }
}
