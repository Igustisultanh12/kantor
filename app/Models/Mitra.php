<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mitra extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'pt',
        'no_tlp',
        'sort_order',
        'keterangan',
    ];

    public function payments()
    {
        return $this->hasMany(MitraPayment::class, 'mitra_id');
    }
}
