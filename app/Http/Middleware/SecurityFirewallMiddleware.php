<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SecurityFirewallMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // =========================================================================
        // 1. MITIGASI HTTP REQUEST SMUGGLING (TE.CL, CL.TE, TE.TE & HEADER ANOMALIES)
        // =========================================================================
        $contentLength = $request->server('HTTP_CONTENT_LENGTH') ?? $request->header('Content-Length');
        $transferEncoding = $request->server('HTTP_TRANSFER_ENCODING') ?? $request->header('Transfer-Encoding');

        // Serangan TE.CL atau CL.TE: Header Content-Length dan Transfer-Encoding dikirim bersamaan
        if ($contentLength !== null && $transferEncoding !== null) {
            Log::critical('SECURITY ALERT [HTTP Request Smuggling Attempt]: Permintaan memuat Content-Length dan Transfer-Encoding bersamaan dari IP: ' . $request->ip());
            return response()->json([
                'status' => 'error',
                'message' => 'Protokol Ditolak: Anomali integritas header HTTP (Ambiguous Length & Transfer Encoding).'
            ], 400);
        }

        // Serangan TE.TE / Obfuscation: Transfer-Encoding dikirim tidak wajar
        if ($transferEncoding !== null) {
            $teNormalized = strtolower(trim($transferEncoding));
            // Hanya izinkan 'chunked' standar. Tolak variasi seperti 'chunked, chunked', 'identity, chunked', spasi tersembunyi
            if ($teNormalized !== 'chunked') {
                Log::warning('SECURITY ALERT [Transfer-Encoding Anomaly]: ' . $transferEncoding . ' dari IP: ' . $request->ip());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Protokol Ditolak: Nilai Transfer-Encoding tidak didukung.'
                ], 501);
            }
        }

        // Inspeksi injeksi karakter kontrol (CRLF Injection / Null Bytes) pada header HTTP
        $rawHeaders = $request->headers->all();
        foreach ($rawHeaders as $name => $values) {
            if (str_contains($name, "\0") || str_contains($name, "\r") || str_contains($name, "\n")) {
                Log::critical('SECURITY ALERT [Header Injection]: Karakter kontrol terdeteksi pada nama header dari IP: ' . $request->ip());
                return response()->json(['status' => 'error', 'message' => 'Header HTTP tidak valid.'], 400);
            }
            foreach ($values as $val) {
                if (str_contains($val, "\0") || str_contains($val, "\r\n") || str_contains($val, "\n\r")) {
                    Log::critical('SECURITY ALERT [Header Injection]: CRLF/Null byte pada nilai header dari IP: ' . $request->ip());
                    return response()->json(['status' => 'error', 'message' => 'Header HTTP tidak valid.'], 400);
                }
            }
        }

        // =========================================================================
        // 2. MITIGASI DATA DENIAL OF SERVICE (INFINITE STREAM / PAYLOAD BOMB)
        // =========================================================================
        // Batas maksimum payload HTTP global: 20 GB (sesuai spesifikasi pangkalan backup)
        $maxGlobalPayload = 20 * 1024 * 1024 * 1024; // 20 GB
        if ($contentLength !== null) {
            $intLength = (int)$contentLength;
            if ($intLength > $maxGlobalPayload) {
                Log::warning('SECURITY ALERT [Payload Too Large]: Ukuran payload ' . $intLength . ' melebihi batas dari IP: ' . $request->ip());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ukuran payload melampaui batas maksimum yang diizinkan.'
                ], 413);
            }
        }

        // Eksekusi request selanjutnya
        $response = $next($request);

        // =========================================================================
        // 3. INJEKSI HEADER PERTAHANAN STANDAR MILITER (ANTI-MIME SNIFFING & ISOLASI)
        // =========================================================================
        // Mencegah browser melakukan eksekusi file teks/gambar sebagai javascript/html
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Mencegah clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        
        // Perlindungan XSS bawaan browser
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Isolasi referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
