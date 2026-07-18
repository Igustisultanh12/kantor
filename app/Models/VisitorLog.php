<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VisitorLog extends Model
{
    use HasFactory;

    /**
     * Kolom yang diizinkan untuk diisi secara massal.
     * Ditambahkan is_suspicious untuk menandai indikasi manipulasi lokasi.
     */
    protected $fillable = [
        'user_id', 
        'user_name', 
        'nrp', 
        'ip_address', 
        'location',     // Nama Kota (IP) atau Label Status
        'latitude',     // Koordinat Latitude
        'longitude',    // Koordinat Longitude
        'is_suspicious', // BARU: Status Indikasi Manipulasi/Fake GPS
        'user_agent', 
        'device',       // Merk HP / OS
        'login_at'
    ];

    /**
     * Casting data agar selalu terbaca sebagai tipe data yang tepat.
     */
    protected $casts = [
        'login_at'      => 'datetime',
        'is_suspicious' => 'boolean', // Memastikan nilai 0/1 dibaca sebagai true/false
        'latitude'      => 'double',
        'longitude'     => 'double',
    ];

    /**
     * Relasi ke User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * FUNGSI INTELIJEN: Menghitung Jarak Antar Dua Titik (Haversine Formula)
     * Digunakan untuk mendeteksi "Impossible Travel".
     * Jarak dikembalikan dalam satuan Kilometer (KM).
     */
    public static function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if (is_null($lat1) || is_null($lon1) || is_null($lat2) || is_null($lon2)) {
            return 0;
        }

        $earthRadius = 6371; // Radius Bumi dalam KM

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
}