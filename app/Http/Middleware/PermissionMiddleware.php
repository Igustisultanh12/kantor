<?php

namespace App\Http\Middleware;

use App\Support\MobileAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Otorisasi rute web memakai aturan yang sama dengan API mobile (App\Support\MobileAccess).
 * Pemakaian: ->middleware('perm:cash.manage') — semua ability yang disebut wajib dimiliki.
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();
        foreach ($abilities as $ability) {
            if (!$user || !MobileAccess::can($user, $ability)) {
                abort(403, 'Anda tidak memiliki otoritas untuk tindakan ini.');
            }
        }
        return $next($request);
    }
}
