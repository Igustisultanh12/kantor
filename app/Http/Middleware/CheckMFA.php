<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckMFA
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // 1. Cek apakah user sudah terautentikasi (login password)
        if ($user) {
            // 2. Jika user mengaktifkan MFA tapi sesi mfa_verified belum ada
            if ($user->mfa_enabled && !session('mfa_verified')) {
                
                // Jangan redirect jika memang sedang berada di halaman verifikasi
                if (!$request->is('mfa*') && !$request->is('logout')) {
                    return redirect()->route('mfa.verify');
                }
            }
        }

        return $next($request);
    }
}