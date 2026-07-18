<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\LetterSubCategory; 
use App\Models\LetterLog;
use Illuminate\Support\Facades\Log; // <--- Amunisi untuk mencatat aktivitas ke Log
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;


class LetterLogController extends Controller
{
    /**
     * 
     * Menampilkan halaman Buku Nomor (Agenda).
     */
     
     
    public function index()
    {
        return Inertia::render('LetterLogs/Index', [
            'categories' => Category::all(),
            'sub_categories' => LetterSubCategory::all(), 
            'logs' => LetterLog::with(['category', 'subCategory'])->latest()->paginate(1000),
            'priorities' => [
                'B' => 'BIASA',
                'R' => 'RAHASIA',
                'K' => 'KILAT',
                'SR' => 'SANGAT RAHASIA'
            ]
        ]);
    }

    /**
     * Mengambil nomor urut berikutnya berdasarkan kategori.
     * DISESUAIKAN: Menangani sequence tipe String
     */
    public function getNextNumber(Request $request)
    {
        $request->validate(['category_id' => 'required|exists:categories,id']);
        
        $category = Category::find($request->category_id);
        
        // Cari data terakhir berdasarkan kategori dan tahun berjalan
        $lastLog = LetterLog::where('category_id', $category->id)
            ->whereYear('date', date('Y'))
            ->orderBy('id', 'desc') // Menggunakan ID untuk mencari inputan paling terakhir
            ->first();

        // LOGIKA BARU: Jika sequence berisi huruf, otomatisasi dimatikan (personel input manual di Vue)
        // Namun jika sequence terakhir adalah angka murni, kita coba bantu tawarkan +1
        $lastSequence = $lastLog ? $lastLog->sequence : null;
        
        if (is_numeric($lastSequence)) {
            $nextNumber = (int)$lastSequence + 1;
        } else {
            // Jika mengandung huruf (misal 14a), tawarkan start_number atau biarkan personel edit di Vue
            $nextNumber = $lastSequence ?? $category->start_number;
        }

        return response()->json([
            'sequence' => (string)$nextNumber, // Pastikan dikirim sebagai string
            'formatted' => $nextNumber
        ]);
    }

    /**
     * Simpan "Booking" nomor surat ke Buku Nomor.
     * DISESUAIKAN: Validasi sequence menjadi String
     */
    public function store(Request $request)
    {
    	// Catat data yang masuk dari form ke Log untuk analisa
    Log::info('Percobaan Booking Nomor:', $request->all());
    	
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'nullable|exists:letter_sub_categories,id', 
            'priority' => 'required|string',
            'subject' => 'required|string|max:255',
            'recipient' => 'nullable|string', 
            'date' => 'required|date',
            'sequence' => 'required|string', // PERUBAHAN VITAL: Menjadi String
        ]);

        try {
            DB::beginTransaction();

            $category = Category::find($request->category_id);
            
            $timestamp = strtotime($request->date);
            $year = date('Y', $timestamp);
            $month = date('n', $timestamp);

            // Kita gunakan sequence yang dikirim dari form (karena sudah bisa diubah jadi huruf di Vue)
            $sequence = $request->sequence; 
            $romanMonth = $this->toRoman($month);

            // ============================================================
            // LOGIKA PENYAMARAN KODE (SKHPP-D / SKHPP-P -> SKHPP)
            // ============================================================
            $cleanCode = explode('-', $category->code)[0];
            
            if ($cleanCode === 'R' || $cleanCode === 'Sprin') {
                $fullNumber = "{$cleanCode} / {$sequence} / {$romanMonth} / {$year}";
            } else {
                $fullNumber = "{$request->priority} / {$sequence} / {$cleanCode} / {$romanMonth} / {$year}";
            }
            // ============================================================

            LetterLog::create([
                'full_number' => $fullNumber,
                'sequence' => $sequence, // Tersimpan sebagai string (misal: 14a)
                'priority' => $request->priority,
                'category_id' => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'subject' => $request->subject,
                'recipient' => $request->recipient, 
                'date' => $request->date,
                'is_archived' => false
            ]);

            DB::commit();
            return back()->with('success', "Nomor surat {$fullNumber} berhasil di-booking.");
            
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal mengambil nomor: ' . $e->getMessage());
        }
    }

    /**
     * FITUR PDF (Tetap sesuai permintaan)
     */
    public function printPdf(Request $request)
    {
        $query = LetterLog::with(['category', 'subCategory']);

        if ($request->has('category_id') && $request->category_id != '') {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        $logs = $query->latest()->get();
        $categoryName = $request->category_id ? Category::find($request->category_id)->name : 'SEMUA KATEGORI';

        return Inertia::render('LetterLogs/Index', [
            'print_logs' => $logs,
            'filter_name' => strtoupper($categoryName)
        ]);
    }

    /**
     * Fitur Pembersihan riwayat.
     */
    public function clearLogs()
    {
        try {
            LetterLog::truncate();
            return back()->with('success', 'Lapor! Seluruh riwayat penomoran telah berhasil dimusnahkan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memusnahkan riwayat: ' . $e->getMessage());
        }
    }

    /**
     * Helper Romawi.
     */
    private function toRoman($number)
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        return $map[$number] ?? 'I';
    }
}