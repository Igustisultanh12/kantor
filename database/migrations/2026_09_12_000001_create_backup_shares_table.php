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
        if (!Schema::hasTable('backup_shares')) {
            Schema::create('backup_shares', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pc_id')->constrained('pcs')->onDelete('cascade');
                $table->foreignId('backup_id')->nullable()->constrained('backups')->onDelete('cascade');
                $table->string('share_token', 64)->unique()->index();
                $table->string('pin', 255);
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->string('share_name')->nullable();
                $table->unsignedBigInteger('access_count')->default(0);
                $table->timestamp('last_accessed_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_shares');
    }
};
