<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nova sudah tidak beta: jadikan tampilan bawaan web (`ui_nova` = 1).
 * Hanya menyetel satu baris pengaturan; pilihan per perangkat pengguna
 * (localStorage) tetap dihormati.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $now = now();
        $exists = DB::table('settings')->where('key', 'ui_nova')->exists();

        if ($exists) {
            DB::table('settings')->where('key', 'ui_nova')->update(['value' => '1', 'updated_at' => $now]);
        } else {
            DB::table('settings')->insert(['key' => 'ui_nova', 'value' => '1', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan: admin bisa mengubahnya di Pengaturan → Tampilan Web.
    }
};
