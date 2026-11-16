<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TTD manual SKHPP: petugas mencetak, Komandan menandatangani di kertas, hasilnya diunggah.
     * - ttd_mode: null = TTE (QR), 'manual' = tanda tangan basah.
     * - signed_file: berkas bertanda tangan di disk privat.
     */
    public function up(): void
    {
        Schema::table('skhpps', function (Blueprint $table) {
            $table->string('ttd_mode', 10)->nullable()->after('status');
            $table->string('signed_file')->nullable()->after('ttd_mode');
            $table->unsignedBigInteger('signed_uploaded_by')->nullable()->after('signed_file');
            $table->timestamp('signed_uploaded_at')->nullable()->after('signed_uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('skhpps', function (Blueprint $table) {
            $table->dropColumn(['ttd_mode', 'signed_file', 'signed_uploaded_by', 'signed_uploaded_at']);
        });
    }
};
