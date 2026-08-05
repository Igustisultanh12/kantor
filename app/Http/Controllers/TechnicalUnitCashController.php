<?php

namespace App\Http\Controllers;

use App\Models\TechnicalUnitCash;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class TechnicalUnitCashController extends Controller
{
    /**
     * Tampilkan Halaman Utama Buku Kas Dan Unit Teknis
     */
    public function index()
    {
        $user = auth()->user();
        $allowedRoles = ['admin', 'danunitteknis', 'DAN UNIT TEKNIS', 'dan unit teknis'];
        $allowedNames = [
            'I Gusti Sultan H.A, A.Md.Kom',
            'Suma Nurhasanah',
            'HARI BAGIO WIJAYANTO, M.TR.OPSLA'
        ];

        if (!in_array($user->role, $allowedRoles) && !in_array($user->name, $allowedNames)) {
            return redirect()->route('dashboard')->with('error', 'Akses ditolak! Halaman Buku Kas Dan Unit Teknis khusus untuk Role Dan Unit Teknis.');
        }

        $cashes = TechnicalUnitCash::orderBy('date', 'asc')->orderBy('id', 'asc')->get()->map(function($cash) {
            $urls = [];
            $rawPath = $cash->receipt_path;
            
            if (!empty($rawPath)) {
                $decoded = json_decode($rawPath, true);
                
                if (is_array($decoded)) {
                    foreach ($decoded as $path) {
                        if (!empty($path)) {
                            $urls[] = [
                                'url' => asset('storage/' . $path),
                                'is_pdf' => str_ends_with(strtolower($path), '.pdf')
                            ];
                        }
                    }
                } else {
                    $urls[] = [
                        'url' => asset('storage/' . $rawPath),
                        'is_pdf' => str_ends_with(strtolower($rawPath), '.pdf')
                    ];
                }
            }

            return [
                'id' => $cash->id,
                'date' => (string)($cash->getRawOriginal('date') ?? $cash->date),
                'description' => $cash->description,
                'debit' => (float)($cash->debit ?? 0),
                'credit' => (float)($cash->credit ?? 0),
                'balance' => (float)($cash->balance ?? 0),
                'receipt_path' => $rawPath,
                'receipt_urls' => $urls 
            ];
        });

        $totalSaldo = TechnicalUnitCash::latest('id')->first()?->balance ?? 0;

        return Inertia::render('TechnicalUnitCash/Index', [
            'cashes' => $cashes,
            'totalSaldo' => $totalSaldo
        ]);
    }

    /**
     * Tambah Transaksi Kas Dan Unit Teknis
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'description' => 'required',
            'debit' => 'numeric|min:0',
            'credit' => 'numeric|min:0',
            'receipt_files.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:153600', 
        ]);

        $lastBalance = TechnicalUnitCash::latest('id')->first()?->balance ?? 0;
        $currentBalance = $lastBalance + ($request->debit ?? 0) - ($request->credit ?? 0);

        $storedPaths = [];
        if ($request->hasFile('receipt_files')) {
            foreach ($request->file('receipt_files') as $file) {
                $storedPaths[] = $file->store('receipts_technical_unit', 'public');
            }
        }

        $finalPathValue = count($storedPaths) > 0 ? json_encode($storedPaths) : null;
        $dateFormatted = is_string($request->date) ? substr($request->date, 0, 10) : $request->date;

        TechnicalUnitCash::create([
            'date' => $dateFormatted,
            'description' => $request->description,
            'debit' => $request->debit ?? 0,
            'credit' => $request->credit ?? 0,
            'balance' => $currentBalance,
            'receipt_path' => $finalPathValue 
        ]);

        return back()->with('success', 'Transaksi Kas Dan Unit Teknis berhasil dicatat.');
    }

    /**
     * Update Transaksi & Rekalkulasi Saldo Berkelanjutan
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'date' => 'required|date',
            'description' => 'required',
            'debit' => 'numeric|min:0',
            'credit' => 'numeric|min:0',
            'receipt_files.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:153600',
        ]);

        $cash = TechnicalUnitCash::findOrFail($id);
        $updateData = $request->only(['description', 'debit', 'credit']);
        $updateData['date'] = is_string($request->date) ? substr($request->date, 0, 10) : $request->date;

        if ($request->hasFile('receipt_files')) {
            $oldPathRaw = $cash->getRawOriginal('receipt_path') ?? $cash->receipt_path;
            $oldDecoded = is_string($oldPathRaw) ? json_decode($oldPathRaw, true) : $oldPathRaw;

            if (is_array($oldDecoded)) {
                foreach ($oldDecoded as $oldFile) {
                    if ($oldFile && Storage::disk('public')->exists($oldFile)) {
                        Storage::disk('public')->delete($oldFile);
                    }
                }
            } elseif ($oldPathRaw && Storage::disk('public')->exists($oldPathRaw)) {
                Storage::disk('public')->delete($oldPathRaw);
            }

            $newPaths = [];
            foreach ($request->file('receipt_files') as $file) {
                $newPaths[] = $file->store('receipts_technical_unit', 'public');
            }
            $updateData['receipt_path'] = json_encode($newPaths);
        }

        $cash->update($updateData);

        // Rekalkulasi Seluruh Saldo Berjalan
        $this->recalculateBalances();

        return back()->with('success', 'Transaksi Kas Dan Unit Teknis berhasil diperbarui.');
    }

    /**
     * Hapus Transaksi & Rekalkulasi Saldo
     */
    public function destroy($id)
    {
        $cash = TechnicalUnitCash::findOrFail($id);

        $oldPathRaw = $cash->getRawOriginal('receipt_path') ?? $cash->receipt_path;
        $oldDecoded = is_string($oldPathRaw) ? json_decode($oldPathRaw, true) : $oldPathRaw;

        if (is_array($oldDecoded)) {
            foreach ($oldDecoded as $oldFile) {
                if ($oldFile && Storage::disk('public')->exists($oldFile)) {
                    Storage::disk('public')->delete($oldFile);
                }
            }
        } elseif ($oldPathRaw && Storage::disk('public')->exists($oldPathRaw)) {
            Storage::disk('public')->delete($oldPathRaw);
        }

        $cash->delete();
        $this->recalculateBalances();

        return back()->with('success', 'Transaksi Kas Dan Unit Teknis berhasil dihapus.');
    }

    /**
     * Helper Rekalkulasi Saldo Kumulatif
     */
    private function recalculateBalances()
    {
        $all = TechnicalUnitCash::orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        $runningBalance = 0;

        foreach ($all as $item) {
            $runningBalance += ($item->debit - $item->credit);
            $item->balance = $runningBalance;
            $item->save();
        }
    }
}
