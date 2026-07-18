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
        Schema::table('users', function (Blueprint $table) {
        // Cek dulu, kalau belum ada baru buat
        if (!Schema::hasColumn('users', 'activation_token')) {
            $table->string('activation_token')->nullable();
        }
        
        // Karena is_active sudah ada, kita lewati saja
        if (!Schema::hasColumn('users', 'is_active')) {
            $table->boolean('is_active')->default(false);
        }
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
