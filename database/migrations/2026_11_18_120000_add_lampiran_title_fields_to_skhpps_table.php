<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - satuan: satuan pemohon (mis. "Pomal Kodaeral V"), untuk judul lampiran pengikut.
     * - kegiatan: maksud singkat (mis. "Seleksi Diktukpa LVI/56 TA. 2026"); poin 3 dan judul lampiran disusun darinya.
     *   Judul lampiran: "DAFTAR NAMA PERSONEL <satuan> YANG MELAKSANAKAN SC <kegiatan>".
     * - nomor_tulis_tangan: nomor urut dikosongkan di dokumen ("R/      /SKHPP/XI/2026") untuk ditulis tangan;
     *   sistem tidak mengambil nomor dari Agenda Surat.
     */
    public function up(): void
    {
        Schema::table('skhpps', function (Blueprint $table) {
            $table->string('satuan', 150)->nullable()->after('has_pengikut');
            $table->string('kegiatan')->nullable()->after('peruntukan');
            $table->boolean('nomor_tulis_tangan')->default(false)->after('nomor_manual');
        });
    }

    public function down(): void
    {
        Schema::table('skhpps', function (Blueprint $table) {
            $table->dropColumn(['satuan', 'kegiatan', 'nomor_tulis_tangan']);
        });
    }
};
