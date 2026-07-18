<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityActivity extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang digunakan oleh model ini.
     */
    protected $table = 'community_activities';

    /**
     * Amunisi Data: Atribut yang dapat diisi secara massal.
     * Mencakup data IPOLEKSOSBUDHANKAM dan Koordinat Peta.
     */
    protected $fillable = [
        'title',          // Judul Kegiatan
        'description',    // Uraian Rencana
        'category',       // Bidang (Politik, Ekonomi, Sosbud, Keamanan, dll)
        'province',       // Wilayah (Bali, Jatim, Jateng)
        'city',           // Kota/Kabupaten
        'location_name',  // Nama Lokasi Spesifik
        'latitude',       // Koordinat Lintang
        'longitude',      // Koordinat Bujur
        'activity_date',  // Waktu Pelaksanaan
        'status',         // RENCANA, BERJALAN, SELESAI
    ];

    /**
     * Konversi Tipe Data Otomatis.
     * Memastikan koordinat tetap terbaca sebagai angka desimal presisi.
     */
    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'activity_date' => 'date:Y-m-d',
    ];

    /**
     * Helper: Mendapatkan Warna Pin Berdasarkan Bidang (Opsional untuk Logika Backend)
     */
    public function getCategoryColorAttribute()
    {
        return match ($this->category) {
            'Politik' => '#ef4444', // Merah
            'Ekonomi' => '#22c55e', // Hijau
            'Sosial Budaya' => '#3b82f6', // Biru
            'Keamanan' => '#eab308', // Kuning
            default => '#6366f1',    // Indigo (Default)
        };
    }
}