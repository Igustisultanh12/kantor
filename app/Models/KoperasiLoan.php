<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KoperasiLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'loan_code',
        'loan_type',
        'amount_requested',
        'amount_approved',
        'interest_rate_percent',
        'duration_months',
        'monthly_installment',
        'total_loan_amount',
        'total_paid',
        'remaining_amount',
        'purpose',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'disbursed_by',
        'disbursed_at',
        'document_path',
        'notes',
    ];

    protected $casts = [
        'amount_requested' => 'decimal:2',
        'amount_approved' => 'decimal:2',
        'interest_rate_percent' => 'decimal:2',
        'monthly_installment' => 'decimal:2',
        'total_loan_amount' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function disburser()
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function installments()
    {
        return $this->hasMany(KoperasiInstallment::class, 'loan_id')->orderBy('installment_no', 'asc');
    }
}
