<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Pc;
use App\Models\PcAccessRequest;
use App\Models\User;
use App\Support\BackupAccess;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Alur minta akses ke penyimpanan rekan (dipakai web & aplikasi Android):
 *
 *   1. Peminta menekan "Minta Akses"  -> kode 6 digit dikirim ke WhatsApp peminta.
 *   2. Peminta memasukkan kode        -> permintaan diteruskan ke pemilik (WA + notifikasi aplikasi).
 *   3. Pemilik menyetujui / menolak   -> peminta diberi tahu (WA + notifikasi aplikasi).
 *   4. Pemilik bisa mencabut akses kapan saja; peminta bisa membatalkan permintaannya.
 *
 * Kesalahan yang bisa ditampilkan ke pengguna dilempar sebagai PcAccessException.
 */
class PcAccessService
{
    public const OTP_TTL_MINUTES = 10;
    public const OTP_MAX_ATTEMPTS = 5;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const NOTIFICATION_LINK = '/pc-backup';

    /** Langkah 1: buat (atau lanjutkan) permintaan lalu kirim kode WA ke peminta. */
    public function request(User $requester, Pc $pc, ?string $reason = null): PcAccessRequest
    {
        PcAccessRequest::ensureSchema();

        if (BackupAccess::isOwner($requester, $pc)) {
            throw new PcAccessException('Ini penyimpanan milik Anda sendiri.');
        }
        if (BackupAccess::isAdmin($requester)) {
            throw new PcAccessException('Admin sudah bisa membuka semua penyimpanan.');
        }
        if (empty($this->phoneOf($requester))) {
            throw new PcAccessException('Nomor WhatsApp Anda belum terdaftar di profil. Lengkapi nomor WhatsApp terlebih dahulu atau hubungi Admin.');
        }

        $open = PcAccessRequest::where('pc_id', $pc->id)
            ->where('requester_id', $requester->id)
            ->whereIn('status', PcAccessRequest::OPEN_STATUSES)
            ->latest('id')
            ->first();

        if ($open?->status === PcAccessRequest::STATUS_APPROVED) {
            throw new PcAccessException('Anda sudah punya akses ke penyimpanan ini.');
        }
        if ($open?->status === PcAccessRequest::STATUS_PENDING) {
            throw new PcAccessException('Permintaan Anda sudah dikirim dan sedang menunggu persetujuan pemilik.');
        }

        $req = $open ?? new PcAccessRequest([
            'pc_id' => $pc->id,
            'requester_id' => $requester->id,
            'status' => PcAccessRequest::STATUS_VERIFYING,
        ]);
        $req->reason = $this->cleanText($reason) ?? $req->reason;

        // Masih dalam jeda kirim ulang: pakai kode yang sudah terkirim.
        if ($req->exists && $this->resendWaitSeconds($req) > 0 && $req->otp_expires_at?->isFuture()) {
            $req->save();
            return $req;
        }

        $this->issueOtp($req, $requester, $pc);
        return $req;
    }

    /** Kirim ulang kode verifikasi WA. */
    public function resend(User $requester, PcAccessRequest $req): PcAccessRequest
    {
        $this->assertRequester($requester, $req);
        if ($req->status !== PcAccessRequest::STATUS_VERIFYING) {
            throw new PcAccessException('Permintaan ini tidak lagi menunggu kode verifikasi.');
        }
        $wait = $this->resendWaitSeconds($req);
        if ($wait > 0) {
            throw new PcAccessException("Tunggu {$wait} detik sebelum meminta kode baru.");
        }

        $this->issueOtp($req, $requester, $req->pc);
        return $req;
    }

    /** Langkah 2: cocokkan kode WA, lalu teruskan permintaan ke pemilik. */
    public function verify(User $requester, PcAccessRequest $req, string $code): PcAccessRequest
    {
        $this->assertRequester($requester, $req);
        if ($req->status !== PcAccessRequest::STATUS_VERIFYING) {
            throw new PcAccessException('Permintaan ini sudah diverifikasi sebelumnya.');
        }
        if (!$req->otp_hash || !$req->otp_expires_at || $req->otp_expires_at->isPast()) {
            throw new PcAccessException('Kode verifikasi sudah kedaluwarsa. Minta kode baru.');
        }
        if ($req->otp_attempts >= self::OTP_MAX_ATTEMPTS) {
            throw new PcAccessException('Terlalu banyak percobaan. Minta kode baru.');
        }

        $code = preg_replace('/\D/', '', $code);
        if (!Hash::check($code, $req->otp_hash)) {
            $req->increment('otp_attempts');
            $left = max(0, self::OTP_MAX_ATTEMPTS - $req->otp_attempts);
            throw new PcAccessException($left > 0 ? "Kode salah. Sisa percobaan: {$left}." : 'Kode salah. Minta kode baru.');
        }

        $req->forceFill([
            'status' => PcAccessRequest::STATUS_PENDING,
            'otp_hash' => null,
            'otp_expires_at' => null,
            'verified_at' => now(),
        ])->save();

        $pc = $req->pc;
        $who = $this->displayName($requester);
        $reasonLine = $req->reason ? "\nAlasan: {$req->reason}" : '';
        AppNotification::notify(
            $pc->user_id,
            null,
            'Permintaan Akses Penyimpanan',
            "{$who} meminta akses ke penyimpanan \"{$pc->pc_name}\".{$reasonLine} Buka menu Backup untuk menyetujui atau menolak.",
            'info',
            self::NOTIFICATION_LINK,
            "SINDEN - Permintaan Akses Penyimpanan\n\n{$who} meminta izin melihat isi penyimpanan *{$pc->pc_name}* milik Anda.{$reasonLine}\n\nSilakan setujui atau tolak lewat menu Backup di web SINDEN atau aplikasi SISINDEN."
        );

        return $req;
    }

    /** Langkah 3: pemilik menyetujui atau menolak. */
    public function decide(User $owner, PcAccessRequest $req, bool $approve, ?string $note = null): PcAccessRequest
    {
        $pc = $req->pc;
        if (!BackupAccess::isOwner($owner, $pc)) {
            throw new PcAccessException('Hanya pemilik penyimpanan yang bisa memutuskan permintaan ini.', 403);
        }
        if ($req->status !== PcAccessRequest::STATUS_PENDING) {
            throw new PcAccessException('Permintaan ini sudah tidak menunggu persetujuan.');
        }

        $note = $this->cleanText($note);
        $req->forceFill([
            'status' => $approve ? PcAccessRequest::STATUS_APPROVED : PcAccessRequest::STATUS_REJECTED,
            'decided_by' => $owner->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();

        $ownerName = $this->displayName($owner);
        $noteLine = $note ? "\nCatatan: {$note}" : '';
        if ($approve) {
            AppNotification::notify(
                $req->requester_id,
                null,
                'Akses Penyimpanan Disetujui',
                "{$ownerName} menyetujui permintaan Anda. Sekarang Anda bisa melihat dan mengunduh isi penyimpanan \"{$pc->pc_name}\".{$noteLine}",
                'success',
                self::NOTIFICATION_LINK,
                "SINDEN - Akses Disetujui\n\n{$ownerName} menyetujui permintaan akses Anda ke penyimpanan *{$pc->pc_name}*.{$noteLine}\n\nBuka menu Backup di web SINDEN atau aplikasi SISINDEN untuk melihat isinya."
            );
        } else {
            AppNotification::notify(
                $req->requester_id,
                null,
                'Akses Penyimpanan Ditolak',
                "{$ownerName} belum menyetujui permintaan akses Anda ke penyimpanan \"{$pc->pc_name}\".{$noteLine}",
                'warning',
                self::NOTIFICATION_LINK,
                "SINDEN - Akses Ditolak\n\n{$ownerName} menolak permintaan akses Anda ke penyimpanan *{$pc->pc_name}*.{$noteLine}"
            );
        }

        return $req;
    }

    /**
     * Pemilik mencabut akses yang sudah diberikan / menolak, atau peminta
     * membatalkan permintaannya sendiri.
     */
    public function withdraw(User $user, PcAccessRequest $req): PcAccessRequest
    {
        $pc = $req->pc;

        if ((int) $req->requester_id === (int) $user->id) {
            if (!in_array($req->status, PcAccessRequest::OPEN_STATUSES, true)) {
                throw new PcAccessException('Permintaan ini sudah tidak aktif.');
            }
            $req->forceFill(['status' => PcAccessRequest::STATUS_CANCELLED, 'otp_hash' => null])->save();
            return $req;
        }

        if (!BackupAccess::isOwner($user, $pc)) {
            throw new PcAccessException('Hanya pemilik penyimpanan yang bisa mencabut akses.', 403);
        }
        if ($req->status !== PcAccessRequest::STATUS_APPROVED) {
            throw new PcAccessException('Akses ini sudah tidak aktif.');
        }

        $req->forceFill([
            'status' => PcAccessRequest::STATUS_REVOKED,
            'decided_by' => $user->id,
            'decided_at' => now(),
        ])->save();

        $ownerName = $this->displayName($user);
        AppNotification::notify(
            $req->requester_id,
            null,
            'Akses Penyimpanan Dicabut',
            "{$ownerName} mencabut akses Anda ke penyimpanan \"{$pc->pc_name}\".",
            'warning',
            self::NOTIFICATION_LINK,
            "SINDEN - Akses Dicabut\n\n{$ownerName} mencabut akses Anda ke penyimpanan *{$pc->pc_name}*. Ajukan permintaan baru bila masih membutuhkan."
        );

        return $req;
    }

    /** Ringkasan aman untuk dikirim ke klien (tanpa hash kode). */
    public function payload(PcAccessRequest $req): array
    {
        $req->loadMissing(['pc:id,pc_name,user_id', 'pc.user:id,name,pangkat,nrp', 'requester:id,name,pangkat,nrp,phone']);

        return [
            'id' => $req->id,
            'status' => $req->status,
            'pc_id' => $req->pc_id,
            'pc_name' => $req->pc?->pc_name,
            'owner_name' => $req->pc?->user?->name,
            'requester' => [
                'id' => $req->requester?->id,
                'name' => $req->requester?->name,
                'pangkat' => $req->requester?->pangkat,
                'nrp' => $req->requester?->nrp,
            ],
            'reason' => $req->reason,
            'decision_note' => $req->decision_note,
            'phone_hint' => $req->status === PcAccessRequest::STATUS_VERIFYING ? $this->maskPhone($this->phoneOf($req->requester)) : null,
            'otp_expires_in' => $req->otp_expires_at ? (int) max(0, now()->diffInSeconds($req->otp_expires_at, false)) : 0,
            'resend_in' => $this->resendWaitSeconds($req),
            'created_at' => $req->created_at?->toIso8601String(),
            'created_human' => $req->created_at?->format('d M Y H:i'),
            'decided_human' => $req->decided_at?->format('d M Y H:i'),
        ];
    }

    // ------------------------------------------------------------------ internal

    private function issueOtp(PcAccessRequest $req, User $requester, Pc $pc): void
    {
        $code = (string) random_int(100000, 999999);

        $req->forceFill([
            'status' => PcAccessRequest::STATUS_VERIFYING,
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'otp_sent_at' => now(),
            'otp_attempts' => 0,
        ])->save();

        $owner = $pc->user;
        $ownerLabel = $owner ? " milik {$this->displayName($owner)}" : '';
        $sent = WhatsappService::sendMessage(
            $this->phoneOf($requester),
            "SINDEN - Kode Verifikasi\n\nKode untuk meminta akses ke penyimpanan *{$pc->pc_name}*{$ownerLabel}:\n\n*{$code}*\n\nBerlaku " . self::OTP_TTL_MINUTES . " menit. Jangan berikan kode ini kepada siapa pun.",
            null,
            true
        );

        if (!$sent) {
            Log::warning('[PC_ACCESS] Kode WA gagal dikirim', ['request_id' => $req->id, 'user_id' => $requester->id]);
            // Izinkan kirim ulang segera.
            $req->forceFill(['otp_sent_at' => null])->save();
            throw new PcAccessException('Kode verifikasi gagal dikirim ke WhatsApp Anda. Pastikan nomor WhatsApp di profil benar, lalu coba lagi.', 502);
        }
    }

    private function resendWaitSeconds(PcAccessRequest $req): int
    {
        if (!$req->otp_sent_at) {
            return 0;
        }
        $elapsed = $req->otp_sent_at->diffInSeconds(now(), false);
        return (int) max(0, self::RESEND_COOLDOWN_SECONDS - $elapsed);
    }

    private function assertRequester(User $user, PcAccessRequest $req): void
    {
        if ((int) $req->requester_id !== (int) $user->id) {
            throw new PcAccessException('Permintaan ini bukan milik Anda.', 403);
        }
    }

    private function phoneOf(?User $user): ?string
    {
        $phone = $user?->phone ?: ($user?->whatsapp_number ?? null);
        return $phone ? trim((string) $phone) : null;
    }

    private function maskPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) < 6) {
            return $digits ?: null;
        }
        return substr($digits, 0, 4) . str_repeat('*', max(3, strlen($digits) - 7)) . substr($digits, -3);
    }

    private function displayName(User $user): string
    {
        return trim(($user->pangkat ? $user->pangkat . ' ' : '') . $user->name);
    }

    private function cleanText(?string $text): ?string
    {
        $text = trim(strip_tags((string) $text));
        return $text === '' ? null : mb_substr($text, 0, 500);
    }
}
