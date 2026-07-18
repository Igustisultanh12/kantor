<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('stamp_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained(); // Personel yang melakukan stempel
        $table->string('subject');
        $table->string('file_path'); // Lokasi file PDF yang sudah distempel
        $table->float('x')->nullable();
        $table->float('y')->nullable();
        $table->integer('page')->default(1);
        $table->boolean('is_manual')->default(false); // Penanda jika hasil upload manual
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_logs');
    }
};
