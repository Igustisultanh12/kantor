<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_code',
        'nama',
        'pangkat_korps',
        'identifier_type',
        'identifier_number',
        'kesatuan',
        'jabatan',
        'phone',
        'keperluan',
        'current_stage',
        'status',
        'nomor_surat_rh',
        'nomor_skhpp',
        'nomor_sc',
        'catatan_petugas',
        'created_by',
    ];

    protected $casts = [
        'current_stage' => 'integer',
    ];

    protected $appends = [
        'stage_title',
        'stage_description',
        'progress_percentage',
        'all_stages',
    ];

    /**
     * 10 Tahapan Resmi Pengajuan Security Clearance (SC)
     */
    public const STAGES = [
        1 => [
            'id' => 1,
            'title' => 'Pengisian RH',
            'desc' => 'Pengisian formulir Daftar Riwayat Hidup (RH) oleh personel pemohon.',
            'category' => 'Denintel',
        ],
        2 => [
            'id' => 2,
            'title' => 'Pengecekan Kelengkapan Dokumen',
            'desc' => 'Pemeriksaan berkas administrasi dan kelengkapan lampiran oleh petugas Denintel.',
            'category' => 'Denintel',
        ],
        3 => [
            'id' => 3,
            'title' => 'Proses Cetak RH',
            'desc' => 'Pencetakan lembar resmi Riwayat Hidup untuk penandatanganan kedinasan.',
            'category' => 'Denintel',
        ],
        4 => [
            'id' => 4,
            'title' => 'Menunggu TTD Komandan Denintel',
            'desc' => 'Pengajuan berkas untuk diteliti dan ditandatangani oleh Komandan Detasemen Intelijen.',
            'category' => 'Denintel',
        ],
        5 => [
            'id' => 5,
            'title' => 'SKHPP Terbit',
            'desc' => 'Surat Keterangan Hasil Penelitian Personel (SKHPP) telah resmi diterbitkan oleh Denintel.',
            'category' => 'Denintel',
        ],
        6 => [
            'id' => 6,
            'title' => 'Proses Staf Intelijen',
            'desc' => 'Pemberkasan dan penyerahan ke Staf Intelijen (Sintel) Komando Daerah Angkatan Laut.',
            'category' => 'Sintel',
        ],
        7 => [
            'id' => 7,
            'title' => 'Verifikasi Dokumen Sintel',
            'desc' => 'Penelitian dan validasi kepatutan data intelijen di tingkat Staf Intelijen Kodaeral V.',
            'category' => 'Sintel',
        ],
        8 => [
            'id' => 8,
            'title' => 'Proses Pencetakan SC',
            'desc' => 'Pencetakan blangko resmi naskah Security Clearance (SC).',
            'category' => 'Sintel',
        ],
        9 => [
            'id' => 9,
            'title' => 'Menunggu TTD Asintel',
            'desc' => 'Naskah Security Clearance diajukan untuk ditandatangani oleh Asisten Intelijen (Asintel).',
            'category' => 'Sintel',
        ],
        10 => [
            'id' => 10,
            'title' => 'SC Terbit dan Bisa Diambil di Mako Kodaeral V',
            'desc' => 'Naskah Security Clearance telah selesai terbit dan siap diambil oleh personel pemohon di Mako Kodaeral V.',
            'category' => 'Selesai',
        ],
    ];

    public function logs()
    {
        return $this->hasMany(ScSubmissionLog::class, 'sc_submission_id')->orderBy('id', 'asc');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStageTitleAttribute()
    {
        return self::STAGES[$this->current_stage]['title'] ?? 'Dalam Proses';
    }

    public function getStageDescriptionAttribute()
    {
        return self::STAGES[$this->current_stage]['desc'] ?? '-';
    }

    public function getProgressPercentageAttribute()
    {
        return min(100, max(10, $this->current_stage * 10));
    }

    public function getAllStagesAttribute()
    {
        return array_values(self::STAGES);
    }
}
