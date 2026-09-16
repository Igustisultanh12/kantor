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
        Schema::table('print_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('print_jobs', 'paper_size')) {
                $table->string('paper_size', 20)->default('A4')->after('color_mode');
            }
            if (!Schema::hasColumn('print_jobs', 'printer_brand')) {
                $table->string('printer_brand', 30)->default('brother')->after('paper_size');
            }
            if (!Schema::hasColumn('print_jobs', 'print_quality')) {
                $table->string('print_quality', 30)->default('normal')->after('printer_brand');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('print_jobs', 'paper_size')) {
                $table->dropColumn('paper_size');
            }
            if (Schema::hasColumn('print_jobs', 'printer_brand')) {
                $table->dropColumn('printer_brand');
            }
            if (Schema::hasColumn('print_jobs', 'print_quality')) {
                $table->dropColumn('print_quality');
            }
        });
    }
};
