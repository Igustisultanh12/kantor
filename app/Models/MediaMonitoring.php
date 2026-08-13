<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaMonitoring extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'source_name',
        'category',
        'risk_level',
        'sentiment',
        'summary',
        'url',
        'location',
        'published_at',
        'is_pinned'
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_pinned' => 'boolean',
    ];
}
