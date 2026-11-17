<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Teks waktu pengesahan untuk halaman verifikasi publik, diformat di server
 * (zona waktu aplikasi) supaya tidak bergantung pada zona waktu/peramban pemindai.
 * Sumber yang hanya berupa tanggal (tanpa jam) ditampilkan tanpa jam, bukan "00.00".
 */
class WaktuTte
{
    private const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    private const ZONA = ['Asia/Jakarta' => 'WIB', 'Asia/Pontianak' => 'WIB', 'Asia/Makassar' => 'WITA', 'Asia/Jayapura' => 'WIT'];

    /**
     * @param  mixed  $waktu      DateTime/Carbon/string berjam (mis. approved_at)
     * @param  mixed  $tanggal    cadangan tanggal saja (mis. tanggal_surat) bila $waktu kosong
     */
    public static function teks($waktu, $tanggal = null): ?string
    {
        $tz = config('app.timezone');

        if (!empty($waktu)) {
            $c = Carbon::parse($waktu)->setTimezone($tz);
            $zona = self::ZONA[$tz] ?? $c->format('T');
            return $c->format('j') . ' ' . self::BULAN[(int) $c->format('n')] . ' ' . $c->format('Y') . ', pukul ' . $c->format('H.i') . ' ' . $zona;
        }

        if (!empty($tanggal)) {
            $c = Carbon::parse($tanggal instanceof \DateTimeInterface ? $tanggal->format('Y-m-d') : substr((string) $tanggal, 0, 10), $tz);
            return $c->format('j') . ' ' . self::BULAN[(int) $c->format('n')] . ' ' . $c->format('Y');
        }

        return null;
    }
}
