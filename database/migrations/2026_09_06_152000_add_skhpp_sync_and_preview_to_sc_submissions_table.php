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
        if (Schema::hasTable('sc_submissions')) {
            Schema::table('sc_submissions', function (Blueprint $table) {
                if (!Schema::hasColumn('sc_submissions', 'skhpp_id')) {
                    $table->foreignId('skhpp_id')->nullable()->after('id')->constrained('skhpps')->nullOnDelete();
                }
                if (!Schema::hasColumn('sc_submissions', 'file_skhpp')) {
                    $table->string('file_skhpp')->nullable()->after('nomor_skhpp');
                }
                if (!Schema::hasColumn('sc_submissions', 'file_sc_preview')) {
                    $table->string('file_sc_preview')->nullable()->after('nomor_sc');
                }
                if (!Schema::hasColumn('sc_submissions', 'sc_preview_uploaded_at')) {
                    $table->timestamp('sc_preview_uploaded_at')->nullable()->after('file_sc_preview');
                }
                if (!Schema::hasColumn('sc_submissions', 'sc_preview_expired_at')) {
                    $table->timestamp('sc_preview_expired_at')->nullable()->after('sc_preview_uploaded_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sc_submissions')) {
            Schema::table('sc_submissions', function (Blueprint $table) {
                if (Schema::hasColumn('sc_submissions', 'skhpp_id')) {
                    $table->dropForeign(['skhpp_id']);
                    $table->dropColumn('skhpp_id');
                }
                $table->dropColumn([
                    'file_skhpp',
                    'file_sc_preview',
                    'sc_preview_uploaded_at',
                    'sc_preview_expired_at',
                ]);
            });
        }
    }
};
