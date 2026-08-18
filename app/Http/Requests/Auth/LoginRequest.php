<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string'],
            'username' => ['nullable', 'string'],
            'password' => ['required', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ];
    }

    public function authenticate(): void
    {
        ->ensureIsNotRateLimited();

         = ->input('username') ?? ->input('email');

        if (empty()) {
            throw ValidationException::withMessages([
                'email' => 'Silakan masukkan NRP, Username, atau Alamat Email Anda.',
            ]);
        }

         = ->input('password');
         = ->boolean('remember');

        // Check 1: Try email field
        if (filter_var(, FILTER_VALIDATE_EMAIL)) {
            if (Auth::attempt(['email' => , 'password' => ], )) {
                RateLimiter::clear(->throttleKey());
                return;
            }
        }

        // Check 2: Try username field
        if (Auth::attempt(['username' => , 'password' => ], )) {
            RateLimiter::clear(->throttleKey());
            return;
        }

        // Check 3: Try nrp field
        if (Auth::attempt(['nrp' => , 'password' => ], )) {
            RateLimiter::clear(->throttleKey());
            return;
        }

        // Check 4: Try email field directly if input wasn't valid email format
        if (Auth::attempt(['email' => , 'password' => ], )) {
            RateLimiter::clear(->throttleKey());
            return;
        }

        RateLimiter::hit(->throttleKey());

        throw ValidationException::withMessages([
            'email' => 'Kredensial tidak cocok. Silakan periksa NRP/Username dan Kata Sandi Anda.',
        ]);
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts(->throttleKey(), 5)) {
            return;
        }

        event(new Lockout());

         = RateLimiter::availableIn(->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => ,
                'minutes' => ceil( / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
         = ->input('username') ?? ->input('email') ?? '';
        return Str::transliterate(Str::lower().'|'.->ip());
    }
}