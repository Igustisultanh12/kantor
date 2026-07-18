<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubCategory extends Model
{
    // Tambahkan ini agar bisa menyimpan sub-kategori
    protected $fillable = ['category_id', 'name'];

    /**
     * Relasi balik ke Kategori Utama
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}