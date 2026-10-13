<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifikasi Cloudflare Turnstile untuk login & aktivasi aplikasi mobile.
 *
 * Kunci dibaca dari .env (JANGAN disimpan di repository):
 *   TURNSTILE_SITE_KEY   — kunci publik widget
 *   TURNSTILE_SECRET_KEY — kunci rahasia untuk siteverify (server saja)
 *
 * Bila TURNSTILE_SECRET_KEY belum diisi, verifikasi dilewati agar deploy
 * bertahap tidak memutus login; begitu diisi, token wajib dan divalidasi.
 */
class Turnstile
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function siteKey(): ?string
    {
        $key = config('services.turnstile.site_key');
        return filled($key) ? (string) $key : null;
    }

    public static function enabled(): bool
    {
        return filled(config('services.turnstile.secret_key'));
    }

    /**
     * @return array{ok: bool, message: string|null}
     */
    public static function verify(?string $token, ?string $ip, string $expectedAction): array
    {
        if (!self::enabled()) {
            return ['ok' => true, 'message' => null];
        }

        $token = trim((string) $token);
        if ($token === '' || strlen($token) > 2048) {
            return ['ok' => false, 'message' => 'Verifikasi keamanan diperlukan. Silakan ulangi pemeriksaan keamanan.'];
        }

        try {
            $res = Http::asForm()->timeout(8)->post(self::VERIFY_URL, array_filter([
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]));
            $body = $res->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('[TURNSTILE] Gagal menghubungi siteverify: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Layanan verifikasi keamanan tidak dapat dihubungi. Coba lagi.'];
        }

        if (($body['success'] ?? false) !== true) {
            Log::warning('[TURNSTILE] Token ditolak', ['codes' => $body['error-codes'] ?? [], 'ip' => $ip]);
            return ['ok' => false, 'message' => 'Verifikasi keamanan gagal atau kedaluwarsa. Silakan ulangi.'];
        }

        $action = $body['action'] ?? null;
        if ($action !== null && $action !== '' && $action !== $expectedAction) {
            Log::warning('[TURNSTILE] Action tidak cocok', ['expected' => $expectedAction, 'got' => $action]);
            return ['ok' => false, 'message' => 'Verifikasi keamanan tidak valid untuk permintaan ini.'];
        }

        return ['ok' => true, 'message' => null];
    }
}
