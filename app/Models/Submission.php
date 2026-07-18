<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model
{
    use HasFactory;

    /**
     * PESS CONFIG: Mengunci nama tabel pangkalan data berkas clearance
     */
    protected $table = 'submissions';

    /**
     * PESS CONFIG: Melindungi seluruh field integrasi PESS -> SINDEN -> SINKODV
     */
    protected $fillable = [
        'applicant_id',
        'submission_category_id',
        'answers_json',
        'generated_pdf_path',
        'attachment_paths',
        'status',
        'interview_details',
        'skhpp_path',
        'jenis_ttd_komandan',
        'komandan_coord_x',
        'komandan_coord_y',
        'sc_pdf_path',
        'jenis_ttd_asintel',
        'asintel_coord_x',
        'asintel_coord_y'
    ];

    /**
     * SINDEN MUTATOR & CASTING: Otomatis konversi JSON DB menjadi Array PHP murni
     * Ini yang menjamin data data DRH, link wawancara, dan berkas ter-load sempurna tanpa corrupt
     */
    protected $casts = [
        'answers_json'      => 'array',
        'attachment_paths'  => 'array',
        'interview_details' => 'array',
        'komandan_coord_x'  => 'integer',
        'komandan_coord_y'  => 'integer',
        'asintel_coord_x'   => 'integer',
        'asintel_coord_y'   => 'integer',
    ];

    /**
     * SINDEN RELASI: Berkas ini dimiliki oleh satu personil Pemohon (Applicant)
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }

    /**
     * SINDEN RELASI: Berkas ini diklasifikasikan berdasarkan kategori DRH (RhType)
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(RhType::class, 'submission_category_id', 'id');
    }
}