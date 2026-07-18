<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StampLog extends Model
{
    protected $fillable = [
        'user_id', 'subject', 'file_path', 'x', 'y', 'page', 'is_manual'
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}