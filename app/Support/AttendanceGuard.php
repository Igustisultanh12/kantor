<?php

namespace App\Support;

/**
 * Pemeriksaan presensi di SERVER (audit S06). Dulu GPS, lokasi palsu, dan foto hanya
 * dicek di HP sehingga klien yang dimodifikasi bisa absen dari mana saja.
 *
 * Geofence aktif bila koordinat kantor diisi di .env:
 *   ATTENDANCE_OFFICE_LAT, ATTENDANCE_OFFICE_LNG, ATTENDANCE_RADIUS_M (bawaan 500 m),
 *   ATTENDANCE_MAX_ACCURACY_M (bawaan 150 m).
 */
class AttendanceGuard
{
    /** Status yang wajib berada di area kantor. */
    public const ON_SITE_STATUSES = ['hadir', 'piket'];

    /** @return string|null Pesan penolakan, atau null bila lolos. */
    public static function check(string $status, ?float $lat, ?float $lng, ?float $accuracy, bool $isMock): ?string
    {
        if ($isMock) {
            return 'Lokasi palsu (mock location) terdeteksi. Matikan aplikasi pengubah lokasi lalu ulangi.';
        }
        if (!in_array($status, self::ON_SITE_STATUSES, true)) {
            return null;
        }
        $maxAcc = (float) config('services.attendance.max_accuracy_m', 150);
        if ($accuracy !== null && $accuracy > $maxAcc) {
            return 'Akurasi GPS terlalu rendah (' . round($accuracy) . ' m). Pindah ke area terbuka lalu ulangi.';
        }
        $officeLat = config('services.attendance.office_lat');
        $officeLng = config('services.attendance.office_lng');
        if ($officeLat === null || $officeLng === null || $officeLat === '' || $officeLng === '' || $lat === null || $lng === null) {
            return null;
        }
        $radius = (float) config('services.attendance.radius_m', 500);
        $distance = self::distanceMeters((float) $officeLat, (float) $officeLng, $lat, $lng);
        if ($distance > $radius) {
            return 'Anda berada di luar area kantor (' . round($distance) . ' m dari titik kantor). '
                . 'Untuk tugas di luar, pilih status Dinas Luar.';
        }
        return null;
    }

    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $r * asin(min(1.0, sqrt($a)));
    }
}
