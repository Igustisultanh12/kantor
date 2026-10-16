<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\IbuBetiAccountController;
use App\Models\IbuBetiAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * API mobile Rekening Ibu Beti. Hak akses (admin, developer, atau personel
 * yang diberi akses "Rekening Ibu Beti" di Kelola Pengguna web) ditegakkan
 * oleh middleware `mobile.auth:ibu_beti.*`. Tambah/ubah/hapus/PDF memakai
 * IbuBetiAccountController milik web agar audit log & format tetap sama.
 */
class MobileIbuBetiController extends Controller
{
    /** Mutasi + saldo awal/akhir per bulan (sama dengan halaman web). */
    public function index(Request $request)
    {
        try {
            $query = IbuBetiAccount::query();
            if ($request->filled('search')) {
                $query->where('keterangan', 'like', '%' . $request->input('search') . '%');
            }
            if (in_array($request->input('jenis'), ['MASUK', 'KELUAR'], true)) {
                $query->where('jenis', $request->input('jenis'));
            }

            $totalMasukAll = (float) IbuBetiAccount::where('jenis', 'MASUK')->sum('jumlah');
            $totalKeluarAll = (float) IbuBetiAccount::where('jenis', 'KELUAR')->sum('jumlah');
            $saldoAwal = 0.0;
            $monthly = $request->filled('month') && preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month'));

            if ($monthly) {
                $start = Carbon::parse($request->input('month') . '-01')->startOfMonth();
                $from = $start->toDateString();
                $to = $start->copy()->endOfMonth()->toDateString();
                $saldoAwal = (float) IbuBetiAccount::where('tanggal', '<', $from)->where('jenis', 'MASUK')->sum('jumlah')
                    - (float) IbuBetiAccount::where('tanggal', '<', $from)->where('jenis', 'KELUAR')->sum('jumlah');
                $query->whereBetween('tanggal', [$from, $to]);
                $totalMasuk = (float) (clone $query)->where('jenis', 'MASUK')->sum('jumlah');
                $totalKeluar = (float) (clone $query)->where('jenis', 'KELUAR')->sum('jumlah');
                $saldoAkhir = $saldoAwal + $totalMasuk - $totalKeluar;
            } else {
                $totalMasuk = $totalMasukAll;
                $totalKeluar = $totalKeluarAll;
                $saldoAkhir = $totalMasukAll - $totalKeluarAll;
            }

            $running = $saldoAwal;
            $rows = [];
            foreach ((clone $query)->orderBy('tanggal')->orderBy('id')->get() as $item) {
                $running += $item->jenis === 'MASUK' ? (float) $item->jumlah : -(float) $item->jumlah;
                $rows[] = [
                    'id' => $item->id,
                    'tanggal' => $item->tanggal ? $item->tanggal->format('Y-m-d') : null,
                    'keterangan' => $item->keterangan,
                    'jenis' => $item->jenis,
                    'jumlah' => (float) $item->jumlah,
                    'saldo_berjalan' => $running,
                    'has_bukti' => !empty($item->bukti),
                    'bukti_is_pdf' => $item->bukti ? str_ends_with(strtolower($item->bukti), '.pdf') : false,
                    'petugas_input' => $item->petugas_input,
                    'created_at' => $item->created_at?->format('Y-m-d H:i'),
                ];
            }

            return response()->json([
                'status' => 'success',
                'saldo_awal' => $saldoAwal,
                'total_masuk' => $totalMasuk,
                'total_keluar' => $totalKeluar,
                'saldo_akhir' => $saldoAkhir,
                'saldo_keseluruhan' => $totalMasukAll - $totalKeluarAll,
                'is_monthly' => $monthly,
                'data' => array_reverse($rows), // terbaru di atas
            ]);
        } catch (\Throwable $e) {
            return mobile_error($e, 'Gagal memuat Rekening Ibu Beti.');
        }
    }

    public function store(Request $request)
    {
        return mobile_delegate(
            fn () => app(IbuBetiAccountController::class)->store($request),
            'Mutasi Rekening Ibu Beti berhasil dicatat.'
        );
    }

    public function update(Request $request, int $id)
    {
        IbuBetiAccount::findOrFail($id);
        return mobile_delegate(
            fn () => app(IbuBetiAccountController::class)->update($request, $id),
            'Mutasi Rekening Ibu Beti berhasil diperbarui.'
        );
    }

    public function destroy(int $id)
    {
        IbuBetiAccount::findOrFail($id);
        return mobile_delegate(
            fn () => app(IbuBetiAccountController::class)->destroy($id),
            'Mutasi Rekening Ibu Beti berhasil dihapus.'
        );
    }

    /** Bukti transaksi (foto/PDF) — hanya untuk pemegang akses. */
    public function bukti(int $id)
    {
        $item = IbuBetiAccount::findOrFail($id);
        $path = (string) $item->bukti;
        if ($path === '' || str_contains($path, '..') || !Storage::disk('public')->exists($path)) {
            return response()->json(['status' => 'error', 'message' => 'Bukti transaksi tidak ditemukan.'], 404);
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $type = match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            default => 'image/jpeg',
        };
        return response(Storage::disk('public')->get($path), 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'inline; filename="BUKTI_IBU_BETI_' . $item->id . '.' . $ext . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Rekening koran PDF (format web). */
    public function pdf(Request $request)
    {
        $request->validate(['month' => ['nullable', 'regex:/^\d{4}-\d{2}$/']]);
        try {
            $response = app(IbuBetiAccountController::class)->exportPdf($request);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage() ?: 'Akses ditolak.'], $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('[MOBILE_PDF] Ibu Beti: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal membuat rekening koran PDF.'], 500);
        }
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
