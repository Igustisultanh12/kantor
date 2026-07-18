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
    Schema::create('access_requests', function (Blueprint $table) {
        $table->id();
        $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('pc_id')->constrained('pcs')->onDelete('cascade');
        $table->text('reason');
        $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_requests');
    }
};
