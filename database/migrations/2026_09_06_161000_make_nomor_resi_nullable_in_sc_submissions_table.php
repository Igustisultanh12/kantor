<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sc_submissions')) {
            if (Schema::hasColumn('sc_submissions', 'nomor_resi')) {
                try {
                    DB::statement("ALTER TABLE sc_submissions MODIFY COLUMN nomor_resi VARCHAR(255) NULL DEFAULT NULL");
                } catch (\Throwable $e) {
                    // Abaikan jika tidak didukung
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
