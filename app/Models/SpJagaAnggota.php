<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpJagaAnggota extends Model
{
    use HasFactory;

    protected $fillable = [
        'sp_jaga_id',
        'divisi_no',
        'tanggal_list_text',
        'tanggal_array',
        'anggota_items',
    ];

    protected $casts = [
        'tanggal_array' => 'array',
        'anggota_items' => 'array',
    ];

    public function spJaga()
    {
        return $this->belongsTo(SpJaga::class, 'sp_jaga_id');
    }
}