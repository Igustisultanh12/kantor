<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenMeetingAttendee extends Model
{
    use HasFactory;

    protected $table = 'open_meeting_attendees';

    protected $fillable = [
        'open_meeting_id',
        'name',
        'pangkat_nrp',
        'jabatan',
        'custom_data',
        'signature',
        'ip_address',
        'user_agent',
        'attended_at',
    ];

    protected $casts = [
        'custom_data' => 'array',
        'attended_at' => 'datetime',
    ];

    protected $appends = [
        'formatted_attended_at',
    ];

    /**
     * Relasi ke sesi rapat terbuka
     */
    public function meeting()
    {
        return $this->belongsTo(OpenMeeting::class, 'open_meeting_id');
    }

    /**
     * Format waktu hadir
     */
    public function getFormattedAttendedAtAttribute(): string
    {
        return $this->attended_at ? $this->attended_at->format('d/m/Y H:i:s') : ($this->created_at ? $this->created_at->format('d/m/Y H:i:s') : '-');
    }
}
