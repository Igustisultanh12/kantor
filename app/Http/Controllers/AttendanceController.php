<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;

class AttendanceController extends Controller
{
    /**
     * Menyimpan presensi kehadiran personel (Opsional).
     */
    public function store(Request $request)
    {
        $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'location_name' => 'nullable|string|max:255',
            'device_model' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:hadir,dinas_luar,piket,izin',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $today = now()->toDateString();
        $timeNow = now()->format('H:i:s');

        // Deteksi IP Address
        $ipAddress = $request->header('X-Forwarded-For') 
            ? trim(explode(',', $request->header('X-Forwarded-For'))[0]) 
            : $request->ip();

        // Deteksi Perangkat (Merk & Tipe HP)
        $deviceInfo = $request->device_model;
        if (empty($deviceInfo)) {
            try {
                $agent = new Agent();
                $platform = $agent->platform();
                $device = $agent->device();
                $browser = $agent->browser();

                if ($agent->isDesktop()) {
                    $deviceInfo = "{$platform} PC ({$browser})";
                } elseif (!empty($device)) {
                    $deviceInfo = "{$device} ({$platform})";
                } else {
                    $deviceInfo = "{$platform} Mobile ({$browser})";
                }
            } catch (\Exception $e) {
                $deviceInfo = 'Perangkat Tidak Dikenal';
            }
        }

        // Penanganan Koordinat GPS & Lokasi
        $lat = $request->latitude;
        $lng = $request->longitude;
        $locationName = $request->location_name;

        if ($lat && $lng && empty($locationName)) {
            $locationName = $this->reverseGeocode($lat, $lng);
            if (empty($locationName)) {
                $locationName = "GPS: {$lat}, {$lng}";
            }
        }

        if (empty($locationName)) {
            $locationName = 'Lokasi GPS Belum Diizinkan';
        }

        // Simpan / Perbarui Presensi Kehadiran Hari Ini
        $attendance = Attendance::updateOrCreate(
            [
                'user_id' => $user->id,
                'attendance_date' => $today,
            ],
            [
                'time_in' => $timeNow,
                'status' => $request->status ?: 'hadir',
                'ip_address' => $ipAddress,
                'device' => $deviceInfo,
                'user_agent' => $request->userAgent(),
                'latitude' => $lat,
                'longitude' => $lng,
                'location_name' => $locationName,
                'notes' => $request->notes,
            ]
        );

        return redirect()->back()->with('success', "Lapor! Presensi kehadiran berhasil dicatat pada pukul {$timeNow} WIB.");
    }

    /**
     * Konversi koordinat GPS menjadi nama wilayah/kota secara ringkas.
     */
    private function reverseGeocode($lat, $lng)
    {
        try {
            $response = Http::timeout(3)->get("https://api.bigdatacloud.net/data/reverse-geocode-client", [
                'latitude' => $lat,
                'longitude' => $lng,
                'localityLanguage' => 'id',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $locality = $data['locality'] ?? '';
                $city = $data['city'] ?? $data['principalSubdivision'] ?? '';
                if ($locality && $city) {
                    return "{$locality}, {$city}";
                }
                return $city ?: $locality;
            }
        } catch (\Exception $e) {
            Log::warning("Gagal reverse geocode presensi: " . $e->getMessage());
        }
        return null;
    }
}
