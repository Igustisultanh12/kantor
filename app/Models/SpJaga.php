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
        'tmt_mulai' => 'date',
        'tmt_selesai' => 'date',
        'tanggal_surat' => 'date',
        'approved_at' => 'datetime',
    ];

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