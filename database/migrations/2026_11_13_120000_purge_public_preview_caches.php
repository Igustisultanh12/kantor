<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

/**
 * Audit S05: salinan terdekripsi (pratinjau Office, thumbnail, JPG dari RAW) dulu
 * disimpan di storage/app/public dan bisa dibuka tanpa login. Cache kini di
 * storage/app/private; cache lama di folder publik dihapus (akan dibuat ulang).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['office_cache', 'cache_thumbs', 'cache_arw'] as $dir) {
            $path = storage_path('app/public/' . $dir);
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
