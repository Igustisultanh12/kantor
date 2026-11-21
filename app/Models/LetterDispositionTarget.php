<?php

namespace App\Models;

use App\Support\MobileAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu jabatan (role) tujuan disposisi: baru → diterima → selesai (dengan laporan tindak lanjut). */
class LetterDispositionTarget extends Model
{
    public const BARU = 'baru';
    public const DITERIMA = 'diterima';
    public const SELESAI = 'selesai';

    protected $fillable = [
        'disposition_id', 'role', 'status', 'received_by', 'received_at', 'completed_by', 'completed_at', 'report_note',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function disposition(): BelongsTo
    {
        return $this->belongsTo(LetterDisposition::class, 'disposition_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** Pemegang jabatan tujuan (role dibandingkan setelah dinormalkan, mis. "Dan Unit 1" = danunit1). */
    public function belongsToUser(?User $user): bool
    {
        return $user !== null && MobileAccess::normalizedRole($user) === strtolower(preg_replace('/[\s_-]+/', '', $this->role));
    }
}
