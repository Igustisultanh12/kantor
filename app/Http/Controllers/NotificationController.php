<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Cakupan notifikasi seorang pengguna (audit T08): notifikasi pribadinya, ATAU
     * siaran (user_id kosong) untuk role-nya / untuk semua. Dulu `orWhereNull('role')`
     * ikut menampilkan notifikasi pribadi orang lain.
     */
    public static function scopeFor(User $user)
    {
        return AppNotification::query()->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere(function ($q2) use ($user) {
                  $q2->whereNull('user_id')->where(function ($q3) use ($user) {
                      $q3->where('role', $user->role)->orWhereNull('role');
                  });
              });
        });
    }

    /**
     * Ambil Notifikasi Pengguna Real-Time (JSON untuk Topbar Bell)
     */
    public function getNotifications(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['notifications' => [], 'unreadCount' => 0]);
        }

        return response()->json([
            'notifications' => self::scopeFor($user)->latest()->limit(20)->get(),
            'unreadCount' => self::scopeFor($user)->where('is_read', false)->count(),
        ]);
    }

    /**
     * Tandai Notifikasi Tertentu Telah Dibaca (hanya yang ada dalam cakupan pengguna).
     */
    public function markAsRead(Request $request, $id)
    {
        $notif = self::scopeFor($request->user())->find($id);
        if ($notif) {
            $notif->update(['is_read' => true]);
        }

        return back()->with('success', 'Notifikasi ditandai dibaca.');
    }

    /**
     * Halaman Utama Lihat Semua Notifikasi
     */
    public function index(Request $request)
    {
        return \Inertia\Inertia::render('Notifications/Index', [
            'notifications' => self::scopeFor($request->user())->latest()->paginate(50),
        ]);
    }

    /**
     * Tandai Semua Notifikasi Telah Dibaca
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        if ($user) {
            self::scopeFor($user)->update(['is_read' => true]);
        }

        return back()->with('success', 'Semua notifikasi ditandai dibaca.');
    }

    /**
     * Hapus Notifikasi: pribadi milik sendiri; siaran hanya oleh Admin.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $notif = self::scopeFor($user)->find($id);
        if ($notif && ((int) $notif->user_id === (int) $user->id || \App\Support\MobileAccess::isSuperAdmin($user))) {
            $notif->delete();
        }

        return back()->with('success', 'Notifikasi berhasil dihapus.');
    }
}
