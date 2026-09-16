<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('print_jobs')) {
            try {
                DB::statement('ALTER TABLE print_jobs MODIFY original_file_path VARCHAR(255) NULL');
                DB::statement('ALTER TABLE print_jobs MODIFY preview_pdf_path VARCHAR(255) NULL');
                DB::statement('ALTER TABLE print_jobs MODIFY printable_pdf_path VARCHAR(255) NULL');
            } catch (\Throwable $e) {
                // Fallback using Schema
                Schema::table('print_jobs', function (Blueprint $table) {
                    if (Schema::hasColumn('print_jobs', 'original_file_path')) {
                        $table->string('original_file_path', 255)->nullable()->change();
                    }
                    if (Schema::hasColumn('print_jobs', 'preview_pdf_path')) {
                        $table->string('preview_pdf_path', 255)->nullable()->change();
                    }
                    if (Schema::hasColumn('print_jobs', 'printable_pdf_path')) {
                        $table->string('printable_pdf_path', 255)->nullable()->change();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
