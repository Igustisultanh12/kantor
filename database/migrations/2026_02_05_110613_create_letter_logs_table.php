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
        Schema::create('letter_logs', function (Blueprint $table) {
        $table->id();
        $table->string('full_number')->unique(); // Sifat/Nomor/Kategori/Bulan/Tahun
        $table->integer('sequence');           // Angka urut (misal: 125)
        $table->string('priority');           // B, R, K, SR
        $table->foreignId('category_id')->constrained();
        $table->string('subject');            // Perihal singkat
        $table->date('date');                 // Tanggal pengambilan nomor
        $table->boolean('is_archived')->default(false); // Penanda sudah di-upload ke arsip atau belum
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_logs');
    }
};
