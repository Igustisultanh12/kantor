<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LetterCategory extends Model
{
    protected $fillable = ['name', 'code', 'format_template'];

    /**
     * Relasi One-to-Many ke Sub Kategori.
     */
    public function subCategories(): HasMany
    {
        return $this->hasMany(LetterSubCategory::class);
    }
}