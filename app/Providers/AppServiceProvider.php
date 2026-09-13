<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // Wajib ditambahkan untuk akses HTTPS

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set Standar Locale Indonesia untuk Tanggal & Waktu (Baku)
        \Carbon\Carbon::setLocale('id');
        \Illuminate\Support\Carbon::setLocale('id');
        setlocale(LC_TIME, 'id_ID.utf8', 'id_ID', 'id', 'indonesian');

        /**
         * SOLUSI MIXED CONTENT
         * Memaksa semua URL aset (JS/CSS) menggunakan HTTPS agar tidak diblokir browser.
         * Kode ini memastikan tombol SI SINDEN kembali aktif.
         */
        if (config('app.env') === 'production' || env('APP_ENV') === 'production') {
            URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);
    }
}