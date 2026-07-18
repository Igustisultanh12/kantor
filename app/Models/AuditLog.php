<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * Kolom yang diizinkan untuk pengisian massal (Mass Assignment).
     *
     */
    protected $fillable = [
        'user_id', 
        'admin_name', 
        'action', 
        'target_personnel', 
        'description', 
        'ip_address'
    ];

    /**
     * Casting atribut agar format waktu seragam dan mudah dibaca di Dashboard.
     *
     */
    protected $casts = [
        'created_at' => 'datetime:d M Y H:i',
        'updated_at' => 'datetime:d M Y H:i',
    ];

    /**
     * Relasi ke model User (Admin yang melakukan tindakan).
     *
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}