<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\SpJaga;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi in-app (lonceng web + notifikasi HP) untuk alur SP Jaga.
 * Aplikasi Android membaca tabel app_notifications dan menampilkan
 * notifikasi bersuara di bilah notifikasi perangkat.
 */
class SpJagaNotifier
{
    private const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    private static function periode(SpJaga $sp): string
    {
        return (self::BULAN[(int) $sp->bulan] ?? $sp->bulan) . ' ' . $sp->tahun;
    }

    /** Pengajuan SP Jaga dengan TTE → Pasops diminta menandatangani. */
    public static function pendingSignature(SpJaga $sp, ?User $pengaju = null): void
    {
        try {
            $hasPasopsRole = User::where('role', 'pasops')->exists();
            $fallback = $hasPasopsRole ? null : User::where('name', 'like', '%Roni Sumantri%')->first();
            AppNotification::notify(
                $fallback?->id,
                $hasPasopsRole ? 'pasops' : null,
                $sp->revisi_ke ? 'Pengajuan TTE Revisi SP Jaga' : 'Pengajuan TTE SP Jaga',
                'SP Jaga ' . self::periode($sp) . ' (' . ($sp->nomor_sprin ?: 'tanpa nomor') . ($sp->revisi_ke ? ', revisi ke-' . $sp->revisi_ke : '') . ') menunggu tanda tangan elektronik Pasops'
                    . ($pengaju ? ' — diajukan oleh ' . $pengaju->name : '') . '.',
                'warning',
                '/sp-jaga'
            );
        } catch (\Throwable $e) {
            Log::warning('[SP_JAGA_NOTIF] Gagal membuat notifikasi Pasops: ' . $e->getMessage());
        }
    }

    /** Revisi disahkan otomatis atas nama Pasops (lolos OTP pengaju) → Pasops diberi tahu TTE-nya dipakai. */
    public static function revisiDisahkan(SpJaga $sp, User $pengaju): void
    {
        try {
            $hasPasopsRole = User::where('role', 'pasops')->exists();
            $fallback = $hasPasopsRole ? null : User::where('name', 'like', '%Roni Sumantri%')->first();
            AppNotification::notify(
                $fallback?->id,
                $hasPasopsRole ? 'pasops' : null,
                'TTE Revisi SP Jaga',
                'Revisi ke-' . $sp->revisi_ke . ' SP Jaga ' . self::periode($sp) . ' (' . $sp->nomor_sprin . ') disahkan dengan TTE Pasops oleh '
                    . $pengaju->name . ' setelah verifikasi OTP WhatsApp. Alasan: ' . ($sp->revisi_alasan ?: '-') . '.',
                'info',
                '/sp-jaga'
            );
        } catch (\Throwable $e) {
            Log::warning('[SP_JAGA_NOTIF] Gagal memberi tahu Pasops soal revisi: ' . $e->getMessage());
        }
    }

    /** SP Jaga disahkan → seluruh personel diberi tahu (pengingat H-1 di HP ikut terjadwal). */
    public static function published(SpJaga $sp): void
    {
        try {
            AppNotification::notify(
                null,
                null,
                'Jadwal jaga terbit',
                'SP Jaga ' . self::periode($sp) . ($sp->revisi_ke ? ' (revisi ke-' . $sp->revisi_ke . ')' : '') . ' telah disahkan. Cek tanggal jaga Anda di menu Daftar Jaga.',
                'success',
                '/sp-jaga',
                null,
                null,
                true // siaran ke seluruh personel (disengaja)
            );
        } catch (\Throwable $e) {
            Log::warning('[SP_JAGA_NOTIF] Gagal membuat notifikasi terbit: ' . $e->getMessage());
        }
    }
}
