<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /**
     * Kolom yang dapat diisi secara massal.
     * Pastikan 'is_telegram' ditambahkan agar data dari form admin bisa tersimpan.
     */
    protected $fillable = [
        'name', 
        'code', 
        'start_number', // TAMBAHKAN INI
        'is_telegram'
    ];

    /**
     * Casting atribut ke tipe data tertentu.
     * Ini memastikan 'is_telegram' selalu dibaca sebagai true/false (boolean).
     */
    protected $casts = [
        'is_telegram' => 'boolean',
    ];

    /**
     * Relasi ke Sub-Kategori (One-to-Many).
     * Kategori Surat memiliki banyak sub-kategori, sedangkan Telegram tidak (secara logika di form).
     */
    public function subCategories(): HasMany
    {
        return $this->hasMany(SubCategory::class);
    }
}