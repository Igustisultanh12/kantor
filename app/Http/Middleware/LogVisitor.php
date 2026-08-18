<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\VisitorLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Jenssegers\Agent\Agent;

class LogVisitor
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();

            // 1. TANGKAP KOORDINAT DARI LOGIN (Hanya ada saat POST login)
            if ($request->has('latitude') && $request->has('longitude')) {
                Session::put('user_lat', $request->latitude);
                Session::put('user_lng', $request->longitude);
            }

            // Cek duplikasi akses dalam 1 menit terakhir agar database tidak bengkak
            $exists = VisitorLog::where('user_id', $user->id)
                                ->where('login_at', '>', now()->subMinutes(1)) 
                                ->exists();

            if (!$exists) {
                $agent = new Agent();

                $ipAddress = $request->header('X-Forwarded-For') 
                             ? explode(',', $request->header('X-Forwarded-For'))[0] 
                             : $request->ip();

                $platform = $agent->platform(); 
                $device   = $agent->device();   
                $deviceInfo = $agent->isDesktop() ? $platform . " (Desktop)" : $platform . " - " . $device;

                // 2. AMBIL DARI SESSION
                $lat = Session::get('user_lat');
                $lng = Session::get('user_lng');
                
                $isSuspicious = false;
                $location = 'Menunggu GPS...';

                // --- LOGIKA DETEKSI IMPOSSIBLE TRAVEL ---
                if ($lat && $lng) {
                    $location = "GPS: $lat, $lng";

                    // Ambil data login terakhir yang punya koordinat
                    $lastLog = VisitorLog::where('user_id', $user->id)
                                         ->whereNotNull('latitude')
                                         ->orderBy('login_at', 'desc')
                                         ->first();

                    if ($lastLog) {
                        $distance = VisitorLog::calculateDistance($lastLog->latitude, $lastLog->longitude, $lat, $lng);
                        $timeDiff = now()->diffInMinutes($lastLog->login_at);

                        // Ambil waktu tempuh dalam jam (untuk hitung kecepatan)
                        $timeDiffHours = $timeDiff / 60;
                        
                        // Validasi: Jika jarak > 10 KM dalam waktu sangat singkat
                        // Rumus: Kecepatan = Jarak / Waktu. Jika > 250 km/jam (Mustahil di darat)
                        if ($timeDiff > 0) {
                            $speed = $distance / $timeDiffHours;
                            if ($speed > 250 && $distance > 10) {
                                $isSuspicious = true;
                                $location = " INDIKASI MANIPULASI (Kec: " . round($speed) . " km/jam)";
                            }
                        }
                    }
                } else {
                    // Fallback ke IP-API jika GPS tidak ada
                    if ($ipAddress !== '127.0.0.1' && $ipAddress !== '::1') {
                        try {
                            $response = Http::timeout(2)->get("http://ip-api.com/json/{$ipAddress}?fields=city");
                            if ($response->successful()) {
                                $location = $response->json()['city'] . ' (IP Base)';
                            }
                        } catch (\Exception $e) { $location = 'UNKNOWN'; }
                    }
                }

                // 3. SIMPAN KE DATABASE
                VisitorLog::create([
                    'user_id'       => $user->id,
                    'user_name'     => $user->name,
                    'nrp'           => $user->nrp,
                    'ip_address'    => $ipAddress,
                    'location'      => $location,
                    'latitude'      => $lat,
                    'longitude'     => $lng,
                    'is_suspicious' => $isSuspicious, // Menyimpan status kecurigaan
                    'device'        => $deviceInfo, 
                    'user_agent'    => $agent->browser() . ' on ' . $platform,
                    'login_at'      => now(),
                ]);

                // Hapus session agar tidak terbaca di request halaman berikutnya (refresh)
                Session::forget(['user_lat', 'user_lng']);
            }
        }

        return $next($request);
    }
}