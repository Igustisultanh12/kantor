<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\OfficeNetwork;
use Illuminate\Support\Facades\Log;

class OfficeOnly
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Deteksi IP dengan Radar Cloudflare
        $clientIp = $request->header('CF-Connecting-IP') ?? $request->ip();

        // ==========================================================
        // 🚀 OPERASI TEMBAK LANGSUNG (DEBUG MODE)
        // Lepas tanda komentar (//) pada baris di bawah ini untuk 
        // memaksa IP muncul di layar putih browser.
        // ==========================================================
        
        // dd("RADAR SINDEN: IP yang terdeteksi sistem adalah: " . $clientIp);

        // ==========================================================

        // 2. Catat ke Log (Cadangan)
        Log::info("RADAR ACCESS - User: " . (auth()->user()->name ?? 'Guest') . " | IP: " . $clientIp);

        // 3. Protokol Bypass Sultan (Bapak)
        // Pastikan nama ini sama persis dengan yang ada di database Bapak
        if (auth()->check() && (
            auth()->user()->name === 'I Gusti Sultan H.A, A.Md.Kom' || 
            auth()->user()->role === 'admin'
        )) {
            return $next($request);
        }

        // 4. Verifikasi Jaringan Kantor
        $isOffice = OfficeNetwork::where('ip_address', $clientIp)
                                ->where('is_active', true)
                                ->exists();

        // 5. Eksekusi Blokade dengan Pesan Error Berisi IP
        if (!$isOffice) {
            return redirect()->route('dashboard')->with('error', 'Radar: Anda di luar area kantor! IP Terdeteksi: ' . $clientIp);
        }

        return $next($request);
    }
}