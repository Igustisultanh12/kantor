<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaSession extends Model
{
    use HasFactory;

    protected $table = 'wa_sessions';

    protected $fillable = [
        'session_id',
        'label',
        'phone_number',
        'status',
        'created_by',
        'role_access',
        'last_active_at',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
