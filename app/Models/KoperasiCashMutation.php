<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KoperasiCashMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_code',
        'date',
        'type',
        'category',
        'amount',
        'balance',
        'reference_type',
        'reference_id',
        'description',
        'recorded_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
