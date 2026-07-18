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
    Schema::create('audit_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Admin yang bertindak
        $table->string('admin_name'); // Nama Admin untuk pencatatan cepat
        $table->string('action'); // Contoh: KONFIRMASI, HAPUS, UPDATE
        $table->string('target_personnel')->nullable(); // Nama personel yang diproses
        $table->text('description'); // Detail tindakan
        $table->string('ip_address')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
