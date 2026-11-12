<?php

namespace App\Http\Middleware;

use App\Services\DeviceSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Mencatat sesi web user yang login (maks. sekali per menit per sesi). Tidak pernah menggagalkan request. */
class TrackDeviceSession
{
    public function __construct(private readonly DeviceSessionService $devices)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        try {
            if ($request->user() && $request->hasSession()) {
                $this->devices->touchWeb($request);
            }
        } catch (\Throwable $e) {
            report($e);
        }
        return $response;
    }
}
