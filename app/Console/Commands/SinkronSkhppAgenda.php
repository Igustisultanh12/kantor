<?php

namespace App\Console\Commands;

use App\Http\Controllers\SkhppController;
use App\Models\LetterLog;
use App\Models\Skhpp;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Sinkronkan SKHPP lama ke Agenda Surat (sekali jalan setelah fitur ini dipasang):
 * - SKHPP TTE yang masih menunggu TTE Komandan: nomor dibooking & dicatat (status belum ada PDF).
 * - SKHPP TTE yang sudah terbit: dicatat bila belum ada, lalu naskah ber-QR diarsipkan (ber-PDF).
 * Default hanya pratinjau; perubahan baru disimpan dengan --simpan. Aman dijalankan ulang.
 *
 *   php artisan skhpp:sinkron-agenda
 *   php artisan skhpp:sinkron-agenda --simpan
 */
class SinkronSkhppAgenda extends Command
{
    protected $signature = 'skhpp:sinkron-agenda {--simpan : Simpan perubahan (tanpa ini hanya pratinjau)}';

    protected $description = 'Masukkan SKHPP TTE yang menunggu ke Agenda Surat dan arsipkan SKHPP TTE yang sudah terbit.';

    public function handle(SkhppController $skhpp): int
    {
        $simpan = (bool) $this->option('simpan');

        // TTE menunggu tanda tangan (urut pengajuan, supaya nomor urutnya mengikuti urutan masuk).
        $menunggu = Skhpp::where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('ttd_mode')->orWhere('ttd_mode', '!=', 'manual'))
            ->where('nomor_tulis_tangan', false)
            ->orderBy('id')->get()
            ->filter(fn (Skhpp $s) => !$s->nomor_skhpp || !LetterLog::where('full_number', $s->nomor_skhpp)->exists());

        // TTE sudah terbit yang entri Agenda Surat-nya belum ada / belum ber-PDF.
        $terbit = Skhpp::where('status', 'approved')
            ->where(fn ($q) => $q->whereNull('ttd_mode')->orWhere('ttd_mode', '!=', 'manual'))
            ->where('nomor_tulis_tangan', false)
            ->whereNotNull('nomor_skhpp')
            ->orderBy('id')->get()
            ->filter(fn (Skhpp $s) => !LetterLog::where('full_number', $s->nomor_skhpp)->where('is_archived', true)->exists());

        $this->info("Menunggu TTE, belum di Agenda Surat: {$menunggu->count()}");
        foreach ($menunggu as $s) {
            $nomor = $simpan ? $skhpp->bookingTte($s, new Request()) : ($s->nomor_skhpp ?: '(nomor berikutnya)');
            $this->line("  #{$s->id} {$s->nama} -> {$nomor}");
        }

        $this->info("Sudah terbit (TTE), belum terarsip: {$terbit->count()}");
        foreach ($terbit as $s) {
            if ($simpan) {
                $skhpp->sinkronTerbitKeAgenda($s);
            }
            $this->line("  #{$s->id} {$s->nama} -> {$s->nomor_skhpp}");
        }

        if (!$simpan) {
            $this->warn('Pratinjau saja. Jalankan ulang dengan --simpan untuk menyimpan.');
        }

        return self::SUCCESS;
    }
}
