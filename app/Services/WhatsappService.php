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
        if (empty($target)) {
            Log::warning("WhatsappService: Target phone number is empty.");
            return;
        }

        // 1. Bersihkan nomor (hilangkan spasi, strip, tanda plus, dll)
        $phone = preg_replace('/[^0-9]/', '', $target);

        // 2. Normalisasi nomor Indonesia (08xxx -> 628xxx)
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        // 3. Kirim perintah ke Server Node.js (wa-gateway)
        try {
            $response = Http::timeout(10)->get("http://127.0.0.1:3000/send", [
                'number' => $phone,
                'msg'    => $message
            ]);

            if ($response->successful()) {
                Log::info("WA Terkirim ke: {$phone}");
            } else {
                Log::error("Server WA Port 3000 merespon gagal: " . $response->body());
            }

        } catch (\Exception $e) {
            Log::error("Koneksi ke Server WA Gagal: " . $e->getMessage());
        }
    }
}