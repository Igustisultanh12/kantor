<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_activities', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Judul Kegiatan
            $table->text('description')->nullable(); // Detail Rencana
            
            // Bidang (IPOLEKSOSBUDHANKAM)
            // Bisa berisi: Politik, Ekonomi, Sosial Budaya, Keamanan, dll.
            $table->string('category'); 
            
            // Wilayah
            $table->enum('province', ['Bali', 'Jawa Timur', 'Jawa Tengah']);
            $table->string('city')->nullable(); // Kota/Kabupaten
            
            // Lokasi & Pin (Koordinat)
            $table->string('location_name'); // Nama Tempat
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            
            // Waktu & Status
            $table->date('activity_date');
            $table->enum('status', ['RENCANA', 'BERJALAN', 'SELESAI'])->default('RENCANA');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_activities');
    }
};