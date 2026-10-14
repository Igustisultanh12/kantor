<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom settings.value semula VARCHAR(255). Nilai terenkripsi (mis. secret key
 * Cloudflare Turnstile, ±300 karakter) tidak muat dan menyebabkan error 500
 * "Data too long for column 'value'" saat menyimpan Pengaturan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->text('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sengaja tidak dikembalikan ke VARCHAR(255): nilai panjang yang sudah
        // tersimpan akan terpotong/gagal.
    }
};
