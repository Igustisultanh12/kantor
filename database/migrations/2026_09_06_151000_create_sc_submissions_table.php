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
        Schema::create('sc_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_code')->unique();
            $table->string('nama');
            $table->string('pangkat_korps')->nullable();
            $table->string('identifier_type')->default('nrp'); // nrp, nip, nik
            $table->string('identifier_number');
            $table->string('kesatuan')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('phone')->nullable();
            $table->string('keperluan')->nullable();
            $table->unsignedTinyInteger('current_stage')->default(1); // 1 sampai 10
            $table->string('status')->default('proses'); // proses, selesai, perbaikan, ditolak
            $table->string('nomor_surat_rh')->nullable();
            $table->string('nomor_skhpp')->nullable();
            $table->string('nomor_sc')->nullable();
            $table->text('catatan_petugas')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('identifier_number');
            $table->index('tracking_code');
            $table->index('current_stage');
            $table->index('status');
        });

        Schema::create('sc_submission_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sc_submission_id')->constrained('sc_submissions')->onDelete('cascade');
            $table->unsignedTinyInteger('stage');
            $table->string('stage_title');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->timestamps();

            $table->index('sc_submission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sc_submission_logs');
        Schema::dropIfExists('sc_submissions');
    }
};
