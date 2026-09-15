<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambah durasi akses tamu (dalam jam) pada backup_shares
        Schema::table('backup_shares', function (Blueprint $table) {
            if (!Schema::hasColumn('backup_shares', 'guest_duration_hours')) {
                $table->integer('guest_duration_hours')->default(24)->after('allow_guest')
                    ->comment('Masa berlaku akses pengunjung tamu dalam hitungan jam');
            }
        });

        // 2. Tambah no WhatsApp dan waktu kedaluwarsa akses tamu pada backup_share_guests
        Schema::table('backup_share_guests', function (Blueprint $table) {
            if (!Schema::hasColumn('backup_share_guests', 'whatsapp')) {
                $table->string('whatsapp', 30)->nullable()->after('satuan')
                    ->comment('Nomor WhatsApp aktif pengunjung untuk notifikasi masa berlaku');
            }
            if (!Schema::hasColumn('backup_share_guests', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('accessed_at')
                    ->comment('Waktu berakhirnya masa berlaku sesi tamu');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backup_shares', function (Blueprint $table) {
            if (Schema::hasColumn('backup_shares', 'guest_duration_hours')) {
                $table->dropColumn('guest_duration_hours');
            }
        });

        Schema::table('backup_share_guests', function (Blueprint $table) {
            $dropCols = [];
            if (Schema::hasColumn('backup_share_guests', 'whatsapp')) {
                $dropCols[] = 'whatsapp';
            }
            if (Schema::hasColumn('backup_share_guests', 'expires_at')) {
                $dropCols[] = 'expires_at';
            }
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};
