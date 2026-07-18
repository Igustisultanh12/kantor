<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UcapanConfig extends Model
{
    protected $table = 'ucapan_configs'; // Memastikan mengarah ke tabel yang benar
    protected $fillable = ['slug', 'narasi'];
}