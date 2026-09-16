<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'document_title',
        'original_filename',
        'file_type',
        'original_file_path',
        'preview_pdf_path',
        'printable_pdf_path',
        'total_pages',
        'separator_pages',
        'total_sheets',
        'printed_sheets',
        'copies',
        'color_mode',
        'print_density',
        'printer_ip',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_pages' => 'integer',
        'separator_pages' => 'integer',
        'total_sheets' => 'integer',
        'printed_sheets' => 'integer',
        'copies' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relasi ke personel pemohon cetak
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope antrean yang sedang aktif (menunggu atau sedang mencetak)
     */
    public function scopeActiveQueue($query)
    {
        return $query->whereIn('status', ['queued', 'printing'])->orderBy('created_at', 'asc');
    }

    /**
     * Scope riwayat cetak terbaru
     */
    public function scopeRecentCompleted($query, $limit = 15)
    {
        return $query->whereIn('status', ['completed', 'failed', 'cancelled'])->latest()->take($limit);
    }
}
