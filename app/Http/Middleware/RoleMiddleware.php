<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Anda tidak memiliki otoritas untuk mengakses halaman ini.');
        }

        // Admin selalu memiliki otoritas penuh di seluruh sistem
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Cek jika role pengguna cocok dengan salah satu role yang dizinkan
        if (empty($roles) || in_array($user->role, $roles)) {
            return $next($request);
        }

        abort(403, 'Anda tidak memiliki otoritas untuk mengakses halaman ini.');
    }
}