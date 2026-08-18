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
        $this->ensureIsNotRateLimited();

        $loginInput = $this->input('username') ?? $this->input('email');

        if (empty($loginInput)) {
            throw ValidationException::withMessages([
                'email' => 'Silakan masukkan NRP, Username, atau Alamat Email Anda.',
            ]);
        }

        $password = $this->input('password');
        $remember = $this->boolean('remember');

        if (filter_var($loginInput, FILTER_VALIDATE_EMAIL)) {
            if (Auth::attempt(['email' => $loginInput, 'password' => $password], $remember)) {
                RateLimiter::clear($this->throttleKey());
                return;
            }
        }

        if (Auth::attempt(['username' => $loginInput, 'password' => $password], $remember)) {
            RateLimiter::clear($this->throttleKey());
            return;
        }

        if (Auth::attempt(['nrp' => $loginInput, 'password' => $password], $remember)) {
            RateLimiter::clear($this->throttleKey());
            return;
        }

        if (Auth::attempt(['email' => $loginInput, 'password' => $password], $remember)) {
            RateLimiter::clear($this->throttleKey());
            return;
        }

        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => 'Kredensial tidak cocok. Silakan periksa NRP/Username dan Kata Sandi Anda.',
        ]);
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        $loginInput = $this->input('username') ?? $this->input('email') ?? '';
        return Str::transliterate(Str::lower($loginInput).'|'.$this->ip());
    }
}