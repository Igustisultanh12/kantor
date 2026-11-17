<?php

namespace App\Support;

use App\Models\SpJaga;
use App\Models\User;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Hash;

/**
 * OTP WhatsApp sebelum merevisi SP Jaga yang sudah ditandatangani.
 * Kode disimpan sebagai hash di sesi pengguna (per SP), berlaku 5 menit, maksimal 5 kali salah.
 * Setelah lolos, pengguna punya waktu 30 menit untuk mengisi & menyimpan revisi.
 */
class SpJagaRevisiOtp
{
    public const BERLAKU_DETIK = 300;
    public const JEDA_KIRIM_DETIK = 60;
    public const MAKS_SALAH = 5;
    public const IZIN_DETIK = 1800;

    private static function kunciOtp(SpJaga $sp): string
    {
        return 'spjaga_revisi_otp.' . $sp->id;
    }

    private static function kunciIzin(SpJaga $sp): string
    {
        return 'spjaga_revisi_izin.' . $sp->id;
    }

    public static function samarkan(?string $phone): string
    {
        $p = (string) $phone;
        return strlen($p) > 6 ? substr($p, 0, 4) . '****' . substr($p, -3) : $p;
    }

    /**
     * Kirim kode ke WhatsApp pengguna.
     * @return array{ok: bool, pesan: string, tunggu?: int}
     */
    public static function kirim(User $user, SpJaga $sp): array
    {
        if (empty($user->phone)) {
            return ['ok' => false, 'pesan' => 'Nomor WhatsApp Anda belum terdaftar di profil. Lengkapi dulu di menu Profil.'];
        }

        $lama = session(self::kunciOtp($sp));
        if ($lama && now()->timestamp - ($lama['dikirim'] ?? 0) < self::JEDA_KIRIM_DETIK) {
            $tunggu = self::JEDA_KIRIM_DETIK - (now()->timestamp - $lama['dikirim']);
            return ['ok' => false, 'pesan' => "Tunggu {$tunggu} detik sebelum meminta kode baru.", 'tunggu' => $tunggu];
        }

        $kode = (string) random_int(100000, 999999);
        $pesan = "*SI SINDEN: KODE VERIFIKASI REVISI SP JAGA*\n\n" .
                 "Kode OTP Anda: *{$kode}*\n\n" .
                 "Kode ini untuk merevisi {$sp->nomor_sprin} dan berlaku 5 menit. " .
                 "Jangan berikan kode ini kepada siapa pun. Abaikan pesan ini bila Anda tidak sedang merevisi SP Jaga.";

        if (!WhatsappService::sendMessage($user->phone, $pesan, null, true)) {
            return ['ok' => false, 'pesan' => 'Kode OTP gagal dikirim ke WhatsApp. Coba lagi beberapa saat lagi.'];
        }

        session([self::kunciOtp($sp) => [
            'user_id' => $user->id,
            'hash' => Hash::make($kode),
            'kedaluwarsa' => now()->timestamp + self::BERLAKU_DETIK,
            'dikirim' => now()->timestamp,
            'salah' => 0,
        ]]);

        return ['ok' => true, 'pesan' => 'Kode OTP dikirim ke WhatsApp ' . self::samarkan($user->phone) . '.'];
    }

    /** @return array{ok: bool, pesan: string} */
    public static function verifikasi(User $user, SpJaga $sp, string $kode): array
    {
        $data = session(self::kunciOtp($sp));
        if (!$data || (int) ($data['user_id'] ?? 0) !== (int) $user->id || now()->timestamp > ($data['kedaluwarsa'] ?? 0)) {
            session()->forget(self::kunciOtp($sp));
            return ['ok' => false, 'pesan' => 'Kode OTP tidak berlaku atau sudah kedaluwarsa. Minta kode baru.'];
        }

        if (!Hash::check(trim($kode), $data['hash'])) {
            $data['salah'] = ($data['salah'] ?? 0) + 1;
            if ($data['salah'] >= self::MAKS_SALAH) {
                session()->forget(self::kunciOtp($sp));
                return ['ok' => false, 'pesan' => 'Terlalu banyak kode salah. Minta kode baru.'];
            }
            session([self::kunciOtp($sp) => $data]);
            return ['ok' => false, 'pesan' => 'Kode OTP salah. Sisa percobaan: ' . (self::MAKS_SALAH - $data['salah']) . '.'];
        }

        session()->forget(self::kunciOtp($sp));
        session([self::kunciIzin($sp) => ['user_id' => $user->id, 'sampai' => now()->timestamp + self::IZIN_DETIK]]);

        return ['ok' => true, 'pesan' => 'Verifikasi berhasil.'];
    }

    /** Sudah lolos OTP dan izinnya belum habis? */
    public static function lolos(User $user, SpJaga $sp): bool
    {
        $izin = session(self::kunciIzin($sp));
        return $izin && (int) ($izin['user_id'] ?? 0) === (int) $user->id && now()->timestamp <= ($izin['sampai'] ?? 0);
    }

    /** Izin hanya untuk satu kali simpan revisi. */
    public static function habiskan(SpJaga $sp): void
    {
        session()->forget(self::kunciIzin($sp));
    }
}
