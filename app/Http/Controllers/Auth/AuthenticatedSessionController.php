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
        $rawSettings = \App\Models\Setting::pluck('value', 'key')->toArray();
        $settings = [
            'app_name' => $rawSettings['agency_name'] ?? 'SINDEN',
            'agency_logo' => isset($rawSettings['agency_logo']) && $rawSettings['agency_logo'] ? asset('storage/' . $rawSettings['agency_logo']) : null,
            'login_background' => isset($rawSettings['login_background']) && $rawSettings['login_background'] ? asset('storage/' . $rawSettings['login_background']) : null,
        ];

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'settings' => $settings,
        ]);
    }

    public function store(LoginRequest $request)
    {
        $request->authenticate();

        $user = Auth::user();

        if (!$user->is_active) {
            $userName = $user->name;

            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Akses Ditolak: Akun ({$userName}) belum aktif atau sedang ditangguhkan oleh Admin.",
                ], 403);
            }

            return back()->withErrors([
                'email' => "Akses Ditolak: Akun ({$userName}) belum aktif atau sedang ditangguhkan oleh Admin.",
            ]);
        }

        if ($request->wantsJson() || $request->expectsJson() || $request->is('api/*')) {
            $token = method_exists($user, 'createToken') 
                ? $user->createToken('sinden_mobile_token')->plainTextToken 
                : session()->getId();

            return response()->json([
                'status' => 'success',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email ?? '',
                    'username' => $user->username ?? $user->nrp ?? '',
                    'nrp' => $user->nrp ?? $user->username ?? '',
                    'role' => $user->role ?? 'user',
                    'pangkat' => $user->pangkat ?? 'Prajurit',
                    'korps' => $user->korps ?? '',
                    'jabatan' => $user->jabatan ?? '',
                ],
            ]);
        }

        $request->session()->regenerate();

        if ($request->filled('latitude') && $request->filled('longitude')) {
            session([
                'user_lat' => $request->latitude,
                'user_lng' => $request->longitude
            ]);
        }

        if (empty($user->phone)) {
            session(['warning_wa' => 'Nomor WhatsApp belum terdaftar. Notifikasi sistem tidak akan terkirim.']);
        }

        if ($user->must_change_password) {
            return redirect()->route('profile.edit')->with('info', 'Otoritas Keamanan: Ini adalah login pertama Anda. Mohon perbarui password default Anda segera.');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}