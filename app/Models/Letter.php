<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Letter extends Model
{
    /** Status disposisi surat masuk. null = surat lama / bukan surat masuk (tidak terkunci). */
    public const DISPOSISI_MENUNGGU = 'menunggu';
    public const DISPOSISI_DIDISPOSISI = 'didisposisi';
    public const DISPOSISI_SELESAI = 'selesai';

    protected $fillable = [
        'type',
        'category_id',
        'sub_category_id',
        'security_level',
        'letter_number',
        'subject',
        'date',
        'issuer',
        'file_path',
        'agenda_number',
        'agenda_sequence',
        'received_at',
        'recorded_by',
        'disposition_status',
        'twu',
        'disposition_sheet_pages',
        'disposition_sheet_path',
    ];

    /** Tipe dokumen yang wajib melalui disposisi Komandan (semua telegram dianggap telegram masuk). */
    public const TIPE_DISPOSISI = ['masuk', 'telegram'];

    protected $casts = [
        'received_at' => 'date:Y-m-d',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * PERBAIKAN: Pastikan nama Model target adalah 'SubCategory' 
     * bukan 'LetterSubCategory' (sesuaikan dengan nama file di folder Models Anda).
     */
    public function subCategory(): BelongsTo
    {
        // Jika nama file model Anda adalah SubCategory.php, gunakan ini:
        return $this->belongsTo(SubCategory::class, 'sub_category_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function disposition(): HasOne
    {
        return $this->hasOne(LetterDisposition::class)->latestOfMany();
    }

    /** Surat/telegram masuk yang belum didisposisi Komandan: berkas & detail terkunci. */
    public function isAwaitingDisposition(): bool
    {
        return in_array($this->type, self::TIPE_DISPOSISI, true) && $this->disposition_status === self::DISPOSISI_MENUNGGU;
    }

    public function isTelegram(): bool
    {
        return $this->type === 'telegram';
    }
}
