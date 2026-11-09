<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Lampiran struk/nota transaksi kas (Buku Kas & Kas Unit Teknis).
 * Format penyimpanan sama dengan web (CashController/TechnicalUnitCashController):
 * kolom `receipt_path` berisi JSON array path di disk `public`, atau string tunggal (data lama).
 */
class CashReceipts
{
    public const MAX_FILES = 10;
    public const RULES = [
        'keep' => 'nullable|array|max:' . self::MAX_FILES,
        'keep.*' => 'string|max:500',
        'receipt_files' => 'nullable|array|max:' . self::MAX_FILES,
        'receipt_files.*' => 'file|mimes:jpeg,jpg,png,pdf|max:20480',
    ];

    /** @return list<string> */
    public static function paths(Model $cash): array
    {
        $raw = $cash->getRawOriginal('receipt_path') ?? $cash->receipt_path;
        if (is_array($raw)) {
            $list = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $list = is_array($decoded) ? $decoded : [$raw];
        } else {
            $list = [];
        }
        return array_values(array_filter(array_map('strval', $list), fn ($p) => $p !== ''));
    }

    /** Data lampiran yang aman dikirim ke aplikasi. */
    public static function payload(Model $cash): array
    {
        return array_map(fn (string $path) => [
            'path' => $path,
            'url' => asset('storage/' . $path),
            'name' => basename($path),
            'is_pdf' => str_ends_with(strtolower($path), '.pdf'),
        ], self::paths($cash));
    }

    /**
     * Simpan susunan lampiran baru: [keep] = path lama yang dipertahankan,
     * [files] = unggahan baru. Berkas lama yang tidak dipertahankan dihapus.
     *
     * @param  list<string>  $keep
     * @param  list<UploadedFile>  $files
     */
    public static function sync(Model $cash, array $keep, array $files, string $dir): array
    {
        $current = self::paths($cash);
        // Hanya path milik transaksi ini yang boleh dipertahankan.
        $kept = array_values(array_intersect($current, $keep));
        if (count($kept) + count($files) > self::MAX_FILES) {
            throw new \InvalidArgumentException('Maksimal ' . self::MAX_FILES . ' lampiran per transaksi.');
        }

        $stored = [];
        foreach ($files as $file) {
            $stored[] = $file->store($dir, 'public');
        }

        foreach (array_diff($current, $kept) as $old) {
            if (Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }

        $final = array_merge($kept, $stored);
        $cash->forceFill(['receipt_path' => $final ? json_encode($final) : null])->save();

        return self::payload($cash->fresh());
    }

    public static function deleteAll(Model $cash): void
    {
        foreach (self::paths($cash) as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
