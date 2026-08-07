<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Ambil Notifikasi Pengguna Real-Time (JSON untuk Topbar Bell)
     */
    public function getNotifications(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['notifications' => [], 'unreadCount' => 0]);
        }

        $query = AppNotification::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('role', $user->role)
                  ->orWhereNull('role');
            })
            ->latest()
            ->limit(20);

        $notifications = $query->get();

        $unreadCount = AppNotification::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('role', $user->role)
                  ->orWhereNull('role');
            })
            ->where('is_read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    /**
     * Tandai Notifikasi Tertentu Telah Dibaca
     */
    public function markAsRead($id)
    {
        $notif = AppNotification::find($id);
        if ($notif) {
            $notif->update(['is_read' => true]);
        }

        return back()->with('success', 'Notifikasi ditandai dibaca.');
    }

    /**
     * Tandai Semua Notifikasi Telah Dibaca
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        if ($user) {
            AppNotification::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('role', $user->role)
                  ->orWhereNull('role');
            })->update(['is_read' => true]);
        }

        return back()->with('success', 'Semua notifikasi ditandai dibaca.');
    }
}
