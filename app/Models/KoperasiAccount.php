<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KoperasiAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'member_number',
        'balance_simpanan_pokok',
        'balance_simpanan_wajib',
        'balance_simpanan_sukarela',
        'total_simpanan',
        'status',
    ];

    protected $casts = [
        'balance_simpanan_pokok' => 'decimal:2',
        'balance_simpanan_wajib' => 'decimal:2',
        'balance_simpanan_sukarela' => 'decimal:2',
        'total_simpanan' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(KoperasiSavingsTransaction::class, 'account_id')->latest();
    }
}
