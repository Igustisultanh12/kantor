<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'title',
        'message',
        'type',
        'link',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Notifikasi push ke HP (seperti WhatsApp) untuk setiap warta baru.
        static::created(fn (self $n) => \App\Support\PushNotifier::appNotification($n));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper Terpusat untuk Membuat In-App Bell Notification & Sekaligus Mengirim WA
     */
    public static function notify($targetUserId, $targetRole, $title, $message, $type = 'info', $link = null, $waMessage = null, $waTargetPhone = null, bool $siaran = false)
    {
        // Tanpa penerima (user & role kosong) notifikasi tampil ke SEMUA personel. Itu hanya boleh bila memang
        // siaran ($siaran = true). Notifikasi pribadi yang penerimanya hilang (mis. akun pengaju sudah dihapus,
        // tidak ada akun Pasops) dialihkan ke Admin agar tidak bocor ke personel lain.
        if (empty($targetUserId) && empty($targetRole) && !$siaran) {
            \Illuminate\Support\Facades\Log::warning("[NOTIF] Penerima kosong untuk \"{$title}\"; dialihkan ke Admin agar tidak menjadi siaran.");
            $targetRole = 'admin';
        }

        // 1. Simpan ke database In-App Bell Notification
        $notif = self::create([
            'user_id' => $targetUserId,
            'role'    => $targetRole,
            'title'   => $title,
            'message' => $message,
            'type'    => $type,
            'link'    => $link,
            'is_read' => false,
        ]);

        // 2. Kirim Notifikasi WhatsApp jika waMessage ada
        if (!empty($waMessage)) {
            $phone = $waTargetPhone;
            if (!$phone && $targetUserId) {
                $user = User::find($targetUserId);
                $phone = $user?->phone;
            }
            if (!$phone && $targetRole) {
                $userRole = User::where('role', $targetRole)->whereNotNull('phone')->first();
                $phone = $userRole?->phone;
            }

            if ($phone) {
                \App\Services\WhatsappService::sendMessage($phone, $waMessage);
            }
        }

        return $notif;
    }
}
