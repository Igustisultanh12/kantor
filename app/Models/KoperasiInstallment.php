<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KoperasiInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'user_id',
        'installment_no',
        'receipt_number',
        'due_date',
        'amount_due',
        'amount_paid',
        'remaining_loan_after',
        'payment_date',
        'payment_method',
        'status',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'payment_date' => 'date',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'remaining_loan_after' => 'decimal:2',
    ];

    public function loan()
    {
        return $this->belongsTo(KoperasiLoan::class, 'loan_id');
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
