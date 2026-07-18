<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Personnel extends Model
{
    use HasFactory;

    // Menentukan nama tabel secara eksplisit
    protected $table = 'personnels';

    // Kolom yang diizinkan untuk diisi secara massal
    protected $fillable = [
        'pangkat',
        'name',
        'phone',
        'birth_date',
    ];
}