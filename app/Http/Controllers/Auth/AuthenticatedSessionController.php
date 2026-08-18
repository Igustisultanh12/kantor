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
    public function create(): Response
    {
         = \App\Models\Setting::pluck('value', 'key')->toArray();
         = [
            'app_name' => ['agency_name'] ?? 'SINDEN',
            'agency_logo' => isset(['agency_logo']) && ['agency_logo'] ? asset('storage/' . ['agency_logo']) : null,
            'login_background' => isset(['login_background']) && ['login_background'] ? asset('storage/' . ['login_background']) : null,
        ];

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'settings' => ,
        ]);
    }

    public function store(LoginRequest )
    {
        ->authenticate();

         = Auth::user();

        // Check if user is active
        if (!->is_active) {
             = ->name;

            Auth::guard('web')->logout();
            if (->hasSession()) {
                ->session()->invalidate();
                ->session()->regenerateToken();
            }

            if (->wantsJson() || ->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Akses Ditolak: Akun ({}) belum aktif atau sedang ditangguhkan oleh Admin.",
                ], 403);
            }

            return back()->withErrors([
                'email' => "Akses Ditolak: Akun ({}) belum aktif atau sedang ditangguhkan oleh Admin.",
            ]);
        }

        // Return JSON response if requested by Mobile App
        if (->wantsJson() || ->expectsJson() || ->is('api/*')) {
             = method_exists(, 'createToken') 
                ? ->createToken('sinden_mobile_token')->plainTextToken 
                : session()->getId();

            return response()->json([
                'status' => 'success',
                'token' => ,
                'user' => [
                    'id' => ->id,
                    'name' => ->name,
                    'email' => ->email ?? '',
                    'username' => ->username ?? ->nrp ?? '',
                    'nrp' => ->nrp ?? ->username ?? '',
                    'role' => ->role ?? 'user',
                    'pangkat' => ->pangkat ?? 'Prajurit',
                    'korps' => ->korps ?? '',
                    'jabatan' => ->jabatan ?? '',
                ],
            ]);
        }

        // Standard Web Inertia session response
        ->session()->regenerate();

        if (->filled('latitude') && ->filled('longitude')) {
            session([
                'user_lat' => ->latitude,
                'user_lng' => ->longitude
            ]);
        }

        if (empty(->phone)) {
            session(['warning_wa' => 'Nomor WhatsApp belum terdaftar. Notifikasi sistem tidak akan terkirim.']);
        }

        if (->must_change_password) {
            return redirect()->route('profile.edit')->with('info', 'Otoritas Keamanan: Ini adalah login pertama Anda. Mohon perbarui password default Anda segera.');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request ): RedirectResponse
    {
        Auth::guard('web')->logout();

        ->session()->invalidate();
        ->session()->regenerateToken();

        return redirect('/');
    }
}