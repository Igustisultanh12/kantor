<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() {
    Schema::create('signature_requests', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Personel yang minta
        $table->string('subject'); // Perihal Surat
        $table->string('letter_number')->nullable();
        $table->string('file_path'); // File PDF yang mau di-TTD
        $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
        $table->text('note')->nullable(); // Catatan dari Komandan
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signature_requests');
    }
};
