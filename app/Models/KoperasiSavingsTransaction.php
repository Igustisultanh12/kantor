<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KoperasiSavingsTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'user_id',
        'transaction_code',
        'type',
        'saving_type',
        'amount',
        'balance_after',
        'payment_method',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(KoperasiAccount::class, 'account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
