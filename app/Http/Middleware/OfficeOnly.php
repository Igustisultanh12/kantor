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

        Log::info("RADAR ACCESS - User: " . (auth()->user()->name ?? 'Guest') . " | IP: " . $clientIp);

        // 2. Izinkan Seluruh Personel Terautentikasi Mengakses Modul Backup
        if (auth()->check()) {
            return $next($request);
        }

        // 3. Verifikasi Jaringan Kantor untuk Akses Tamu
        $isOffice = OfficeNetwork::where('ip_address', $clientIp)
                                ->where('is_active', true)
                                ->exists();

        if (!$isOffice) {
            return redirect()->route('dashboard')->with('error', 'Radar: Anda di luar area kantor! IP Terdeteksi: ' . $clientIp);
        }

        return $next($request);
    }
}