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
        Schema::create('open_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 64)->unique();
            $table->string('title');
            $table->date('date');
            $table->string('time_start', 10)->nullable();
            $table->string('time_end', 10)->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->json('custom_fields')->nullable();
            $table->boolean('require_signature')->default(false);

            // Petugas Pembuat Absensi
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('creator_name')->nullable();
            $table->string('creator_pangkat_nrp')->nullable();
            $table->string('creator_jabatan')->nullable();

            // Pejabat Penandatangan Resmi (Komandan Detasemen Intelijen Kodaeral V)
            $table->string('commander_name')->nullable();
            $table->string('commander_pangkat_nrp')->nullable();
            $table->string('commander_jabatan')->default('Komandan Detasemen Intelijen Kodaeral V');

            $table->timestamps();
        });

        Schema::create('open_meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('open_meeting_id')->constrained('open_meetings')->cascadeOnDelete();
            $table->string('name');
            $table->string('pangkat_nrp')->nullable();
            $table->string('jabatan')->nullable();
            $table->json('custom_data')->nullable();
            $table->longText('signature')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_meeting_attendees');
        Schema::dropIfExists('open_meetings');
    }
};
