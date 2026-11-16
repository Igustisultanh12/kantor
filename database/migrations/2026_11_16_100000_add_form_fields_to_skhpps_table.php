<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom baru dari form SKHPP:
     * - jenis_identitas: pilihan dropdown NRP/NIP/NIM/NIK (null = data lama, ditebak dari kolom terisi).
     * - letter_sub_category_id: "Jenis Surat" di Agenda Surat (tabel sub_categories) untuk sinkronisasi.
     * - nomor_manual: nomor SKHPP yang diketik manual; bila terisi, dipakai apa adanya saat disahkan.
     */
    public function up(): void
    {
        Schema::table('skhpps', function (Blueprint $table) {
            $table->string('jenis_identitas', 10)->nullable()->after('nik');
            $table->unsignedBigInteger('letter_sub_category_id')->nullable()->after('kategori_personel');
            $table->string('nomor_manual', 100)->nullable()->after('nomor_skhpp');
        });
    }

    public function down(): void
    {
        Schema::table('skhpps', function (Blueprint $table) {
            $table->dropColumn(['jenis_identitas', 'letter_sub_category_id', 'nomor_manual']);
        });
    }
};
