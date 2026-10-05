<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sp_jagas') && Schema::hasColumn('sp_jagas', 'nomor_urut')) {
            DB::statement("ALTER TABLE sp_jagas MODIFY COLUMN nomor_urut VARCHAR(50) NULL DEFAULT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sp_jagas') && Schema::hasColumn('sp_jagas', 'nomor_urut')) {
            DB::statement("ALTER TABLE sp_jagas MODIFY COLUMN nomor_urut INT NULL DEFAULT NULL");
        }
    }
};
