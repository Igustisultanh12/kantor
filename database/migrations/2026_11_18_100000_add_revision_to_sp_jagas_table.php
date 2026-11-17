<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revisi SP Jaga yang sudah ditandatangani. Revisi adalah baris SP Jaga baru dengan nomor Sprin
     * yang sama, menunggu TTE Pasops, dan tampil di bawah SP aslinya.
     * - revisi_dari_id: SP Jaga asli (selalu yang pertama, bukan revisi sebelumnya).
     * - revisi_ke: 1, 2, ... (0 / null = dokumen asli).
     * - revisi_alasan: alasan revisi yang diisi pengaju.
     */
    public function up(): void
    {
        Schema::table('sp_jagas', function (Blueprint $table) {
            $table->unsignedBigInteger('revisi_dari_id')->nullable()->index()->after('id');
            $table->unsignedSmallInteger('revisi_ke')->nullable()->after('revisi_dari_id');
            $table->text('revisi_alasan')->nullable()->after('revisi_ke');
        });
    }

    public function down(): void
    {
        Schema::table('sp_jagas', function (Blueprint $table) {
            $table->dropIndex(['revisi_dari_id']);
            $table->dropColumn(['revisi_dari_id', 'revisi_ke', 'revisi_alasan']);
        });
    }
};
