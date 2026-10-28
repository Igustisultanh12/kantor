<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OpenMeeting extends Model
{
    use HasFactory;

    protected $table = 'open_meetings';

    protected $fillable = [
        'uuid',
        'title',
        'date',
        'time_start',
        'time_end',
        'location',
        'description',
        'status',
        'custom_fields',
        'require_signature',
        'created_by',
        'creator_name',
        'creator_pangkat_nrp',
        'creator_jabatan',
        'commander_name',
        'commander_pangkat_nrp',
        'commander_jabatan',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'custom_fields' => 'array',
        'require_signature' => 'boolean',
    ];

    protected $appends = [
        'public_url',
        'formatted_date',
        'attendees_count',
    ];

    /**
     * Relasi ke peserta rapat
     */
    public function attendees()
    {
        return $this->hasMany(OpenMeetingAttendee::class, 'open_meeting_id')->orderBy('id', 'asc');
    }

    /**
     * Relasi ke user pembuat / petugas
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * URL publik untuk scan QR atau akses luar tanpa login
     */
    public function getPublicUrlAttribute(): string
    {
        return url('/absen/' . $this->uuid);
    }

    /**
     * Format tanggal baku Indonesia
     */
    public function getFormattedDateAttribute(): string
    {
        if (!$this->date) return '-';
        
        $bulanBaku = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $hariBaku = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu'
        ];

        $c = Carbon::parse($this->date);
        $hari = $hariBaku[$c->format('l')] ?? $c->format('l');
        $tgl = $c->format('j');
        $bln = $bulanBaku[(int)$c->format('n')] ?? $c->format('F');
        $thn = $c->format('Y');

        return "{$hari}, {$tgl} {$bln} {$thn}";
    }

    /**
     * Total jumlah peserta hadir
     */
    public function getAttendeesCountAttribute(): int
    {
        return $this->attendees()->count();
    }
}
