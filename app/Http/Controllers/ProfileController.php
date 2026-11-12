<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Menampilkan formulir profil pengguna.
     */
    public function edit(Request $request): Response
    {
        $rawSettings = \App\Models\Setting::pluck('value', 'key')->toArray();
        $clientId = !empty($rawSettings['google_client_id']) ? $rawSettings['google_client_id'] : config('services.google.client_id');
        $googleLoginEnabled = isset($rawSettings['google_login_enabled']) ? ($rawSettings['google_login_enabled'] === '1' || $rawSettings['google_login_enabled'] === 1) : true;

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'googleLoginEnabled' => $googleLoginEnabled && !empty($clientId),
        ]);
    }

    /**
     * Memperbarui informasi profil pengguna (Termasuk Avatar, Pangkat, NRP, & WhatsApp).
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Nama & NRP adalah identitas dinas: hanya Admin yang boleh mengubahnya (audit K01).
        if (!\App\Support\MobileAccess::isSuperAdmin($request->user())) {
            unset($data['name'], $data['nrp']);
        }
        if (array_key_exists('name', $data) && blank($data['name'])) {
            unset($data['name']);
        }

        // Penanganan berkas foto profil / avatar personel
        if ($request->hasFile('avatar')) {
            if ($request->user()->avatar && Storage::disk('public')->exists($request->user()->avatar)) {
                Storage::disk('public')->delete($request->user()->avatar);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        } elseif ($request->boolean('remove_avatar')) {
            if ($request->user()->avatar && Storage::disk('public')->exists($request->user()->avatar)) {
                Storage::disk('public')->delete($request->user()->avatar);
            }
            $data['avatar'] = null;
        } else {
            unset($data['avatar']);
        }

        $request->user()->fill($data);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Aktifkan / nonaktifkan verifikasi 2 langkah (OTP WhatsApp) milik sendiri.
     * Wajib kata sandi saat ini; mengaktifkan butuh nomor WhatsApp agar akun tidak terkunci.
     */
    public function updateMfa(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'password' => ['required', 'current_password'],
        ], [
            'password.current_password' => 'Kata sandi tidak sesuai.',
        ]);

        $user = $request->user();
        $enable = (bool) $data['enabled'];

        if ($enable && blank($user->phone)) {
            return back()->withErrors(['mfa' => 'Isi nomor WhatsApp di profil terlebih dahulu sebelum mengaktifkan OTP.']);
        }

        $user->forceFill(['mfa_enabled' => $enable])->save();
        if ($enable) {
            // Sesi ini baru saja dikonfirmasi dengan kata sandi: jangan dikeluarkan oleh CheckMFA.
            $request->session()->put('mfa_verified', true);
        }

        \App\Models\AuditLog::create([
            'user_id' => $user->id,
            'admin_name' => $user->name,
            'action' => $enable ? 'MFA_DIAKTIFKAN' : 'MFA_DINONAKTIFKAN',
            'target_personnel' => $user->name,
            'description' => ($enable ? 'Mengaktifkan' : 'Menonaktifkan') . ' verifikasi 2 langkah (OTP WhatsApp) pada akun sendiri',
            'ip_address' => $request->ip(),
        ]);

        \App\Support\MfaNotifier::changed($user, $enable, $user, $request->ip());

        return back()->with('status', $enable ? 'mfa-enabled' : 'mfa-disabled');
    }

    /**
     * Menghapus akun pengguna.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}