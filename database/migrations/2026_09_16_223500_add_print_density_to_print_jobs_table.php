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
        if (Schema::hasTable('print_jobs')) {
            Schema::table('print_jobs', function (Blueprint $table) {
                if (!Schema::hasColumn('print_jobs', 'print_density')) {
                    $table->string('print_density', 20)->default('normal')->after('color_mode');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('print_jobs')) {
            Schema::table('print_jobs', function (Blueprint $table) {
                if (Schema::hasColumn('print_jobs', 'print_density')) {
                    $table->dropColumn('print_density');
                }
            });
        }
    }
};
