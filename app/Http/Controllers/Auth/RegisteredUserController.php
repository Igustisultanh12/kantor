<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // 1. Validasi Data Identitas Militer
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'nrp' => 'required|string|max:50|unique:users', // Mendukung kombinasi huruf & angka
            'pangkat' => 'required|string|max:100',
        ]);

        // 2. Simpan User dengan status is_active = false
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nrp' => strtoupper($request->nrp), // Memastikan NRP tersimpan dalam huruf kapital
            'pangkat' => $request->pangkat,
            'role' => 'user',      // Default sebagai staf/user biasa
            'is_active' => false,  // Akun nonaktif sampai dikonfirmasi Admin
        ]);

        event(new Registered($user));

        /**
         * 3. LOGIN OTOMATIS DIMATIKAN
         * Kita tidak menggunakan Auth::login($user) agar pengguna tetap di halaman login 
         * sampai Admin mengaktifkan akun mereka.
         */

        // Kembali ke halaman login dengan pesan status
        return redirect()->route('login')->with('status', 'Registrasi berhasil. Akun Anda sedang menunggu konfirmasi Admin.');
    }
}