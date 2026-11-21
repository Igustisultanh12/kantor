<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Disposisi Komandan atas satu surat masuk, diteruskan ke satu atau beberapa jabatan (role). */
class LetterDisposition extends Model
{
    /** Instruksi standar lembar disposisi (kunci => label). */
    public const INSTRUKSI = [
        'diketahui' => 'Untuk diketahui',
        'dilaksanakan' => 'Untuk dilaksanakan',
        'koordinasikan' => 'Koordinasikan',
        'saran' => 'Pelajari & beri saran',
        'jawaban' => 'Siapkan jawaban',
        'hadiri' => 'Hadiri / wakili',
        'arsipkan' => 'Arsipkan',
    ];

    public const URGENSI = ['biasa' => 'Biasa', 'segera' => 'Segera', 'sangat_segera' => 'Sangat segera'];

    protected $fillable = ['letter_id', 'given_by', 'instructions', 'note', 'urgency', 'due_date'];

    protected $casts = [
        'instructions' => 'array',
        'due_date' => 'date:Y-m-d',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function giver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'given_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(LetterDispositionTarget::class, 'disposition_id');
    }
}
