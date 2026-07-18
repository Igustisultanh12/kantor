<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VisitorLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http; // Untuk hit API jika diperlukan

class LocationController extends Controller
{
    /**
     * Memperbarui lokasi log pengunjung berdasarkan koordinat GPS dari Dashboard.
     * Dikirim otomatis via axios.post di Dashboard.vue
     */
    public function update(Request $request)
    {
        $lat = $request->latitude;
        $lng = $request->longitude;

        if ($lat && $lng) {
            // 1. Cari log terbaru milik personel yang sedang login
            $lastLog = VisitorLog::where('user_id', Auth::id())
                                ->orderBy('login_at', 'desc')
                                ->first();

            if ($lastLog) {
                // 2. Coba konversi koordinat menjadi nama wilayah (Reverse Geocoding)
                // Jika Bapak ingin langsung koordinat, simpan "GPS: $lat, $lng"
                $locationName = $this->reverseGeocode($lat, $lng);

                // 3. Update data di database
                $lastLog->update([
                    'location' => $locationName ?: "GPS: $lat, $lng"
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Lokasi GPS berhasil diperbarui',
                    'data' => $locationName
                ]);
            }
        }

        return response()->json(['status' => 'error', 'message' => 'Data koordinat tidak lengkap'], 400);
    }

    /**
     * Fungsi Opsional: Mengubah koordinat menjadi nama kota agar lebih mudah dipantau
     */
    private function reverseGeocode($lat, $lng)
    {
        try {
            // Menggunakan API gratis dari bigdatacloud (tanpa API Key)
            $response = Http::timeout(3)->get("https://api.bigdatacloud.net/data/reverse-geocode-client", [
                'latitude' => $lat,
                'longitude' => $lng,
                'localityLanguage' => 'id'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $city = $data['city'] ?? $data['locality'] ?? 'Unknown';
                $region = $data['principalSubdivision'] ?? '';
                return "$city, $region (GPS)";
            }
        } catch (\Exception $e) {
            return null; // Jika gagal, akan kembali ke format koordinat angka
        }
        return null;
    }
}