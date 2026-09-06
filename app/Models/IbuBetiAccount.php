<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IbuBetiAccount extends Model
{
    use HasFactory;

    protected $table = 'ibu_beti_accounts';

    protected $fillable = [
        'tanggal',
        'keterangan',
        'jenis',
        'jumlah',
        'bukti',
        'petugas_input',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'jumlah' => 'decimal:2',
    ];
}
