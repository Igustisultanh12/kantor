<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AccessRequest extends Model
{
    use HasFactory;

    // KALIBRASI NAMA TABEL: Pastikan sama persis dengan phpMyAdmin
    protected $table = 'access_requests_for_backup';

    protected $fillable = [
        'user_id',
        'pc_name',
        'status',
        'verification_code',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}