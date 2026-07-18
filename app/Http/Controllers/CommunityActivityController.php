<?php

namespace App\Http\Controllers;

use App\Models\CommunityActivity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class CommunityActivityController extends Controller
{
    /**
     * Menampilkan Peta Monitoring dan Analisis Statistik Wilayah.
     */
    public function index()
    {
        try {
            return Inertia::render('Activities/MapIndex', [
                'activities' => CommunityActivity::latest()->get(),
                'stats_by_province' => CommunityActivity::select('province', DB::raw('count(*) as total'))
                    ->groupBy('province')
                    ->get(),
                'stats_by_category' => CommunityActivity::select('category', DB::raw('count(*) as total'))
                    ->groupBy('category')
                    ->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Lapor! Gagal memuat Peta Radar: ' . $e->getMessage());
            return back()->with('error', 'Gagal memuat data peta.');
        }
    }

    /**
     * Menyimpan Plotting Rencana Kegiatan Masyarakat (Baru).
     */
    public function store(Request $request)
    {
        // 1. Catat data yang masuk ke log untuk audit awal
        Log::info('Mencoba Plotting Baru:', $request->all());

        try {
            $validated = $request->validate([
                'title' => 'required|string',
                'category' => 'required|string',
                'province' => 'required',
                'location_name' => 'required',
                'latitude' => 'required',
                'longitude' => 'required',
                'activity_date' => 'required|date',
                'description' => 'nullable|string',
            ]);

            // --- AMUNISI BARU: OTOMATISASI DETEKSI WILAYAH (BACKEND PROXY) ---
            $regionName = "Luar Jangkauan";
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'SiSinden_Intel_App/1.0',
                    'Accept-Language' => 'id'
                ])->timeout(5)->get("https://nominatim.openstreetmap.org/reverse", [
                    'format' => 'json',
                    'lat' => $request->latitude,
                    'lon' => $request->longitude,
                    'zoom' => 10
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $regionName = $data['address']['city'] ?? $data['address']['town'] ?? $data['address']['county'] ?? "Lainnya";
                    $regionName = str_replace(["Kabupaten ", "Kota "], ["Kab. ", ""], $regionName);
                }
            } catch (\Exception $e) {
                Log::warning('Radar Nominatim Timeout, Menggunakan default: ' . $e->getMessage());
            }

            // Gabungkan hasil deteksi wilayah ke data yang divalidasi
            $validated['region'] = $regionName;

            CommunityActivity::create($validated);
            Log::info("Berhasil! Plotting di wilayah [$regionName] diamankan ke database.");
            
            return redirect()->back()->with('success', "Rencana kegiatan di $regionName berhasil diplot.");

        } catch (\Illuminate\Validation\ValidationException $ve) {
            Log::warning('Ranjau Validasi! Isian form tidak sesuai:', $ve->errors());
            return redirect()->back()->withErrors($ve->errors());

        } catch (\Exception $e) {
            Log::error('Kritis! Gagal simpan plotting: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Server error: ' . $e->getMessage());
        }
    }

    /**
     * Memperbarui Data Plotting yang Sudah Ada (Update).
     */
    public function update(Request $request, $id)
    {
        Log::info("Mencoba Update Plotting ID: $id", $request->all());

        try {
            $validated = $request->validate([
                'title' => 'required|string',
                'category' => 'required|string',
                'province' => 'required',
                'location_name' => 'required',
                'latitude' => 'required',
                'longitude' => 'required',
                'activity_date' => 'required|date',
                'description' => 'nullable|string',
            ]);

            $activity = CommunityActivity::findOrFail($id);

            // --- AMUNISI BARU: CEK ULANG WILAYAH JIKA KOORDINAT BERUBAH ---
            if ($activity->latitude != $request->latitude || $activity->longitude != $request->longitude) {
                try {
                    $response = Http::withHeaders([
                        'User-Agent' => 'SiSinden_Intel_App/1.0'
                    ])->timeout(5)->get("https://nominatim.openstreetmap.org/reverse", [
                        'format' => 'json',
                        'lat' => $request->latitude,
                        'lon' => $request->longitude,
                        'zoom' => 10
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        $regionName = $data['address']['city'] ?? $data['address']['town'] ?? $data['address']['county'] ?? "Lainnya";
                        $validated['region'] = str_replace(["Kabupaten ", "Kota "], ["Kab. ", ""], $regionName);
                    }
                } catch (\Exception $e) {
                    Log::warning('Gagal Update Wilayah: ' . $e->getMessage());
                }
            }

            $activity->update($validated);

            Log::info("Berhasil! Update data ID: $id telah masuk pangkalan data.");
            return redirect()->back()->with('success', 'Lapor! Data plotting rencana kegiatan berhasil diperbarui.');

        } catch (\Illuminate\Validation\ValidationException $ve) {
            Log::warning("Gagal Update ID: $id karena validasi:", $ve->errors());
            return redirect()->back()->withErrors($ve->errors());

        } catch (\Exception $e) {
            Log::error("Kritis! Gagal update ID: $id. Pesan: " . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal update: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan Detail Kegiatan (Untuk Modal Detail).
     */
    public function show($id)
    {
        try {
            return response()->json(CommunityActivity::findOrFail($id));
        } catch (\Exception $e) {
            Log::error("ID: $id tidak ditemukan: " . $e->getMessage());
            return response()->json(['error' => 'Data tidak ditemukan'], 404);
        }
    }

    /**
     * Memusnahkan Data Plotting dari Database.
     */
    public function destroy($id)
    {
        Log::info("Operasi Pemusnahan Data ID: $id dimulai.");
        try {
            $activity = CommunityActivity::findOrFail($id);
            $activity->delete();
            Log::info("Data ID: $id berhasil dimusnahkan.");
            return redirect()->back()->with('success', 'Data plotting berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error("Gagal musnahkan data ID: $id: " . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menghapus data.');
        }
    }

    /**
     * OPERASI KHUSUS: Sinkronisasi Ulang Semua Wilayah (Repair Radar).
     * Gunakan ini jika masih banyak kolom region yang kosong.
     */
    public function repairRadar()
    {
        $activities = CommunityActivity::whereNull('region')->orWhere('region', 'Scanning...')->get();
        $count = 0;

        foreach ($activities as $activity) {
            try {
                // Jeda 1 detik agar tidak diblokir Nominatim (Rate Limit)
                sleep(1);
                
                $response = Http::withHeaders(['User-Agent' => 'SiSinden_Repair/1.0'])
                    ->get("https://nominatim.openstreetmap.org/reverse", [
                        'format' => 'json',
                        'lat' => $activity->latitude,
                        'lon' => $activity->longitude,
                        'zoom' => 10
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $name = $data['address']['city'] ?? $data['address']['town'] ?? $data['address']['county'] ?? "Lainnya";
                    $activity->update(['region' => str_replace(["Kabupaten ", "Kota "], ["Kab. ", ""], $name)]);
                    $count++;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return back()->with('message', "$count data radar berhasil diperbaiki.");
    }
}