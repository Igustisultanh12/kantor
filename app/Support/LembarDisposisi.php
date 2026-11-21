<?php

namespace App\Support;

use App\Models\Letter;
use App\Models\LetterDisposition;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Lembar disposisi A5 (surat masuk / telegram) yang digabung ke halaman depan PDF arsip.
 *
 * - Dibuat saat Komandan mendisposisi, dibuat ulang setiap jabatan tujuan melapor selesai (kolom Paraf).
 * - Berkas unggahan asli dihapus HANYA setelah berkas gabungan berhasil disimpan.
 * - Bila PDF unggahan tidak bisa dibaca FPDI (meski sudah dinormalkan), berkas asli dipertahankan dan
 *   lembar disposisi disimpan terpisah (`disposition_sheet_path`) agar tidak ada data yang hilang.
 */
class LembarDisposisi
{
    private const DIR = 'archives/disposisi';

    /** @return array{merged: bool, sheet_only: bool} */
    public static function generate(Letter $letter): array
    {
        $letter->loadMissing(['recorder', 'disposition.giver', 'disposition.targets.completer', 'disposition.targets.receiver']);
        if (!$letter->disposition) {
            return ['merged' => false, 'sheet_only' => false];
        }

        $sheet = self::renderSheet($letter);
        $disk = self::diskOf($letter->file_path);
        $slug = ($letter->isTelegram() ? 'telegram' : 'surat') . '-' . $letter->id;

        try {
            if (!$disk) {
                throw new \RuntimeException('Berkas arsip tidak ditemukan.');
            }
            $merged = self::merge($sheet, Storage::disk($disk)->path($letter->file_path), (int) $letter->disposition_sheet_pages);
            $newPath = self::DIR . "/{$slug}-" . now()->format('YmdHis') . '.pdf';
            if (!Storage::disk('local')->put($newPath, $merged['bytes'])) {
                throw new \RuntimeException('Berkas gabungan gagal disimpan.');
            }

            $oldPath = $letter->file_path;
            $oldSheet = $letter->disposition_sheet_path;
            $letter->forceFill([
                'file_path' => $newPath,
                'disposition_sheet_pages' => $merged['sheet_pages'],
                'disposition_sheet_path' => null,
            ])->save();

            // Baru sekarang berkas lama (unggahan asli / gabungan sebelumnya) aman dihapus.
            if ($oldPath !== $newPath) {
                Storage::disk($disk)->delete($oldPath);
            }
            if ($oldSheet) {
                Storage::disk('local')->delete($oldSheet);
            }
            return ['merged' => true, 'sheet_only' => false];
        } catch (\Throwable $e) {
            Log::warning("[DISPOSISI] Lembar disposisi {$letter->agenda_number} tidak dapat digabung: {$e->getMessage()}");
            $sheetPath = self::DIR . "/lembar-{$slug}.pdf";
            Storage::disk('local')->put($sheetPath, $sheet);
            $letter->forceFill(['disposition_sheet_path' => $sheetPath])->save();
            return ['merged' => false, 'sheet_only' => true];
        }
    }

    /** Bytes PDF lembar disposisi (A5 potret). */
    public static function renderSheet(Letter $letter): string
    {
        return Pdf::loadView('pdf.lembar_disposisi', self::viewData($letter))
            ->setPaper('a5', 'portrait')
            ->output();
    }

    /** @return array<string, mixed> */
    public static function viewData(Letter $letter): array
    {
        $d = $letter->disposition;
        $telegram = $letter->isTelegram();
        $tgl = fn ($v) => $v ? Carbon::parse($v)->locale('id')->translatedFormat('j F Y') : '';
        $labels = $telegram ? AgendaSurat::JABATAN_TUJUAN : array_merge(AgendaSurat::JABATAN_TUJUAN, AgendaSurat::LABEL_LEMBAR_SURAT);
        $targets = $d ? $d->targets->keyBy('role') : collect();

        $rows = [];
        foreach ($labels as $role => $label) {
            $t = $targets->get($role);
            $paraf = null;
            if ($t && $t->status === 'selesai') {
                $paraf = ['nama' => $t->completer?->name ?? '-', 'waktu' => $t->completed_at?->format('d/m/y H:i')];
            } elseif ($t && $t->status === 'diterima') {
                $paraf = ['nama' => 'Diterima', 'waktu' => $t->received_at?->format('d/m/y H:i')];
            }
            $rows[] = ['label' => $label, 'dipilih' => (bool) $t, 'paraf' => $paraf, 'laporan' => $t?->report_note];
        }

        $giver = $d?->giver;
        $namaKomandan = $giver
            ? (($giver->pangkat && !str_starts_with((string) $giver->name, (string) $giver->pangkat)) ? "{$giver->pangkat} {$giver->name}" : $giver->name)
            : '';

        return [
            'telegram' => $telegram,
            'kop1' => self::setting('disposition_kop_1', 'KOMANDO DAERAH TNI ANGKATAN LAUT V'),
            'kop2' => self::setting('disposition_kop_2', 'DETASEMEN INTELIJEN'),
            'jabatanTtd' => self::setting('disposition_sign_title', 'DANDEN INTEL KODAERAL V'),
            'yth' => $telegram ? 'Yth. KOMANDAN' : 'Yth. Dan Den Intel',
            'agenda' => $letter->agenda_number,
            'tglTerima' => $tgl($letter->received_at),
            'dari' => $letter->issuer,
            'nomor' => $letter->letter_number,
            'tglSurat' => $tgl($letter->date),
            'perihal' => $letter->subject,
            'twu' => $letter->twu,
            'pemohon' => AgendaSurat::labelRole($letter->recorder?->role),
            'tglCatat' => $letter->created_at?->format('d/m/y H:i'),
            'rows' => $rows,
            'instruksi' => array_values(array_map(fn ($k) => LetterDisposition::INSTRUKSI[$k] ?? $k, $d?->instructions ?? [])),
            'catatan' => $d?->note,
            'urgensi' => $d ? (LetterDisposition::URGENSI[$d->urgency] ?? $d->urgency) : null,
            'batas' => $tgl($d?->due_date),
            'tglDisposisi' => $d?->created_at?->format('d/m/Y H:i'),
            'namaKomandan' => mb_strtoupper($namaKomandan),
        ];
    }

    /**
     * Lembar disposisi + halaman berkas (melewati $skipPages halaman lembar lama di depan berkas gabungan).
     *
     * @return array{bytes: string, sheet_pages: int}
     */
    private static function merge(string $sheet, string $sourcePath, int $skipPages): array
    {
        $normalized = self::normalize($sourcePath);
        try {
            $pdf = new Fpdi();
            $pdf->SetAutoPageBreak(false);

            $sheetPages = $pdf->setSourceFile(StreamReader::createByString($sheet));
            for ($i = 1; $i <= $sheetPages; $i++) {
                self::addImported($pdf, $pdf->importPage($i));
            }

            $count = $pdf->setSourceFile($normalized);
            if ($count <= $skipPages) {
                throw new \RuntimeException('Berkas arsip tidak memiliki halaman surat.');
            }
            for ($i = $skipPages + 1; $i <= $count; $i++) {
                self::addImported($pdf, $pdf->importPage($i));
            }

            return ['bytes' => $pdf->Output('S'), 'sheet_pages' => $sheetPages];
        } finally {
            if ($normalized !== $sourcePath && is_file($normalized)) {
                @unlink($normalized);
            }
        }
    }

    private static function addImported(Fpdi $pdf, string $tpl): void
    {
        $size = $pdf->getTemplateSize($tpl);
        $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl);
    }

    /** Turunkan PDF modern (xref stream/kompresi objek) ke PDF 1.4 agar terbaca FPDI versi gratis. */
    private static function normalize(string $path): string
    {
        // PDF yang sudah terbaca tidak perlu diubah.
        try {
            (new Fpdi())->setSourceFile($path);
            return $path;
        } catch (\Throwable) {
        }

        $out = tempnam(sys_get_temp_dir(), 'disp') . '.pdf';
        $commands = [
            'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=' . escapeshellarg($out) . ' ' . escapeshellarg($path),
            'qpdf --qdf --object-streams=disable ' . escapeshellarg($path) . ' ' . escapeshellarg($out),
            'pdftk ' . escapeshellarg($path) . ' output ' . escapeshellarg($out) . ' uncompress',
        ];
        foreach ($commands as $cmd) {
            @exec($cmd . ' 2>&1', $o, $code);
            if ($code === 0 && is_file($out) && filesize($out) > 0) {
                return $out;
            }
        }
        @unlink($out);
        return $path;
    }

    private static function diskOf(?string $path): ?string
    {
        foreach (['local', 'public'] as $disk) {
            if ($path && Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }
        return null;
    }

    private static function setting(string $key, string $default): string
    {
        $v = trim((string) Setting::where('key', $key)->value('value'));
        return $v !== '' ? $v : $default;
    }
}
