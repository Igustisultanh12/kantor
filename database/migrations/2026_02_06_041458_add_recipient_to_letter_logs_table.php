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
        Schema::table('letter_logs', function (Blueprint $table) {
        // Menggunakan text agar bisa menampung isian yang panjang
        $table->text('recipient')->nullable()->after('subject');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letter_logs', function (Blueprint $table) {
            //
        });
    }
};
