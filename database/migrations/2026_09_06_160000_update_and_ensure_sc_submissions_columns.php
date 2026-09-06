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
        if (!Schema::hasTable('sc_submissions')) {
            Schema::create('sc_submissions', function (Blueprint $table) {
                $table->id();
                $table->string('tracking_code')->nullable()->unique();
                $table->string('nama')->nullable();
                $table->string('pangkat_korps')->nullable();
                $table->string('identifier_type')->default('nrp');
                $table->string('identifier_number')->nullable()->index();
                $table->string('kesatuan')->nullable();
                $table->string('jabatan')->nullable();
                $table->string('phone')->nullable();
                $table->string('keperluan')->nullable();
                $table->unsignedTinyInteger('current_stage')->default(1)->index();
                $table->string('status')->default('proses')->index();
                $table->unsignedBigInteger('skhpp_id')->nullable();
                $table->string('nomor_surat_rh')->nullable();
                $table->string('nomor_skhpp')->nullable();
                $table->string('file_skhpp')->nullable();
                $table->string('nomor_sc')->nullable();
                $table->string('file_sc_preview')->nullable();
                $table->timestamp('sc_preview_uploaded_at')->nullable();
                $table->timestamp('sc_preview_expired_at')->nullable();
                $table->text('catatan_petugas')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('sc_submissions', function (Blueprint $table) {
                if (!Schema::hasColumn('sc_submissions', 'tracking_code')) {
                    $table->string('tracking_code')->nullable()->index();
                }
                if (!Schema::hasColumn('sc_submissions', 'nama')) {
                    $table->string('nama')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'pangkat_korps')) {
                    $table->string('pangkat_korps')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'identifier_type')) {
                    $table->string('identifier_type')->default('nrp');
                }
                if (!Schema::hasColumn('sc_submissions', 'identifier_number')) {
                    $table->string('identifier_number')->nullable()->index();
                }
                if (!Schema::hasColumn('sc_submissions', 'kesatuan')) {
                    $table->string('kesatuan')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'jabatan')) {
                    $table->string('jabatan')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'phone')) {
                    $table->string('phone')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'keperluan')) {
                    $table->string('keperluan')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'current_stage')) {
                    $table->unsignedTinyInteger('current_stage')->default(1)->index();
                }
                if (!Schema::hasColumn('sc_submissions', 'status')) {
                    $table->string('status')->default('proses')->index();
                }
                if (!Schema::hasColumn('sc_submissions', 'skhpp_id')) {
                    $table->unsignedBigInteger('skhpp_id')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'nomor_surat_rh')) {
                    $table->string('nomor_surat_rh')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'nomor_skhpp')) {
                    $table->string('nomor_skhpp')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'file_skhpp')) {
                    $table->string('file_skhpp')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'nomor_sc')) {
                    $table->string('nomor_sc')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'file_sc_preview')) {
                    $table->string('file_sc_preview')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'sc_preview_uploaded_at')) {
                    $table->timestamp('sc_preview_uploaded_at')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'sc_preview_expired_at')) {
                    $table->timestamp('sc_preview_expired_at')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'catatan_petugas')) {
                    $table->text('catatan_petugas')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (!Schema::hasTable('sc_submission_logs')) {
            Schema::create('sc_submission_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sc_submission_id')->index();
                $table->unsignedTinyInteger('stage');
                $table->string('stage_title');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pertahankan integritas data
    }
};
