<?php

namespace App\Support;

use App\Models\User;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Log;

/**
 * Kirim pemberitahuan WhatsApp ke personel setiap kali verifikasi 2 langkah (OTP WA)
 * di akunnya diaktifkan / dimatikan, oleh dirinya sendiri maupun oleh Admin.
 * Dikirim sebagai pesan wajib (tetap terkirim walau notifikasi WA umum dimatikan).
 */
class MfaNotifier
{
    public static function changed(User $user, bool $enabled, ?User $by = null, ?string $ip = null): void
    {
        if (blank($user->phone)) {
            return;
        }
        $oleh = ($by === null || (int) $by->id === (int) $user->id)
            ? 'Anda sendiri (menu Profil)'
            : trim(($by->pangkat ? $by->pangkat . ' ' : '') . $by->name) . ' (Admin)';
        $waktu = now()->format('d/m/Y H:i') . ' WIB';
        $status = $enabled ? '*DIAKTIFKAN*' : '*DINONAKTIFKAN*';

        $pesan = "*OTORITAS KEAMANAN SINDEN*\n"
            . "Verifikasi 2 Langkah (OTP WhatsApp) akun Anda telah {$status}.\n\n"
            . "Yth. " . trim(($user->pangkat ? $user->pangkat . ' ' : '') . $user->name) . "\n"
            . "- Diubah oleh: {$oleh}\n"
            . "- Waktu: {$waktu}\n"
            . ($ip ? "- Alamat IP: {$ip}\n" : '')
            . "\n"
            . ($enabled
                ? 'Mulai sekarang setiap kali masuk (web & aplikasi) Anda akan diminta kode OTP yang dikirim ke nomor ini.'
                : 'Akun Anda kini hanya dilindungi kata sandi. Disarankan mengaktifkan kembali OTP di menu Profil.')
            . "\n\nJika perubahan ini BUKAN oleh Anda, segera ganti kata sandi dan hubungi Admin SINDEN.";

        try {
            WhatsappService::sendMessage($user->phone, $pesan, null, true);
        } catch (\Throwable $e) {
            Log::warning('[MFA] Gagal kirim notifikasi WA perubahan OTP: ' . $e->getMessage());
        }
    }
}
