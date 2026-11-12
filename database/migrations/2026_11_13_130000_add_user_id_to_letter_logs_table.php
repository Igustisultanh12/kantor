<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda Surat: catat siapa yang mem-booking nomor agar pem-booking bisa
 * membatalkan nomornya sendiri (booking lama tanpa pemilik hanya oleh Admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('letter_logs') && !Schema::hasColumn('letter_logs', 'user_id')) {
            Schema::table('letter_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('letter_logs', 'user_id')) {
            Schema::table('letter_logs', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
    }
};
