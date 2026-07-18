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
        Schema::create('soldier_violations', function (Blueprint $table) {
    $table->id();
    // Data Personel
    $table->string('name');
    $table->string('nrp')->unique();
    $table->string('rank'); // Pangkat
    $table->string('position'); // Jabatan
    $table->string('unit'); // Satuan Kerja
    
    // Data Kasus
    $table->text('case_description'); // Kasus tentang apa
    $table->date('incident_date'); // TMT kapan / Tanggal kejadian
    
    // Data Dinamis (Update Berkala)
    $table->text('case_development'); // Pengembangan kasus sampai mana
    $table->enum('status', ['PROSES', 'SIDANG', 'SELESAI', 'BANDING'])->default('PROSES');
    
    $table->timestamps();
    $table->softDeletes();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('soldier_violations');
    }
};
