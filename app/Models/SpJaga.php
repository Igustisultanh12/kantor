<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpJaga extends Model
{
    use HasFactory;

    protected $fillable = [
        'nomor_sprin',
        'nomor_urut',
        'bulan',
        'tahun',
        'bulan_romawi',
        'tmt_mulai',
        'tmt_selesai',
        'tanggal_surat',
        'perwira_tertua_user_id',
        'perwira_tertua_nama',
        'perwira_tertua_pangkat_nrp',
        'perwira_tertua_jabatan',
        'total_personel_count',
        'total_personel_terbilang',
        'ttd_type',
        'status',
        'signed_file_path',
        'verification_code',
        'penandatangan_user_id',
        'penandatangan_nama',
        'penandatangan_pangkat_nrp',
        'penandatangan_jabatan',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'nomor_urut' => 'string',
        'tmt_mulai' => 'date:Y-m-d',
        'tmt_selesai' => 'date:Y-m-d',
        'tanggal_surat' => 'date:Y-m-d',
        'approved_at' => 'datetime',
    ];

    protected $appends = [
        'tmt_mulai_formatted',
        'tmt_selesai_formatted',
        'tanggal_surat_formatted',
    ];

    public function getTmtMulaiFormattedAttribute(): string
    {
        $raw = $this->attributes['tmt_mulai'] ?? null;
        if (!$raw) return '';
        return substr((string)$raw, 0, 10);
    }

    public function getTmtSelesaiFormattedAttribute(): string
    {
        $raw = $this->attributes['tmt_selesai'] ?? null;
        if (!$raw) return '';
        return substr((string)$raw, 0, 10);
    }

    public function getTanggalSuratFormattedAttribute(): string
    {
        $raw = $this->attributes['tanggal_surat'] ?? null;
        if (!$raw) return '';
        return substr((string)$raw, 0, 10);
    }

    public function perwiras()
    {
        return $this->hasMany(SpJagaPerwira::class, 'sp_jaga_id')->orderBy('no_urut', 'asc');
    }

    public function anggotas()
    {
        return $this->hasMany(SpJagaAnggota::class, 'sp_jaga_id')->orderBy('divisi_no', 'asc');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function perwiraTertua()
    {
        return $this->belongsTo(User::class, 'perwira_tertua_user_id');
    }
}