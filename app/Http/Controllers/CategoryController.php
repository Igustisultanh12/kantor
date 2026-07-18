<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    /**
     * Menampilkan daftar semua kategori beserta sub-kategorinya.
     */
    public function index()
    {
        return Inertia::render('Categories/Index', [
            'categories' => Category::with('subCategories')->latest()->get()
        ]);
    }

    /**
     * Simpan Kategori Utama Baru.
     */
    public function store(Request $request)
    {
        // Validasi menyertakan start_number agar sistem penomoran berjalan
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:categories,code',
            'start_number' => 'required|integer|min:1', // Wajib diisi agar penomoran tidak error
            'is_telegram' => 'boolean'
        ]);

        try {
            // Pastikan data yang dikirim ke create() sesuai dengan isi $fillable di Model
            Category::create([
                'name' => $request->name,
                'code' => $request->code,
                'start_number' => $request->start_number,
                'is_telegram' => $request->is_telegram ?? false
            ]);

            return back()->with('success', 'Kategori utama berhasil ditambahkan');
        } catch (\Exception $e) {
            Log::error("Gagal simpan kategori: " . $e->getMessage());
            return back()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /**
     * Simpan Sub-Kategori Baru.
     */
    public function storeSub(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            // Kategori Telegram biasanya menggunakan format khusus, tidak perlu sub-jenis
            if ($category->is_telegram) {
                return back()->with('error', 'Kategori Telegram tidak memerlukan sub-jenis.');
            }

            $category->subCategories()->create([
                'name' => $request->name
            ]);

            return back()->with('success', 'Sub-Kategori berhasil ditambahkan');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menyimpan sub-kategori.');
        }
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return back()->with('success', 'Kategori berhasil dihapus');
    }

    public function destroySub(SubCategory $subCategory)
    {
        $subCategory->delete();
        return back()->with('success', 'Sub-Kategori berhasil dihapus');
    }
}