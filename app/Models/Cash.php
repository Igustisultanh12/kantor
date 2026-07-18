<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // Opsional: Dipertahankan jika menggunakan factory untuk testing
use Illuminate\Database\Eloquent\Model;

class Cash extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terhubung ke model ini.
     * (Laravel otomatis mendeteksi jamak 'cashes', namun dideklarasikan eksplisit agar lebih kokoh)
     *
     * @var string
     */
    protected $table = 'cashes';

    /**
     * Atribut yang dapat diisi secara massal (Mass Assignment Protection).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'date', 
        'description', 
        'debit', 
        'credit', 
        'balance',
        'receipt_path' // PERBAIKAN: Kolom pangkalan data baru ditambahkan ke sini agar pengunggahan nota tidak di-block oleh Laravel
    ];

    /**
     * Sistem casting otomatis atribut pangkalan data.
     * Menjamin nilai debit, kredit, dan saldo dikembalikan sebagai tipe numeric murni (float/double)
     * ke sisi Inertia Vue, bukan sebagai string teks mentah.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date:Y-m-d',
        'debit' => 'float',
        'credit' => 'float',
        'balance' => 'float',
    ];
}