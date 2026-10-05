<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WaSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WhatsAppController extends Controller
{
    private string $gatewayUrl = 'http://127.0.0.1:3000';

    /**
     * Tampilan Utama WhatsApp Web Multi-Akun
     */
    public function index()
    {
        $user = Auth::user();
        $isAdmin = $user->role === 'admin' || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';
        $isCommander = $isAdmin || $user->role === 'komandan';

        // 1. Cek status keaktifan server gateway Node.js
        $gatewayOnline = false;
        $remoteSessions = collect();
        $gwData = [];
        try {
            $gwResponse = Http::timeout(3)->get("{$this->gatewayUrl}/status");
            if ($gwResponse->successful()) {
                $gatewayOnline = true;
                $gwData = $gwResponse->json();
                $remoteSessions = collect($gwData['sessions'] ?? []);
            }
        } catch (\Exception $e) {
            $gatewayOnline = false;
        }

        $tableExists = Schema::hasTable('wa_sessions');

        if ($tableExists) {
            if ($remoteSessions->isNotEmpty()) {
                // Gateway versi multi-session aktif
                foreach ($remoteSessions as $remote) {
                    WaSession::updateOrCreate(
                        ['session_id' => $remote['sessionId']],
                        [
                            'label' => $remote['label'] ?? 'WA Dinas Denintel',
                            'status' => strtolower($remote['status'] ?? 'disconnected'),
                            'phone_number' => $remote['phone'] ?? null,
                            'last_active_at' => now(),
                        ]
                    );
                }
            } elseif (!empty($gwData['status']) && in_array($gwData['status'], ['ONLINE', 'WAITING_SCAN'])) {
                // Gateway versi tunggal aktif terhubung
                WaSession::updateOrCreate(
                    ['session_id' => 'dinas'],
                    [
                        'label' => 'WA Dinas Denintel',
                        'status' => $gwData['status'] === 'ONLINE' ? 'connected' : 'connecting',
                        'role_access' => 'all',
                        'last_active_at' => now(),
                    ]
                );
            }

            // Pastikan minimal selalu ada sesi 'dinas' terdaftar di sistem
            if (WaSession::where('session_id', 'dinas')->doesntExist()) {
                WaSession::create([
                    'session_id' => 'dinas',
                    'label' => 'WA Dinas Denintel',
                    'status' => $gatewayOnline ? 'connected' : 'disconnected',
                    'role_access' => 'all',
                ]);
            }

            // Filter sesi berdasarkan hak akses pengguna
            $query = WaSession::query();
            if (!$isAdmin && !$isCommander) {
                $query->where('role_access', 'all');
            } elseif (!$isAdmin && $isCommander) {
                $query->whereIn('role_access', ['all', 'komandan']);
            }
            $sessions = $query->orderBy('id', 'asc')->get();
        } else {
            // Jika migrasi belum sempat dijalankan di server VPS
            $sessions = collect([
                (object) [
                    'id' => 1,
                    'session_id' => 'dinas',
                    'label' => 'WA Dinas Denintel',
                    'status' => $gatewayOnline ? 'connected' : 'disconnected',
                    'phone_number' => null,
                    'role_access' => 'all',
                ]
            ]);
        }

        // Ambil daftar personel untuk memulai obrolan cepat
        $personnel = User::select('id', 'name', 'nrp', 'pangkat', 'phone', 'role', 'avatar')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('name', 'asc')
            ->get();

        return Inertia::render('WhatsApp/Index', [
            'sessions' => $sessions,
            'personnel' => $personnel,
            'gatewayOnline' => $gatewayOnline,
            'isAdmin' => $isAdmin,
            'isCommander' => $isCommander,
        ]);
    }

    /**
     * Memulai Pembuatan Sesi Baru & Permintaan QR Code
     */
    public function createSession(Request $request)
    {
        $request->validate([
            'label' => 'required|string|max:50',
            'role_access' => 'nullable|string|in:all,admin,komandan',
        ]);

        $user = Auth::user();
        $cleanSlug = Str::slug($request->input('label'));
        if (empty($cleanSlug)) {
            $cleanSlug = 'wa-session';
        }
        $sessionId = $cleanSlug . '-' . substr(uniqid(), -4);

        // Simpan sesi ke database lokal
        $session = WaSession::create([
            'session_id' => $sessionId,
            'label' => $request->input('label'),
            'role_access' => $request->input('role_access', 'all'),
            'status' => 'connecting',
            'created_by' => $user->id,
        ]);

        // Hubungi Gateway Node.js
        try {
            $res = Http::timeout(10)->post("{$this->gatewayUrl}/sessions", [
                'sessionId' => $sessionId,
                'label' => $session->label,
            ]);

            if ($res->successful()) {
                $data = $res->json();
                return response()->json([
                    'ok' => true,
                    'session' => $session,
                    'qr' => $data['qr'] ?? null,
                    'status' => $data['status'] ?? 'CONNECTING',
                ]);
            }

            return response()->json([
                'ok' => false,
                'error' => 'Gateway Node.js merespon error: ' . $res->body(),
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Gagal terhubung ke Gateway Node.js port 3000. Pastikan layanan wa-gateway aktif.',
            ], 503);
        }
    }

    /**
     * Mengambil QR Code Terbaru dari Sesi
     */
    public function getQr(string $sessionId)
    {
        try {
            $res = Http::timeout(5)->get("{$this->gatewayUrl}/sessions/{$sessionId}/qr");
            if ($res->successful()) {
                $data = $res->json();

                // Jika sudah terkoneksi, update database lokal
                if (($data['status'] ?? '') === 'CONNECTED') {
                    $session = WaSession::where('session_id', $sessionId)->first();
                    if ($session) {
                        $session->update([
                            'status' => 'connected',
                            'phone_number' => $data['phone'] ?? $session->phone_number,
                            'last_active_at' => now(),
                        ]);
                    }
                }

                return response()->json($data);
            }

            return response()->json(['ok' => false, 'error' => 'Gagal mengambil QR.'], $res->status());
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 503);
        }
    }

    /**
     * Mengambil Status Sesi
     */
    public function getStatus(string $sessionId)
    {
        try {
            $res = Http::timeout(4)->get("{$this->gatewayUrl}/sessions/{$sessionId}/status");
            if ($res->successful()) {
                $data = $res->json();
                $session = WaSession::where('session_id', $sessionId)->first();
                if ($session) {
                    $session->update([
                        'status' => strtolower($data['status'] ?? 'disconnected'),
                        'phone_number' => $data['phone'] ?? $session->phone_number,
                        'last_active_at' => now(),
                    ]);
                }
                return response()->json($data);
            }
            return response()->json(['ok' => false, 'error' => 'Sesi tidak aktif.'], 404);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 503);
        }
    }

    /**
     * Memutuskan Koneksi (Logout) & Menghapus Token Sesi
     */
    public function logoutSession(string $sessionId)
    {
        try {
            Http::timeout(6)->post("{$this->gatewayUrl}/sessions/{$sessionId}/logout");
        } catch (\Exception $e) {
            Log::warning("Logout gateway error: " . $e->getMessage());
        }

        $session = WaSession::where('session_id', $sessionId)->first();
        if ($session) {
            $session->update([
                'status' => 'disconnected',
                'phone_number' => null,
            ]);
        }

        return response()->json(['ok' => true, 'message' => 'Sesi WhatsApp berhasil diputuskan.']);
    }

    /**
     * Menghapus Sesi Sepenuhnya dari Database & Server
     */
    public function deleteSession(string $sessionId)
    {
        try {
            Http::timeout(5)->delete("{$this->gatewayUrl}/sessions/{$sessionId}");
        } catch (\Exception $e) {}

        WaSession::where('session_id', $sessionId)->delete();
        return response()->json(['ok' => true, 'message' => 'Sesi berhasil dihapus.']);
    }

    /**
     * Mengambil Riwayat Panggilan / Telepon
     */
    public function getCalls(string $sessionId)
    {
        try {
            $res = Http::timeout(6)->get("{$this->gatewayUrl}/sessions/{$sessionId}/calls");
            if ($res->successful()) {
                return response()->json($res->json());
            }
            return response()->json(['ok' => false, 'calls' => []]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'calls' => []]);
        }
    }

    /**
     * Mengambil Daftar Percakapan Aktif
     */
    public function getChats(string $sessionId)
    {
        try {
            $res = Http::timeout(6)->get("{$this->gatewayUrl}/sessions/{$sessionId}/chats");
            if ($res->successful()) {
                return response()->json($res->json());
            }
            return response()->json(['ok' => false, 'chats' => []]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 503);
        }
    }

    /**
     * Mengambil Riwayat Pesan dari Kontak Tertentu
     */
    public function getMessages(string $sessionId, string $jid)
    {
        try {
            $res = Http::timeout(6)->get("{$this->gatewayUrl}/sessions/{$sessionId}/chats/{$jid}/messages");
            if ($res->successful()) {
                return response()->json($res->json());
            }
            return response()->json(['ok' => false, 'messages' => []]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 503);
        }
    }

    /**
     * Mengirim Pesan WhatsApp Melalui Sesi Tertentu
     */
    public function sendMessage(Request $request, string $sessionId)
    {
        $request->validate([
            'number' => 'required|string',
            'message' => 'required|string',
        ]);

        try {
            $res = Http::timeout(10)->post("{$this->gatewayUrl}/sessions/{$sessionId}/send", [
                'number' => $request->input('number'),
                'message' => $request->input('message'),
            ]);

            if ($res->successful()) {
                return response()->json($res->json());
            }

            // Fallback ke endpoint lama jika gateway masih versi tunggal
            if ($res->status() === 404) {
                $legacyRes = Http::timeout(10)->get("{$this->gatewayUrl}/send", [
                    'number' => $request->input('number'),
                    'msg'    => $request->input('message'),
                ]);
                if ($legacyRes->successful()) {
                    return response()->json([
                        'ok' => true,
                        'message' => 'Pesan berhasil dikirim via gateway.',
                    ]);
                }
            }

            return response()->json([
                'ok' => false,
                'error' => $res->json()['error'] ?? 'Gagal mengirim pesan.',
            ], $res->status());
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 503);
        }
    }
}
