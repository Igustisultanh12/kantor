<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Menampilkan halaman login SI SINDEN.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Proses autentikasi, pengecekan status aktivasi, GPS, dan kewajiban ganti password.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // 1. Jalankan proses autentikasi standar (Cek email & password)
        $request->authenticate();

        $user = Auth::user();

        /**
         * 2. CEK STATUS AKTIVASI AKUN
         * Memastikan akun sudah diverifikasi oleh Admin.
         */
        if (!$user->is_active) {
            $userName = $user->name;

            // Paksa logout kembali karena akun ditangguhkan/belum aktif
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => "Akses Ditolak: Akun ({$userName}) belum aktif atau sedang ditangguhkan.",
            ]);
        }

        // 3. Jika aktif, perbarui session ID untuk keamanan
        $request->session()->regenerate();

        /**
         * 4. PENGAMANAN DATA GPS
         */
        if ($request->filled('latitude') && $request->filled('longitude')) {
            session([
                'user_lat' => $request->latitude,
                'user_lng' => $request->longitude
            ]);
        }

        /**
         * 5. VALIDASI NOMOR WHATSAPP
         */
        if (empty($user->phone)) {
            session(['warning_wa' => 'Nomor WhatsApp belum terdaftar. Notifikasi sistem tidak akan terkirim.']);
        }

        /**
         * 6. FITUR BARU: FORCE CHANGE PASSWORD (LOGIN PERTAMA)
         * Jika admin membuatkan akun otomatis, user wajib ganti password sebelum masuk dashboard.
         */
        if ($user->must_change_password) {
            return redirect()->route('profile.edit')->with('info', 'Otoritas Keamanan: Ini adalah login pertama Anda. Mohon perbarui password default Anda segera.');
        }

        /**
         * 7. Redirect ke Dashboard
         */
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Menghapus sesi login (Logout).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}