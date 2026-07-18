<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RhType extends Model
{
    use HasFactory;

    /**
     * SINDEN CONFIG: Mengunci nama tabel pangkalan kategori DRH
     */
    protected $table = 'rh_types';

    /**
     * SINDEN CONFIG: Field kaku yang dapat diisi
     */
    protected $fillable = [
        'name',             // UMUM, KHUSUS, dll
        'needs_double_rh',  // true jika butuh DRH pasangan (Form Nikah)
    ];

    /**
     * SINDEN CONFIG: Casting format data Boolean
     */
    protected $casts = [
        'needs_double_rh' => 'boolean',
    ];

    /**
     * RELASI: Satu jenis kategori ini bisa memiliki banyak data pengajuan (Submissions)
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'submission_category_id', 'id');
    }
}