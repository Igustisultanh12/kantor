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
        if (Schema::hasTable('backup_shares')) {
            if (!Schema::hasColumn('backup_shares', 'allow_guest')) {
                Schema::table('backup_shares', function (Blueprint $table) {
                    $table->boolean('allow_guest')->default(true)->after('is_active');
                });
            }
        }

        if (!Schema::hasTable('backup_share_guests')) {
            Schema::create('backup_share_guests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('backup_share_id')->constrained('backup_shares')->onDelete('cascade');
                $table->string('nrp', 50)->nullable()->index();
                $table->string('nama', 150)->index();
                $table->string('satuan', 150)->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('accessed_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_share_guests');

        if (Schema::hasTable('backup_shares') && Schema::hasColumn('backup_shares', 'allow_guest')) {
            Schema::table('backup_shares', function (Blueprint $table) {
                $table->dropColumn('allow_guest');
            });
        }
    }
};
