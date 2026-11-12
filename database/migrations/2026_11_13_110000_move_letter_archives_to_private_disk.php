<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Audit S04: PDF arsip surat (bisa berklasifikasi rahasia) dipindah dari disk public
 * (bisa dibuka tanpa login lewat /storage/...) ke disk privat. Disajikan lewat
 * rute /letters/{id}/file yang mewajibkan login + izin. Aman diulang (idempoten).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('letters')) {
            return;
        }
        $moved = 0;
        DB::table('letters')->whereNotNull('file_path')->orderBy('id')->each(function ($letter) use (&$moved) {
            $path = (string) $letter->file_path;
            if ($path === '' || str_contains($path, '..')) {
                return;
            }
            try {
                if (Storage::disk('public')->exists($path) && !Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->writeStream($path, Storage::disk('public')->readStream($path));
                    if (Storage::disk('local')->exists($path)) {
                        Storage::disk('public')->delete($path);
                        $moved++;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("[MIGRASI ARSIP] Gagal memindahkan {$path}: " . $e->getMessage());
            }
        });
        Log::info("[MIGRASI ARSIP] {$moved} berkas arsip dipindah ke disk privat.");
    }

    public function down(): void
    {
        // Tidak dikembalikan ke disk publik (alasan keamanan).
    }
};
