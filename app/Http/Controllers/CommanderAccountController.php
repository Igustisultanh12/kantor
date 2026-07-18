<?php

namespace App\Http\Controllers;

use App\Models\CommanderAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;// Pastikan package PDF terpasang aman seperti di kas buku kas

class CommanderAccountController extends Controller
{
    /**
     * Memeriksa hak akses logistik keuangan Komandan
     */
    private function checkAccess()
    {
        $user = Auth::user();
        // Komandan terdeteksi bypass nama khusus atau role khusus
        $isCommander = $user->name === 'I Gusti Sultan H.A, A.Md.Kom' || $user->role === 'komandan';
        $isAdminOrSuma = in_array($user->role, ['admin', 'user']);

        if (!$isCommander && !$isAdminOrSuma) {
            abort(403, 'Anda tidak memiliki otoritas mengakses rekening Komandan.');
        }

        return [
            'canEdit' => $isAdminOrSuma,
            'user' => $user
        ];
    }

    public function index()
    {
        $access = $this->checkAccess();
        
        $logs = CommanderAccount::latest('tanggal')->latest('id')->get();
        
        // Kalkulasi saldo mutasi radar
        $totalMasuk = CommanderAccount::where('jenis', 'MASUK')->sum('jumlah');
        $totalKeluar = CommanderAccount::where('jenis', 'KELUAR')->sum('jumlah');
        $saldoAkhir = $totalMasuk - $totalKeluar;

        return Inertia::render('CommanderAccount/Index', [
            'logs' => $logs,
            'canEdit' => $access['canEdit'],
            'stats' => [
                'total_masuk' => $totalMasuk,
                'total_keluar' => $totalKeluar,
                'saldo_akhir' => $saldoAkhir,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $access = $this->checkAccess();
        if (!$access['canEdit']) abort(403, 'Akses ditolak.');

        $request->validate([
            'tanggal' => 'required|date',
            'keterangan' => 'required|string|max:255',
            'jenis' => 'required|in:MASUK,KELUAR',
            'jumlah' => 'required|numeric|min:0',
        ]);

        CommanderAccount::create([
            'tanggal' => $request->tanggal,
            'keterangan' => strtoupper($request->keterangan),
            'jenis' => $request->jenis,
            'jumlah' => $request->jumlah,
            'petugas_input' => $access['user']->name,
        ]);

        return back()->with('success', 'Lapor! Mutasi rekening Komandan berhasil dicatat.');
    }

    public function update(Request $request, $id)
    {
        $access = $this->checkAccess();
        if (!$access['canEdit']) abort(403, 'Akses ditolak.');

        $request->validate([
            'tanggal' => 'required|date',
            'keterangan' => 'required|string|max:255',
            'jenis' => 'required|in:MASUK,KELUAR',
            'jumlah' => 'required|numeric|min:0',
        ]);

        $log = CommanderAccount::findOrFail($id);
        $log->update([
            'tanggal' => $request->tanggal,
            'keterangan' => strtoupper($request->keterangan),
            'jenis' => $request->jenis,
            'jumlah' => $request->jumlah,
            'petugas_input' => $access['user']->name . ' (EDIT)',
        ]);

        return back()->with('success', 'Lapor! Identitas transaksi berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $access = $this->checkAccess();
        if (!$access['canEdit']) abort(403, 'Akses ditolak.');

        CommanderAccount::destroy($id);
        return back()->with('success', 'Lapor! Transaksi telah dihapus dari log pangkalan.');
    }

    public function exportPdf()
    {
        $this->checkAccess();
        $logs = CommanderAccount::orderBy('tanggal', 'asc')->get();
        
        $totalMasuk = CommanderAccount::where('jenis', 'MASUK')->sum('jumlah');
        $totalKeluar = CommanderAccount::where('jenis', 'KELUAR')->sum('jumlah');
        $saldo = $totalMasuk - $totalKeluar;

        $pdf = PDF::loadView('pdf.commander_cash', compact('logs', 'totalMasuk', 'totalKeluar', 'saldo'))
                  ->setPaper('a4', 'portrait');
                  
        return $pdf->stream('LAP_REKENING_KOMANDAN_' . date('Y') . '.pdf');
    }
}