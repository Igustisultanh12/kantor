<?php

namespace App\Support;

use App\Models\Skhpp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** Naskah PDF SKHPP (dipakai unduhan PDF dan arsip otomatis di Agenda Surat). */
class SkhppPdf
{
    public static function buat(Skhpp $skhpp): \Barryvdh\DomPDF\PDF
    {
        $skhpp->loadMissing('members');

        $foto = function (?string $path): ?string {
            if (!$path || !Storage::disk('public')->exists($path)) {
                return null;
            }
            $full = Storage::disk('public')->path($path);
            return 'data:image/' . pathinfo($full, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($full));
        };
        $foto1_base64 = $foto($skhpp->foto_1);
        $foto2_base64 = $foto($skhpp->foto_2);

        // QR TTE (tanda tangan basah: tanpa QR). Tidak diambil saat tes agar tidak bergantung internet.
        $qr_base64 = null;
        $manual = $skhpp->ttd_mode === 'manual';
        if ($skhpp->status === 'approved' && !$manual && !app()->runningUnitTests()) {
            try {
                $verifyUrl = route('skhpp.verify', $skhpp->verification_code);
                $qrData = file_get_contents('https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($verifyUrl));
                if ($qrData) {
                    $qr_base64 = 'data:image/png;base64,' . base64_encode($qrData);
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal fetch QR image: ' . $e->getMessage());
            }
        }

        $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $tglLahir = $skhpp->tanggal_lahir ? new \DateTime($skhpp->tanggal_lahir) : null;
        $tanggal_lahir_indo = $tglLahir ? ($tglLahir->format('d') . ' ' . $months[(int) $tglLahir->format('n') - 1] . ' ' . $tglLahir->format('Y')) : '';

        $tglSkhpp = $skhpp->tanggal_skhpp ? new \DateTime($skhpp->tanggal_skhpp) : ($skhpp->approved_at ? new \DateTime($skhpp->approved_at) : new \DateTime());
        $tanggal_skhpp_indo = $tglSkhpp->format('j') . ' ' . $months[(int) $tglSkhpp->format('n') - 1] . ' ' . $tglSkhpp->format('Y');
        $bulan_tahun_indo = $months[(int) $tglSkhpp->format('n') - 1] . ' ' . $tglSkhpp->format('Y');
        // Naskah untuk TTD manual: hari dikosongkan (diisi tangan saat ditandatangani), bulan & tahun tetap tercetak.
        if ($manual && $skhpp->status !== 'approved') {
            $tanggal_skhpp_indo = ' ' . $bulan_tahun_indo;
        }

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.skhpp', compact(
            'skhpp', 'foto1_base64', 'foto2_base64', 'qr_base64',
            'tanggal_lahir_indo', 'tanggal_skhpp_indo', 'bulan_tahun_indo'
        ))->setPaper([0, 0, 609.45, 935.43], 'portrait'); // F4 / Folio (21,5 x 33 cm)
    }
}
