<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureRequest extends Model
{
    /**
     * Mass assignable columns.
     * x, y, dan width sudah didaftarkan agar bisa menyimpan koordinat TTD.
     */
    protected $fillable = [
        'user_id', 
        'subject', 
        'document_title',
        'person_name',
        'pangkat_nrp',
        'jabatan',
        'peruntukan',
        'letter_number', 
        'file_path', 
        'status', 
        'note', 
        'x', 
        'y', 
        'width',
        'target_page',
        'verification_code',
    ];

    /**
     * Relasi ke model User (Personel yang mengajukan).
     * Pastikan model User berada di namespace App\Models\User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Opsional: Casting koordinat ke tipe data double/float 
     * agar saat diproses di Controller angkanya tetap presisi (decimal).
     */
    protected $casts = [
        'x' => 'double',
        'y' => 'double',
        'width' => 'double',
        'target_page' => 'integer',
    ];
}