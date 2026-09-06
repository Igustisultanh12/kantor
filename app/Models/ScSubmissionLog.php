<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScSubmissionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'sc_submission_id',
        'stage',
        'stage_title',
        'notes',
        'user_id',
        'user_name',
    ];

    protected $casts = [
        'stage' => 'integer',
    ];

    public function submission()
    {
        return $this->belongsTo(ScSubmission::class, 'sc_submission_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
