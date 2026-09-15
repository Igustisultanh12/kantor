<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('backup_share_access_logs')) {
            Schema::create('backup_share_access_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('backup_share_id')->constrained('backup_shares')->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->string('access_type', 20)->default('tamu')->index(); // 'personel' | 'tamu'
                $table->string('pangkat', 100)->nullable();
                $table->string('nama', 150)->index();
                $table->string('nrp', 50)->nullable()->index();
                $table->string('satuan', 150)->nullable();
                $table->string('whatsapp', 30)->nullable();
                $table->string('ip_address', 45)->nullable()->index();
                $table->text('user_agent')->nullable();
                $table->unsignedInteger('access_count')->default(1);
                $table->timestamp('first_accessed_at')->nullable();
                $table->timestamp('last_accessed_at')->nullable()->index();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });

            // Backfill riwayat tamu dari tabel backup_share_guests yang sudah ada sebelumnya
            try {
                if (Schema::hasTable('backup_share_guests')) {
                    $existingGuests = DB::table('backup_share_guests')->get();
                    foreach ($existingGuests as $guest) {
                        DB::table('backup_share_access_logs')->insert([
                            'backup_share_id' => $guest->backup_share_id,
                            'user_id' => null,
                            'access_type' => 'tamu',
                            'pangkat' => $guest->pangkat ?? null,
                            'nama' => $guest->nama,
                            'nrp' => $guest->nrp,
                            'satuan' => $guest->satuan,
                            'whatsapp' => $guest->whatsapp,
                            'ip_address' => $guest->ip_address,
                            'user_agent' => $guest->user_agent,
                            'access_count' => 1,
                            'first_accessed_at' => $guest->accessed_at ?? $guest->created_at,
                            'last_accessed_at' => $guest->accessed_at ?? $guest->created_at,
                            'expires_at' => $guest->expires_at,
                            'created_at' => $guest->created_at ?? now(),
                            'updated_at' => $guest->updated_at ?? now(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                // Abaikan kesalahan minor saat migrasi data lama
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_share_access_logs');
    }
};
