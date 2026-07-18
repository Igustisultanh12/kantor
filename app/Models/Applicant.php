<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Applicant extends Model
{
    use HasFactory;

    /**
     * PESS CONFIG: Mengunci nama tabel pangkalan pemohon
     */
    protected $table = 'applicants';

    /**
     * PESS CONFIG: Field yang dapat diisi secara masif
     */
    protected $fillable = [
        'nomor_identitas', // KTP / NRP / NIP
        'nama_lengkap',
        'no_wa',
        'status',          // pending, approved, rejected
        'verified_at'
    ];

    /**
     * PESS CONFIG: Casting tipe data tanggal
     */
    protected $casts = [
        'verified_at' => 'datetime',
    ];

    /**
     * SINDEN RELASI: Hubungan ke banyak pengajuan (History Submissions)
     * 1 Pemohon bisa memiliki banyak riwayat pengajuan di masa depan
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'applicant_id', 'id');
    }

    /**
     * SINDEN RELASI: Ambil pengajuan aktif/terbaru milik pemohon
     * Digunakan pada halaman PESSAdmin.vue untuk meload radar berkas terbaru
     */
    public function latest_submission(): HasOne
    {
        return $this->hasOne(Submission::class, 'applicant_id', 'id')->latestOfMany();
    }
}