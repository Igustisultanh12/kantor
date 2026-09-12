<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MFAController extends Controller
{
    /**
     * Tampilan pendaftaran MFA (Hanya untuk user yang belum aktif MFA-nya)
     */
    public function setup()
    {
        $user = Auth::user();
        $google2fa = app('pragmarx.google2fa');

        // Generate Secret Key jika belum punya
        if (!$user->google2fa_secret) {
            $user->google2fa_secret = $google2fa->generateSecretKey();
            $user->save();
        }

        // Buat URL untuk QR Code
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'SI SINDEN', // Nama Aplikasi di Google Authenticator
            $user->email,
            $user->google2fa_secret
        );

        return Inertia::render('MFA/Setup', [
            'qrCodeUrl' => $qrCodeUrl,
            'secret' => $user->google2fa_secret
        ]);
    }

    /**
     * Aktivasi MFA pertama kali (Validasi kode awal)
     */
    public function activate(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
        ]);

        $user = Auth::user();
        $google2fa = app('pragmarx.google2fa');

        $valid = $google2fa->verifyKey($user->google2fa_secret, $request->code);

        if ($valid) {
            $user->mfa_enabled = true;
            $user->save();
            
            session(['mfa_verified' => true]);

            return redirect()->route('dashboard')->with('message', 'MFA Berhasil diaktifkan!');
        }

        return back()->withErrors(['code' => 'Kode verifikasi tidak valid.']);
    }

    /**
     * Tampilan verifikasi setiap kali login
     */
    public function showVerifyForm()
    {
        // Jika sudah verifikasi, jangan kasih akses ke halaman ini lagi
        if (session('mfa_verified')) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('MFA/Verify');
    }

    /**
     * Eksekusi verifikasi kode OTP saat login
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
        ]);

        $user = Auth::user();
        $google2fa = app('pragmarx.google2fa');

        $valid = $google2fa->verifyKey($user->google2fa_secret, $request->code);

        if ($valid) {
            // Sesi krusial agar middleware CheckMFA meloloskan akses
            session(['mfa_verified' => true]);

            $targetRedirect = session()->pull('url.intended');
            if (!empty($targetRedirect) && is_string($targetRedirect)) {
                if (str_contains($targetRedirect, '%')) {
                    $decoded = rawurldecode($targetRedirect);
                    if (str_starts_with($decoded, 'http') || str_starts_with($decoded, '/')) {
                        $targetRedirect = $decoded;
                    }
                }

                $parsedPath = parse_url($targetRedirect, PHP_URL_PATH);
                if ($parsedPath && !in_array($parsedPath, ['/login', '/logout', '/register', '/password/reset'])) {
                    $appHost = parse_url(config('app.url'), PHP_URL_HOST);
                    $targetHost = parse_url($targetRedirect, PHP_URL_HOST);
                    if (empty($targetHost) || $targetHost === $appHost || $targetHost === $request->getHost()) {
                        return redirect()->to($targetRedirect);
                    }
                }
            }

            return redirect()->intended('/dashboard');
        }

        return back()->withErrors(['code' => 'Kode OTP salah atau kadaluwarsa.']);
    }
}