<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // Cek apakah user sudah login dan memiliki role yang sesuai
        if (!$request->user() || $request->user()->role !== $role) {
            abort(403, 'Anda tidak memiliki otoritas untuk mengakses halaman ini.');
        }

        return $next($request);
    }
}