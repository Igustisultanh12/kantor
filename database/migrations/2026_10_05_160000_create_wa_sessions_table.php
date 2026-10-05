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
        if (!Schema::hasTable('wa_sessions')) {
            Schema::create('wa_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('session_id')->unique();
                $table->string('label');
                $table->string('phone_number')->nullable();
                $table->string('status')->default('disconnected'); // disconnected, qr_ready, connecting, connected
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('role_access')->default('all'); // all, admin, komandan
                $table->timestamp('last_active_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_sessions');
    }
};
