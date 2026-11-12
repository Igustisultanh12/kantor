<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Pengaturan rahasia (Google client secret, sertifikat Agora) disimpan terenkripsi
 * dengan awalan "enc:" (audit K02). Nilai lama yang masih polos tetap terbaca
 * dan otomatis terenkripsi saat admin menyimpannya ulang.
 */
class SecretSetting
{
    public const KEYS = ['google_client_secret', 'agora_app_certificate'];
    private const PREFIX = 'enc:';

    public static function get(string $key): ?string
    {
        $raw = Setting::where('key', $key)->value('value');
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }
        if (!str_starts_with($raw, self::PREFIX)) {
            return trim($raw);
        }
        try {
            return Crypt::decryptString(substr($raw, strlen(self::PREFIX)));
        } catch (\Throwable $e) {
            Log::error("[SETTINGS] {$key} tidak dapat didekripsi (APP_KEY berubah?).");
            return null;
        }
    }

    public static function put(string $key, string $plain): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => self::PREFIX . Crypt::encryptString(trim($plain))]);
    }

    public static function isSet(string $key): bool
    {
        return self::get($key) !== null;
    }
}
