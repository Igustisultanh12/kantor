<?php

namespace App\Http\Controllers;

use App\Models\Cash;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Storage; // DI PERTAHANKAN: Diperlukan untuk manajemen penghapusan file nota

class CashController extends Controller
{
    public function index()
    {
        // Otoritas khusus Admin & Suma
        $user = auth()->user();
        if ($user->role !== 'admin' && !in_array($user->name, ['I Gusti Sultan H.A, A.Md.Kom', 'Suma Nurhasanah','HARI BAGIO WIJAYANTO, M.TR.OPSLA'])) {
            return abort(403, 'Anda tidak memiliki otoritas akses kas.');
        }

        // AMAN: Deteksi berlapis demi menjinakkan data lama string tunggal maupun data array baru
        $cashes = Cash::orderBy('date', 'asc')->orderBy('id', 'asc')->get()->map(function($cash) {
            $urls = [];
            $rawPath = $cash->receipt_path;
            
            if (!empty($rawPath)) {
                // Coba deteksi apakah data tersimpan sebagai format JSON array
                $decoded = json_decode($rawPath, true);
                
                if (is_array($decoded)) {
                    // Skenario A: Jika berhasil didecode sebagai array (Multi-images upload baru)
                    foreach ($decoded as $path) {
                        if (!empty($path)) {
                            $urls[] = [
                                'url' => asset('storage/' . $path),
                                'is_pdf' => str_ends_with(strtolower($path), '.pdf')
                            ];
                        }
                    }
                } elseif (is_array($rawPath)) {
                    // Skenario B: Antisipasi jika model sudah terlanjur melakukan auto-casting array
                    foreach ($rawPath as $path) {
                        if (!empty($path)) {
                            $urls[] = [
                                'url' => asset('storage/' . $path),
                                'is_pdf' => str_ends_with(strtolower($path), '.pdf')
                            ];
                        }
                    }
                } else {
                    // Skenario C: Jika murni teks string tunggal biasa (Data transisi lama)
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

        $totalSaldo = Cash::latest('id')->first()?->balance ?? 0;

        return Inertia::render('Cash/Index', [
            'cashes' => $cashes,
            'totalSaldo' => $totalSaldo
        ]);
    }

    public function store(Request $request)
    {
        // Validasi array file nota dengan kapasitas pagu total 150MB
        $request->validate([
            'date' => 'required|date',
            'description' => 'required',
            'debit' => 'numeric|min:0',
            'credit' => 'numeric|min:0',
            'receipt_files.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:153600', 
        ]);

        $lastBalance = Cash::latest('id')->first()?->balance ?? 0;
        $currentBalance = $lastBalance + $request->debit - $request->credit;

        // Loop penyimpanan koleksi multi-berkas gambar/PDF
        $storedPaths = [];
        if ($request->hasFile('receipt_files')) {
            foreach ($request->file('receipt_files') as $file) {
                $storedPaths[] = $file->store('receipts', 'public');
            }
        }

        // PERBAIKAN: Paksa konversi array menjadi string JSON murni secara manual agar database tidak memicu error 500
        $finalPathValue = count($storedPaths) > 0 ? json_encode($storedPaths) : null;

        $dateFormatted = is_string($request->date) ? substr($request->date, 0, 10) : $request->date;

        Cash::create([
            'date' => $dateFormatted,
            'description' => $request->description,
            'debit' => $request->debit ?? 0,
            'credit' => $request->credit ?? 0,
            'balance' => $currentBalance,
            'receipt_path' => $finalPathValue 
        ]);

        $user = auth()->user();
        $typeStr = ($request->debit > 0) ? "Pemasukan (Debit) Rp " . number_format($request->debit) : "Pengeluaran (Kredit) Rp " . number_format($request->credit);
        AppNotification::notify(
            null,
            'admin',
            'Transaksi Kas Unit Baru',
            "Pencatatan {$typeStr} perihal \"{$request->description}\" oleh " . ($user?->name ?? 'Sistem') . ".",
            'success',
            '/cash'
        );

        return back()->with('success', 'Transaksi Kas berhasil dicatat.');
    }

    /**
     * UPDATE TRANSAKSI & REKALKULASI SALDO
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

        $cash = Cash::findOrFail($id);
        $updateData = $request->only(['description', 'debit', 'credit']);
        $updateData['date'] = is_string($request->date) ? substr($request->date, 0, 10) : $request->date;

        // Jika ada unggahan kumpulan dokumen nota baru, bersihkan berkas lama
        if ($request->hasFile('receipt_files')) {
            // Bersihkan data lama baik berupa array maupun string tunggal
            $oldPathRaw = $cash->getRawOriginal('receipt_path') ?? $cash->receipt_path;
            $oldDecoded = is_string($oldPathRaw) ? json_decode($oldPathRaw, true) : $oldPathRaw;

            if (is_array($oldDecoded)) {
                foreach ($oldDecoded as $oldPath) {
                    if (!empty($oldPath) && Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
            } elseif (is_array($oldPathRaw)) {
                foreach ($oldPathRaw as $oldPath) {
                    if (!empty($oldPath) && Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
            } elseif (!empty($oldPathRaw) && is_string($oldPathRaw)) {
                if (Storage::disk('public')->exists($oldPathRaw)) {
                    Storage::disk('public')->delete($oldPathRaw);
                }
            }

            $storedPaths = [];
            foreach ($request->file('receipt_files') as $file) {
                $storedPaths[] = $file->store('receipts', 'public');
            }
            
            // PERBAIKAN: Paksa enkripsi JSON manual saat update
            $updateData['receipt_path'] = json_encode($storedPaths);
        }

        $cash->update($updateData);

        // Protokol Kalibrasi: Hitung ulang seluruh saldo pangkalan
        $this->recalculateBalances();

        return back()->with('success', 'Transaksi berhasil dikalibrasi ulang.');
    }

    /**
     * HAPUS TRANSAKSI & REKALKULASI SALDO
     */
    public function destroy($id)
    {
        $cash = Cash::findOrFail($id);
        $oldPathRaw = $cash->getRawOriginal('receipt_path') ?? $cash->receipt_path;
        $oldDecoded = is_string($oldPathRaw) ? json_decode($oldPathRaw, true) : $oldPathRaw;

        // Hancurkan berkas fisik lampiran di storage (Ditambahkan pengaman internal)
        if (is_array($oldDecoded)) {
            foreach ($oldDecoded as $path) {
                if (!empty($path) && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        } elseif (is_array($oldPathRaw)) {
            foreach ($oldPathRaw as $path) {
                if (!empty($path) && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        } elseif (!empty($oldPathRaw) && is_string($oldPathRaw)) {
            if (Storage::disk('public')->exists($oldPathRaw)) {
                Storage::disk('public')->delete($oldPathRaw);
            }
        }

        $cash->delete();

        // Protokol Kalibrasi: Hitung ulang seluruh saldo pangkalan
        $this->recalculateBalances();

        return back()->with('success', 'Log transaksi telah dimusnahkan.');
    }

    /**
     * FUNGSI TAKTIS: Hitung Ulang Seluruh Saldo Secara Berurutan
     */
    private function recalculateBalances()
    {
        $cashes = Cash::orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        $runningBalance = 0;

        foreach ($cashes as $item) {
            $runningBalance += ($item->debit - $item->credit);
            $item->balance = $runningBalance;
            $item->save();
        }
    }

    public function exportPdf()
    {
        $cashes = Cash::orderBy('date', 'asc')->get();
        $totalSaldo = Cash::latest('id')->first()?->balance ?? 0;

        $pdf = Pdf::loadView('pdf.cash_report', [
            'cashes' => $cashes,
            'totalSaldo' => $totalSaldo,
            'title' => 'LAPORAN BUKU KAS SINDEN'
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Buku_Kas_SINDEN.pdf');
    }
}