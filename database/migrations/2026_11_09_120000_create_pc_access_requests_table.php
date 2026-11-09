<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan akses ke penyimpanan backup milik rekan.
 * Alur: verifying (kode WA dikirim ke peminta) -> pending (menunggu pemilik)
 *       -> approved / rejected; akses yang disetujui bisa dicabut (revoked)
 *       atau dibatalkan peminta (cancelled).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pc_access_requests')) {
            return;
        }

        Schema::create('pc_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pc_id')->constrained('pcs')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('verifying')->index();
            $table->string('reason', 500)->nullable();
            $table->string('otp_hash')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('otp_sent_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();

            $table->index(['pc_id', 'requester_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_access_requests');
    }
};
