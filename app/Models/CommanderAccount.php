<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommanderAccount extends Model
{
    use HasFactory;

    // Menentukan nama tabel logistik di database
    protected $table = 'commander_accounts';

    // Mengunci kolom yang diizinkan untuk manipulasi massal
    protected $fillable = [
        'tanggal',
        'keterangan',
        'jenis',
        'jumlah',
        'petugas_input',
    ];
}