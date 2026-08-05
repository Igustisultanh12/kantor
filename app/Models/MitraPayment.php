<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MitraPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mitra_id',
        'tahun',
        'bulan',
        'is_paid',
        'catatan',
        'updated_by',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'tahun' => 'integer',
        'bulan' => 'integer',
    ];

    public function mitra()
    {
        return $this->belongsTo(Mitra::class, 'mitra_id');
    }
}
