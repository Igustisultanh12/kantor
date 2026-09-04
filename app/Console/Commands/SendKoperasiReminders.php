<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KoperasiLoan;
use App\Models\KoperasiInstallment;
use App\Models\AppNotification;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendKoperasiReminders extends Command
{
    /**
     * Nama komando yang dipanggil via CLI / scheduler
     * Contoh: php artisan koperasi:send-reminders
     * Atau:   php artisan koperasi:send-reminders --force
     */
    protected $signature = 'koperasi:send-reminders {--force : Abaikan pengecekan tanggal 1 dan kirim pengingat langsung}';

    /**
     * Deskripsi tugas komando
     */
    protected $description = 'Mengirimkan notifikasi pengingat pembayaran cicilan pinjaman koperasi setiap tanggal 1 via SINDEN dan WhatsApp';

    public function handle()
    {
        $today = now();
        $isFirstDay = ((int)$today->day === 1);
        $isForce = $this->option('force');

        $this->info("=== RADAR PENGINGAT ANGSURAN KOPERASI SINDEN ===");
        $this->info("Waktu Pemindaian: " . $today->translatedFormat('l, d F Y H:i:s'));

        if (!$isFirstDay && !$isForce) {
            $this->warn("Hari ini bukan tanggal 1 (Hari ini: " . $today->format('d-m-Y') . ").");
            $this->line("Pengingat rutin hanya dieksekusi setiap tanggal 1 secara otomatis.");
            $this->line("Gunakan opsi --force jika ingin melakukan pengujian pengiriman paksa.");
            return 0;
        }

        // Ambil seluruh pinjaman yang berstatus aktif dan masih memiliki sisa hutang
        $activeLoans = KoperasiLoan::with('user')
            ->where('status', 'active')
            ->where('remaining_amount', '>', 0)
            ->get();

        if ($activeLoans->isEmpty()) {
            $this->info("Tidak ditemukan pinjaman aktif yang memiliki kewajiban cicilan.");
            return 0;
        }

        $this->info("Ditemukan " . $activeLoans->count() . " pinjaman aktif. Memproses pengiriman notifikasi...");

        $successCount = 0;
        $skippedCount = 0;
        $currentMonthYear = $today->format('Y-m');

        foreach ($activeLoans as $loan) {
            $user = $loan->user;
            if (!$user) {
                $this->warn("Pinjaman #{$loan->loan_code} tidak memiliki relasi user. Dilewati.");
                $skippedCount++;
                continue;
            }

            // Cegah duplikasi notifikasi pada bulan yang sama jika tidak menggunakan opsi --force
            if (!$isForce) {
                $alreadySent = AppNotification::where('user_id', $user->id)
                    ->where('title', 'like', '%Pengingat Pembayaran Cicilan Koperasi%')
                    ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$currentMonthYear])
                    ->exists();

                if ($alreadySent) {
                    $this->line("Pengingat bulan {$currentMonthYear} untuk {$user->name} sudah pernah dikirim sebelumnya. Dilewati.");
                    $skippedCount++;
                    continue;
                }
            }

            // Cari angsuran aktif terdekat yang belum lunas
            $nextInstallment = KoperasiInstallment::where('loan_id', $loan->id)
                ->where('status', '!=', 'paid')
                ->orderBy('installment_no', 'asc')
                ->first();

            $installmentNo = $nextInstallment ? $nextInstallment->installment_no : 1;
            $dueDateFormatted = $nextInstallment 
                ? Carbon::parse($nextInstallment->due_date)->translatedFormat('d F Y')
                : $today->format('01 M Y');

            $amountDueMonthly = min((float)$loan->remaining_amount, (float)$loan->monthly_installment);
            $formattedAmount = number_format($amountDueMonthly, 0, ',', '.');
            $formattedRemaining = number_format($loan->remaining_amount, 0, ',', '.');

            // 1. Narasi Notifikasi In-App SINDEN
            $inAppTitle = "Pengingat Pembayaran Cicilan Koperasi SINDEN";
            $inAppMessage = "Yth. {$user->pangkat} {$user->name}, hari ini tanggal 1 merupakan jadwal pembayaran cicilan Koperasi SINDEN untuk pinjaman {$loan->loan_code}. Nominal angsuran bulan ini: Rp {$formattedAmount} (Sisa pokok pinjaman: Rp {$formattedRemaining}). Pembayaran dapat dikoordinasikan dengan Pengurus Koperasi.";

            // 2. Narasi Notifikasi WhatsApp Resmi Kedinasan (Formal, Tanpa Emoji)
            $pangkatName = ($user->pangkat ? $user->pangkat . ' ' : '') . $user->name;
            $nrpText = $user->nrp ?: '-';

            $msgWa = "PENGINGAT PEMBAYARAN ANGSURAN BULANAN\n"
                . "========================================\n"
                . "Yth. {$pangkatName}\n"
                . "NRP: {$nrpText}\n\n"
                . "Diberitahukan bahwa hari ini tanggal 1 telah memasuki jadwal pembayaran angsuran rutin Koperasi SINDEN.\n\n"
                . "Rincian Tagihan Angsuran:\n"
                . "- Nomor Pinjaman: {$loan->loan_code}\n"
                . "- Angsuran Ke: {$installmentNo} dari {$loan->duration_months} Bulan\n"
                . "- Tagihan Bulan Ini: Rp {$formattedAmount}\n"
                . "- Sisa Pokok Pinjaman: Rp {$formattedRemaining}\n"
                . "- Jatuh Tempo: {$dueDateFormatted}\n\n"
                . "Pembayaran dapat dilakukan melalui mekanisme potong gaji dinas atau setor tunai kepada Pengurus Simpan Pinjam.\n\n"
                . "Informasi lengkap dan riwayat kartu angsuran dapat dipantau melalui aplikasi SINDEN pada menu Simpan Pinjam.\n\n"
                . "Demikian untuk menjadi perhatian. Terima kasih.";

            try {
                // Gunakan helper AppNotification::notify untuk mencatat in-app bell dan memicu WA Gateway
                AppNotification::notify(
                    $user->id,
                    null,
                    $inAppTitle,
                    $inAppMessage,
                    'warning',
                    route('simpan-pinjam.index'),
                    $msgWa,
                    $user->phone
                );

                $this->info("Berhasil mengirim pengingat ke: {$pangkatName} (WA: {$user->phone})");
                $successCount++;
            } catch (\Exception $e) {
                Log::error("Gagal mengirim pengingat simpan pinjam ke user ID {$user->id}: " . $e->getMessage());
                $this->error("Gagal mengirim ke {$pangkatName}: " . $e->getMessage());
            }
        }

        $this->info("=== SELESAI ===");
        $this->info("Total Berhasil Dikirim: {$successCount}");
        $this->info("Total Dilewati: {$skippedCount}");

        return 0;
    }
}
