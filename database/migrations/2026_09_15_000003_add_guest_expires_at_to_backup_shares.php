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
        Schema::table('backup_shares', function (Blueprint $table) {
            if (!Schema::hasColumn('backup_shares', 'guest_expires_at')) {
                $table->timestamp('guest_expires_at')->nullable()->after('guest_duration_hours');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backup_shares', function (Blueprint $table) {
            if (Schema::hasColumn('backup_shares', 'guest_expires_at')) {
                $table->dropColumn('guest_expires_at');
            }
        });
    }
};
