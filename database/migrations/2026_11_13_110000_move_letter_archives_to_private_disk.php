<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Audit S04: PDF arsip surat (bisa berklasifikasi rahasia) dipindah dari disk public
 * (bisa dibuka tanpa login lewat /storage/...) ke disk privat. Disajikan lewat
 * rute /letters/{id}/file yang mewajibkan login + izin. Aman diulang (idempoten):
 * berkas publik hanya dihapus SETELAH salinannya di disk privat terverifikasi.
 *
 * WAJIB dijalankan sebagai user web (mis. `sudo -u www php artisan migrate --force`)
 * agar folder baru dimiliki user web dan tetap bisa ditulisi aplikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('letters')) {
            return;
        }

        $privateRoot = storage_path('app/private');
        $publicRoot = storage_path('app/public');
        if (!is_writable($privateRoot) || !is_writable($publicRoot)) {
            throw new \RuntimeException(
                "Folder storage tidak bisa ditulisi oleh user '" . get_current_user() . "' ({$privateRoot}). "
                . 'Jalankan migrasi sebagai user web, contoh: sudo -u www php artisan migrate --force. '
                . 'Tidak ada berkas arsip yang diubah.'
            );
        }

        $moved = 0;
        $failed = 0;
        DB::table('letters')->whereNotNull('file_path')->orderBy('id')->each(function ($letter) use (&$moved, &$failed) {
            $path = (string) $letter->file_path;
            if ($path === '' || str_contains($path, '..')) {
                return;
            }
            try {
                if (Storage::disk('public')->exists($path) && !Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->writeStream($path, Storage::disk('public')->readStream($path));
                    if (Storage::disk('local')->exists($path)
                        && Storage::disk('local')->size($path) === Storage::disk('public')->size($path)) {
                        Storage::disk('public')->delete($path);
                        $moved++;
                    } else {
                        $failed++;
                    }
                }
            } catch (\Throwable $e) {
                $failed++;
                fwrite(STDERR, "[MIGRASI ARSIP] Gagal memindahkan {$path}: {$e->getMessage()}\n");
            }
        });

        fwrite(STDOUT, "[MIGRASI ARSIP] {$moved} berkas dipindah ke disk privat, {$failed} gagal (tetap di disk publik dan masih bisa dibuka lewat aplikasi).\n");
    }

    public function down(): void
    {
        // Tidak dikembalikan ke disk publik (alasan keamanan).
    }
};
