<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_sub_categories', function (Blueprint $table) {
            $table->id();
            // Menghubungkan ke tabel categories utama
            $table->foreignId('letter_category_id')->constrained('categories')->onDelete('cascade');
            $table->string('name'); // Contoh: NIKAH, PNS, PKL
            $table->string('sub_code')->nullable(); // Contoh: 01, 02
            $table->timestamps();
            $table->softDeletes(); // Penting: Karena di error Bapak ada pengecekan deleted_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_sub_categories');
    }
};