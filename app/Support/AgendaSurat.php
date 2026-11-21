<?php

namespace App\Support;

use App\Models\Letter;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Penomoran agenda surat & telegram masuk + daftar jabatan tujuan disposisi.
 *
 * Format nomor diambil dari Setting (`incoming_agenda_format` untuk surat, `telegram_agenda_format`
 * untuk telegram) sehingga bisa diganti tanpa ubah kode.
 * Token: {urut} nomor urut, {romawi} bulan romawi, {bulan} bulan 2 digit, {tahun} tahun 4 digit.
 * Nomor urut surat dan telegram terpisah, masing-masing berulang dari 1 setiap tahun (tanggal terima).
 */
class AgendaSurat
{
    public const DEFAULT_FORMAT = 'SM / {urut} / {romawi} / {tahun}';
    public const DEFAULT_FORMAT_TELEGRAM = 'TG / {urut} / {romawi} / {tahun}';

    /** Jabatan tujuan disposisi Komandan (kunci = nilai kolom users.role), urut seperti lembar disposisi. */
    public const JABATAN_TUJUAN = [
        'wadan' => 'Wadan',
        'pasops' => 'Pasops',
        'danunit1' => 'Dantim I / Lid',
        'danunit2' => 'Dantim II / Pamgal',
        'danunitteknis' => 'Dantim III / Teknis',
        'kaurmintel' => 'Kaurmintel',
        'paurset' => 'Paurset',
    ];

    /** Label kolom "Kepada" pada lembar disposisi surat (mengikuti formulir cetak; telegram memakai JABATAN_TUJUAN). */
    public const LABEL_LEMBAR_SURAT = ['danunitteknis' => 'Dantim Teknis'];

    public static function toRoman(int $month): string
    {
        return ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month - 1] ?? 'I';
    }

    public static function normalizeType(?string $type): string
    {
        return $type === 'telegram' ? 'telegram' : 'masuk';
    }

    public static function format(string $type = 'masuk'): string
    {
        $telegram = self::normalizeType($type) === 'telegram';
        $f = trim((string) Setting::where('key', $telegram ? 'telegram_agenda_format' : 'incoming_agenda_format')->value('value'));
        if ($f !== '' && str_contains($f, '{urut}')) {
            return $f;
        }
        return $telegram ? self::DEFAULT_FORMAT_TELEGRAM : self::DEFAULT_FORMAT;
    }

    /** Nomor urut berikutnya untuk tipe & tahun tanggal terima (huruf di belakang angka, mis. "14a", diabaikan). */
    public static function nextSequence(string $receivedAt, string $type = 'masuk'): int
    {
        $year = Carbon::parse($receivedAt)->year;
        $max = Letter::where('type', self::normalizeType($type))
            ->whereNotNull('agenda_sequence')
            ->whereYear('received_at', $year)
            ->pluck('agenda_sequence')
            ->map(fn ($s) => (int) preg_replace('/\D.*$/', '', (string) $s))
            ->max();
        return ((int) $max) + 1;
    }

    public static function build(string $sequence, string $receivedAt, string $type = 'masuk'): string
    {
        $d = Carbon::parse($receivedAt);
        return strtr(self::format($type), [
            '{urut}' => $sequence,
            '{romawi}' => self::toRoman($d->month),
            '{bulan}' => $d->format('m'),
            '{tahun}' => (string) $d->year,
        ]);
    }

    /** Label jabatan untuk role apa pun (dipakai di C-1 "Mohon disposisi <jabatan pencatat>"). */
    public static function labelRole(?string $role): string
    {
        $key = strtolower(preg_replace('/[\s_-]+/', '', (string) $role));
        return self::JABATAN_TUJUAN[$key] ?? match ($key) {
            'staf' => 'Staf',
            'admin' => 'Admin',
            'komandan' => 'Komandan',
            '' => '-',
            default => ucfirst($key),
        };
    }
}
