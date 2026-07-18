<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoldierViolation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nama tabel di database (Tegaskan jika perlu)
     */
    protected $table = 'soldier_violations';

    /**
     * Kolom yang dapat diisi (Mass Assignment)
     * DISESUAIKAN DENGAN IMAGE_1F6621.PNG (BAHASA INGGRIS)
     */
    protected $fillable = [
        'name',             // Nama Prajurit
        'nrp',              // NRP
        'rank',             // Pangkat
        'position',         // Jabatan
        'unit',             // Satuan Kerja (Satker)
        'case_description', // Kasus tentang apa
        'incident_date',    // TMT / Tanggal Kejadian
        'case_development', // Pengembangan kasus (Update berkala)
        'status',           // Status Kasus
        'lampiran_berkas',  // Path File Lampiran
        'dokumen_putusan'   // Path File Putusan
    ];

    /**
     * Casting atribut agar formatnya konsisten
     */
    protected $casts = [
        'incident_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}