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
        DB::statement("ALTER TABLE skhpps MODIFY COLUMN kategori_personel VARCHAR(50) NOT NULL DEFAULT 'militer'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
