<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TechnicalUnitCash extends Model
{
    use HasFactory;

    protected $table = 'technical_unit_cashes';

    protected $fillable = [
        'date',
        'description',
        'debit',
        'credit',
        'balance',
        'receipt_path',
    ];

    protected $casts = [
        'debit' => 'float',
        'credit' => 'float',
        'balance' => 'float',
    ];
}
