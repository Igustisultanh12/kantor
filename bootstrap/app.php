<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\LogVisitor;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\OfficeOnly; // Pastikan ini di-import

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        
        // 0. RADAR KEAMANAN TINGKAT TINGGI (HTTP REQUEST SMUGGLING, STREAM DoS, INJEKSI HEADER)
        $middleware->prepend(\App\Http\Middleware\SecurityFirewallMiddleware::class);

        // Pengalihan cerdas tamu belum login: pertahankan URL tujuan di query 'redirect'
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) => route('login', ['redirect' => $request->fullUrl()]));

        // 1. SOLUSI UTAMA TUNNELING & SSL
        // Menjaga agar aset (CSS/JS) tetap HTTPS meski di balik Cloudflare/Proxy
        // Audit R01: hanya proxy yang benar-benar dipakai (Cloudflare + loopback) yang dipercaya,
        // supaya X-Forwarded-For palsu tidak bisa mengakali rate limit berbasis IP.
        // Bisa ditimpa lewat TRUSTED_PROXIES (dipisah koma) di .env.
        $middleware->trustProxies(at: array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES',
            // Loopback + jaringan privat (proxy/tunnel lokal di server) + rentang Cloudflare.
            '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,fc00::/7,'
            . '173.245.48.0/20,103.21.244.0/22,103.22.200.0/22,103.31.4.0/22,141.101.64.0/18,108.162.192.0/18,'
            . '190.93.240.0/20,188.114.96.0/20,197.234.240.0/22,198.41.128.0/17,162.158.0.0/15,104.16.0.0/13,'
            . '104.24.0.0/14,172.64.0.0/13,131.0.72.0/22,'
            . '2400:cb00::/32,2606:4700::/32,2803:f800::/32,2405:b500::/32,2405:8100::/32,2a06:98c0::/29,2c0f:f248::/32'
        ))))));

        // =====================================================================
        // SULTAN CONFIG: KECUALIKAN GERBANG MASUK FILE FISIK PESS DARI CSRF
        // =====================================================================
        $middleware->validateCsrfTokens(except: [
            'api/v1/pess/receive-submission',
            'login',
            'api/login',
            'api/mobile/*',
            'api/mobile/login',
            'api/mobile/config'
        ]);

        // 2. REGISTRASI SELURUH ALIAS MIDDLEWARE (Disatukan di sini)
        $middleware->alias([
            'role'        => RoleMiddleware::class,
            'office.only' => OfficeOnly::class, // Pagar Radar IP Kantor
            // Otorisasi web dengan aturan yang sama seperti API mobile (audit 2026-11)
            'perm'        => \App\Http\Middleware\PermissionMiddleware::class,
            // Autentikasi token Sanctum + permission untuk seluruh /api/mobile/*
            'mobile.auth' => \App\Http\Middleware\MobileApiAuth::class,
        ]);

        // 3. KONFIGURASI STACK MIDDLEWARE WEB
        // Sinyal realtime (Reverb) dikirim setelah respons, untuk semua rute.
        $middleware->append(\App\Http\Middleware\FlushRealtime::class);

        $middleware->web(append: [
            // Sesi akun ber-MFA tanpa OTP terverifikasi dikeluarkan (audit K04).
            \App\Http\Middleware\CheckMFA::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            
            /** * LogVisitor: Mencatat aktivitas Nama & NRP personel.
             * Diletakkan di stack WEB agar bisa membaca session login.
             */
            LogVisitor::class,

            // Catat perangkat/sesi web yang sedang dipakai (halaman Perangkat & sesi).
            \App\Http\Middleware\TrackDeviceSession::class,

            // Jejak aksi pengguna terhadap data (Log Aktivitas, khusus admin). Login tidak dicatat di sini.
            \App\Http\Middleware\RecordActivity::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();