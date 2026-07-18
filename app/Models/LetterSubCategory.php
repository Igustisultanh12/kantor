<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterSubCategory extends Model
{
    // PAKSA ke tabel yang ada datanya (NIKAH, PNS, dll)
    protected $table = 'sub_categories'; 

    protected $fillable = [
        'category_id', 
        'name', 
        'sub_code'
    ];
}