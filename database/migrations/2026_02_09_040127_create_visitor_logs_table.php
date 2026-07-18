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
    Schema::create('visitor_logs', function (Blueprint $blueprint) {
        $blueprint->id();
        $blueprint->foreignId('user_id')->constrained()->onDelete('cascade'); // Tambahkan baris ini
        $blueprint->string('user_name'); 
        $blueprint->string('nrp')->nullable(); 
        $blueprint->string('ip_address')->nullable();
        $blueprint->string('user_agent')->nullable();
        $blueprint->timestamp('login_at')->useCurrent();
        $blueprint->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_logs');
    }
};
