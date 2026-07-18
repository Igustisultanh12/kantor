<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    /**
     * Mengirim pesan WA via Server Node.js Internal (Port 3000)
     */
    public static function sendMessage($target, $message)
    {
        // 1. Bersihkan nomor (hilangkan spasi, strip, dll)
        $phone = preg_replace('/[^0-9]/', '', $target);

        // 2. Kirim perintah ke Server Node.js (wa-gateway)
        try {
            // PERBAIKAN: Tambahkan /send di akhir URL
            $response = Http::timeout(10)->get("http://127.0.0.1:3000/send", [
                'number' => $phone,
                'msg'    => $message
            ]);

            if ($response->successful()) {
                Log::info("WA Terkirim ke: $phone");
            } else {
                Log::error("Server WA Port 3000 merespon gagal: " . $response->body());
            }

        } catch (\Exception $e) {
            Log::error("Koneksi ke Server WA Gagal: " . $e->getMessage());
        }
    }
}