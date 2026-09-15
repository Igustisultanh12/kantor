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
        Schema::table('backup_share_guests', function (Blueprint $table) {
            if (!Schema::hasColumn('backup_share_guests', 'pangkat')) {
                $table->string('pangkat', 100)->nullable()->after('nrp');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backup_share_guests', function (Blueprint $table) {
            if (Schema::hasColumn('backup_share_guests', 'pangkat')) {
                $table->dropColumn('pangkat');
            }
        });
    }
};
