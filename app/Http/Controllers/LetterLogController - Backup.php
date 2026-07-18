<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\LetterSubCategory; // Sumber data dari Manajemen Kategori
use App\Models\LetterLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class LetterLogController extends Controller
{
    /**
     * Menampilkan halaman Buku Nomor (Agenda).
     */
    public function index()
    {
        return Inertia::render('LetterLogs/Index', [
            // Mengambil semua kategori untuk dropdown
            'categories' => Category::all(),
            // TAMBAHAN: Mengambil data Sub-Kategori agar dropdown di Vue berfungsi
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
     */
    public function getNextNumber(Request $request)
    {
        $request->validate(['category_id' => 'required|exists:categories,id']);
        
        $category = Category::find($request->category_id);
        
        // Cari nomor urut terbesar di kategori tersebut pada tahun berjalan
        $lastSequence = LetterLog::where('category_id', $category->id)
            ->whereYear('date', date('Y'))
            ->max('sequence');

        // Gunakan start_number unik per kategori jika belum ada data
        $nextNumber = $lastSequence ? $lastSequence + 1 : $category->start_number;

        return response()->json([
            'sequence' => $nextNumber,
            'formatted' => str_pad($nextNumber, 3, '0', STR_PAD_LEFT)
        ]);
    }

    /**
     * Simpan "Booking" nomor surat ke Buku Nomor.
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'nullable|exists:sub_categories,id', // Validasi Sub-Kategori
            'priority' => 'required|string',
            'subject' => 'required|string|max:255',
            'recipient' => 'nullable|string', // Pastikan divalidasi
            'date' => 'required|date',
        ]);

        try {
            DB::beginTransaction();

            $category = Category::find($request->category_id);
            
            // Konversi string tanggal ke timestamp agar bisa dibaca fungsi date()
            $timestamp = strtotime($request->date);
            $year = date('Y', $timestamp);
            $month = date('n', $timestamp);

            // Hitung ulang sequence di server untuk menghindari duplikasi
            $lastSequence = LetterLog::where('category_id', $category->id)
                ->whereYear('date', $year) 
                ->max('sequence');
            
            $sequence = $lastSequence ? $lastSequence + 1 : $category->start_number;
            $formattedSeq = $sequence;
            $romanMonth = $this->toRoman($month);

            // ============================================================
            // LOGIKA PENYAMARAN KODE (SKHPP-D / SKHPP-P -> SKHPP)
            // ============================================================
            // Menggunakan explode untuk memotong teks setelah tanda '-'
            $cleanCode = explode('-', $category->code)[0];
            
            // Cek kondisi khusus untuk R atau Sprin (Tanpa Sifat Surat di depan)
            if ($cleanCode === 'R' || $cleanCode === 'Sprin') {
                $fullNumber = "{$cleanCode} / {$formattedSeq} / {$romanMonth} / {$year}";
            } else {
                // Format standar (B / 001 / SKHPP / II / 2026)
                $fullNumber = "{$request->priority} / {$formattedSeq} / {$cleanCode} / {$romanMonth} / {$year}";
            }
            // ============================================================

            // EKSEKUSI PENYIMPANAN DATA
            LetterLog::create([
                'full_number' => $fullNumber,
                'sequence' => $sequence,
                'priority' => $request->priority,
                'category_id' => $request->category_id,
                'sub_category_id' => $request->sub_category_id, // Sub-Kategori tersimpan
                'subject' => $request->subject,
                'recipient' => $request->recipient, // PENYELAMATAN DATA TUJUAN: Sudah Terkunci
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
     * FITUR BARU: Cetak PDF dengan Filter Kategori
     * Dipanggil oleh tombol unduh di Vue
     */
    public function printPdf(Request $request)
    {
        $query = LetterLog::with(['category', 'subCategory']);

        // Filter berdasarkan kategori jika dipilih
        if ($request->has('category_id') && $request->category_id != '') {
            $query->where('category_id', $request->category_id);
        }

        // Filter berdasarkan tanggal jika ada
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
     * Fitur Pembersihan: Menghapus seluruh riwayat log penomoran.
     */
    public function clearLogs()
    {
        try {
            // Menghapus seluruh isi tabel riwayat penomoran
            LetterLog::truncate();
            return back()->with('success', 'Lapor! Seluruh riwayat penomoran telah berhasil dimusnahkan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memusnahkan riwayat: ' . $e->getMessage());
        }
    }

    /**
     * Helper fungsi konversi angka ke Romawi untuk bulan.
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