<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat & telegram masuk bernomor agenda + disposisi wajib (Komandan → jabatan/role → lapor selesai)
 * + lembar disposisi A5 yang digabung ke depan PDF arsip.
 * Surat masuk lama tetap disposition_status = null ("arsip lama"), tidak terkunci.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            if (!Schema::hasColumn('letters', 'agenda_number')) {
                $table->string('agenda_number')->nullable()->unique();
            }
            if (!Schema::hasColumn('letters', 'agenda_sequence')) {
                $table->string('agenda_sequence')->nullable();
            }
            if (!Schema::hasColumn('letters', 'received_at')) {
                $table->date('received_at')->nullable();
            }
            if (!Schema::hasColumn('letters', 'recorded_by')) {
                $table->foreignId('recorded_by')->nullable();
            }
            if (!Schema::hasColumn('letters', 'disposition_status')) {
                $table->string('disposition_status', 20)->nullable()->index();
            }
            // Telegram: Tanggal-Waktu-Urutan (TWU) sesuai lembar disposisi telegram.
            if (!Schema::hasColumn('letters', 'twu')) {
                $table->string('twu', 100)->nullable();
            }
            // Jumlah halaman lembar disposisi di depan berkas gabungan (null = berkas belum digabung).
            if (!Schema::hasColumn('letters', 'disposition_sheet_pages')) {
                $table->unsignedTinyInteger('disposition_sheet_pages')->nullable();
            }
            // Cadangan bila PDF unggahan tidak bisa digabung: lembar disposisi disimpan terpisah.
            if (!Schema::hasColumn('letters', 'disposition_sheet_path')) {
                $table->string('disposition_sheet_path')->nullable();
            }
        });

        if (!Schema::hasTable('letter_dispositions')) {
            Schema::create('letter_dispositions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('letter_id')->constrained('letters')->cascadeOnDelete();
                $table->foreignId('given_by')->nullable();
                $table->json('instructions')->nullable();
                $table->text('note')->nullable();
                $table->string('urgency', 20)->default('biasa');
                $table->date('due_date')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('letter_disposition_targets')) {
            Schema::create('letter_disposition_targets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('disposition_id')->constrained('letter_dispositions')->cascadeOnDelete();
                $table->string('role', 40)->index();
                $table->string('status', 20)->default('baru');
                $table->foreignId('received_by')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->foreignId('completed_by')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('report_note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_disposition_targets');
        Schema::dropIfExists('letter_dispositions');
        Schema::table('letters', function (Blueprint $table) {
            $table->dropUnique(['agenda_number']);
            $table->dropIndex(['disposition_status']);
            $table->dropColumn(['agenda_number', 'agenda_sequence', 'received_at', 'recorded_by', 'disposition_status', 'twu', 'disposition_sheet_pages', 'disposition_sheet_path']);
        });
    }
};
