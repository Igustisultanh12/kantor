<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Pengaman lapis kedua MFA (audit K04): sesi web milik akun ber-MFA yang belum
 * menyelesaikan OTP (mis. dipulihkan dari cookie "Ingat saya" atau jalur login lain)
 * dikeluarkan dan diminta login ulang melalui alur OTP.
 */
class CheckMFA
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        if ($user && $user->mfa_enabled && !$request->session()->get('mfa_verified')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Verifikasi 2 langkah diperlukan. Silakan login kembali.'], 401);
            }
            return redirect()->route('login')->withErrors(['email' => 'Verifikasi 2 langkah diperlukan. Silakan login kembali.']);
        }

        return $next($request);
    }
}
