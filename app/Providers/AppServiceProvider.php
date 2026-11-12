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
        // Realtime (Laravel Reverb): setiap query tulis dari aksi pengguna (POST/PUT/PATCH/DELETE)
        // atau proses latar menandai modulnya berubah, lalu disiarkan sekali di akhir request.
        // Request GET tidak memicu sinyal agar penandaan "sudah dibaca" saat polling tidak berputar tanpa henti.
        if (\App\Support\Realtime::enabled()) {
            \Illuminate\Support\Facades\DB::listen(function (\Illuminate\Database\Events\QueryExecuted $query) {
                if (!app()->runningInConsole() && in_array(request()->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                    return;
                }
                \App\Support\Realtime::inspectSql($query->sql);
            });
            // Queue worker & perintah artisan: siarkan setelah tiap job / saat perintah selesai.
            \Illuminate\Support\Facades\Event::listen([\Illuminate\Queue\Events\JobProcessed::class, \Illuminate\Queue\Events\JobFailed::class], fn () => \App\Support\Realtime::flush());
            if (app()->runningInConsole()) {
                app()->terminating(fn () => \App\Support\Realtime::flush());
            }
        }

        // Set Standar Locale Indonesia untuk Tanggal & Waktu (Baku)
        \Carbon\Carbon::setLocale('id');
        \Illuminate\Support\Carbon::setLocale('id');
        setlocale(LC_TIME, 'id_ID.utf8', 'id_ID', 'id', 'indonesian');

        /**
         * SOLUSI MIXED CONTENT
         * Memaksa semua URL aset (JS/CSS) menggunakan HTTPS agar tidak diblokir browser.
         * Kode ini memastikan tombol SI SINDEN kembali aktif.
         */
        // Juga bila APP_URL https (server ini di belakang Cloudflare/proxy; APP_ENV belum tentu "production").
        if (config('app.env') === 'production' || env('APP_ENV') === 'production'
            || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Login aplikasi: dibatasi per akun + IP (bukan per IP saja) agar banyak personel di
        // Wi-Fi kantor yang sama (satu IP publik) tidak saling kena "Too Many Attempts".
        // Alur OTP memakai 2 permintaan per login, jadi batasnya dilonggarkan.
        \Illuminate\Support\Facades\RateLimiter::for('mobile-login', function (\Illuminate\Http\Request $request) {
            $data = $request->json()->all() ?: $request->all();
            $id = strtolower(trim((string) ($data['username'] ?? $data['email'] ?? $data['nrp'] ?? '')));
            $tooMany = fn () => response()->json([
                'status' => 'error',
                'code' => 'rate_limited',
                'message' => 'Terlalu banyak percobaan masuk. Tunggu 1 menit lalu coba lagi.',
            ], 429);
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('mlogin:' . $id . '|' . $request->ip())->response($tooMany),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by('mlogin-ip:' . $request->ip())->response($tooMany),
            ];
        });

        Vite::prefetch(concurrency: 3);
    }
}