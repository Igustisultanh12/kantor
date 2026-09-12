<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    // --- KALIBRASI FILLABLE (IZIN MASUK DATA) ---
    protected $fillable = [
        'pc_id', 
        'parent_id', // Wajib untuk struktur folder bersarang
        'file_name', 
        'file_path', 
        'file_size', 
        'file_type', 
        'is_folder'  // KUNCI UTAMA: Agar tidak berubah jadi file
    ];

    /**
     * KALIBRASI TIPE DATA
     * Memastikan database selalu konsisten membaca 0/1 sebagai true/false
     */
    protected $casts = [
        'is_folder' => 'boolean',
        'file_size' => 'integer',
    ];

    /**
     * RELASI KE PANGKALAN PC
     */
    public function pc(): BelongsTo
    {
        return $this->belongsTo(Pc::class);
    }

    /**
     * RELASI KE FOLDER INDUK (Navigasi Explorer)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Backup::class, 'parent_id');
    }

    /**
     * RELASI KE TAUTAN BERBAGI (SHARE)
     */
    public function share()
    {
        return $this->hasOne(BackupShare::class, 'backup_id');
    }
}