<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashes', function (Blueprint $table) {
            // Mengubah tipe data menjadi TEXT agar muat menampung banyak nama file JSON
            $table->text('receipt_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cashes', function (Blueprint $table) {
            $table->string('receipt_path', 255)->nullable()->change();
        });
    }
};