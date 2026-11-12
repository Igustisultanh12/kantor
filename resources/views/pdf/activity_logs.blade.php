<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Log Aktivitas SISINDEN</title>
    <style>
        @page {
            margin-top: 1.5cm;
            margin-right: 1.5cm;
            margin-bottom: 2cm;
            margin-left: 1.5cm;
            size: 330mm 215mm; /* Folio mendatar, standar kedinasan TNI AL */
        }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; line-height: 1.3; color: #000; margin: 0; padding: 0; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }

        .kop-box { width: 320px; text-align: center; margin-bottom: 8px; }
        .kop-box .line1, .kop-box .line2 { font-size: 10.5pt; letter-spacing: 0.5px; }
        .kop-box .divider { border-bottom: 1.5px solid #000; margin-top: 3px; }

        .logo-center { text-align: center; margin-top: 2px; margin-bottom: 6px; }
        .logo-center img { width: 80px; height: auto; }

        .title-section { text-align: center; margin-bottom: 10px; }
        .title-main { font-size: 12pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .title-sub { font-size: 10.5pt; font-weight: bold; text-transform: uppercase; margin-top: 2px; }

        .meta-table { width: 100%; margin-bottom: 10px; font-size: 9.5pt; border-collapse: collapse; }
        .meta-table td { padding: 1.5px 0; vertical-align: top; }

        .table-data { width: 100%; border-collapse: collapse; font-size: 8pt; margin-bottom: 12px; }
        .table-data th, .table-data td { border: 1px solid #000; padding: 4px 5px; vertical-align: top; }
        .table-data th { background-color: #f2f2f2; font-weight: bold; text-align: center; text-transform: uppercase; font-size: 8pt; }
        .table-data tr { page-break-inside: avoid; }
        .muted { color: #444; font-size: 7.5pt; }
        .gagal { font-weight: bold; }

        .signature-table { width: 100%; margin-top: 15px; page-break-inside: avoid; font-size: 10pt; border-collapse: collapse; }
        .signature-table td { vertical-align: top; padding: 0; }
        .ttd-kanan { width: 320px; margin-left: auto; text-align: center; }
        .ttd-kiri { width: 280px; text-align: center; }
    </style>
</head>
<body>

    <div class="kop-box">
        <div class="line1">KOMANDO DAERAH TNI ANGKATAN LAUT V</div>
        <div class="line2">DETASEMEN INTELIJEN</div>
        <div class="divider"></div>
    </div>

    @php
        $bwLogoPath = public_path('images/logo_tni_al_bw.png');
        $colorLogoPath = public_path('images/logo_tni_al.png');
        $logoSrc = file_exists($bwLogoPath) ? $bwLogoPath : (file_exists($colorLogoPath) ? $colorLogoPath : '');
        $bulanBaku = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $tgl = fn ($d) => $d->format('j') . ' ' . $bulanBaku[(int) $d->format('n')] . ' ' . $d->format('Y');
        $tglStr = $tgl($printedAt);
    @endphp
    @if($logoSrc)
        <div class="logo-center"><img src="{{ $logoSrc }}" alt="Logo TNI AL" /></div>
    @endif

    <div class="title-section">
        <div class="title-main">LAPORAN JEJAK AKTIVITAS PENGGUNA</div>
        <div class="title-sub">SISTEM INFORMASI DENINTEL KODAERAL V (SISINDEN)</div>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 120px;">Periode</td><td style="width: 12px; text-align: center;">:</td>
            <td>{{ $period }}</td>
        </tr>
        <tr>
            <td>Personel</td><td style="text-align: center;">:</td>
            <td>{{ $filterUser ? trim(($filterUser->pangkat ? $filterUser->pangkat . ' ' : '') . $filterUser->name . ($filterUser->nrp ? ' NRP ' . $filterUser->nrp : '')) : 'Semua personel' }}</td>
        </tr>
        @if($filters['module'] || $filters['channel'] || $filters['status'] || $filters['search'])
        <tr>
            <td>Filter</td><td style="text-align: center;">:</td>
            <td>
                {{ collect([
                    $filters['module'] ? 'Modul ' . $filters['module'] : null,
                    $filters['channel'] ? ($filters['channel'] === 'android' ? 'Aplikasi Android' : 'Web') : null,
                    $filters['status'] ? ($filters['status'] === 'success' ? 'Hanya berhasil' : 'Hanya gagal') : null,
                    $filters['search'] ? 'Kata kunci "' . $filters['search'] . '"' : null,
                ])->filter()->implode('; ') }}
            </td>
        </tr>
        @endif
        <tr>
            <td>Jumlah aktivitas</td><td style="text-align: center;">:</td>
            <td>{{ number_format($total, 0, ',', '.') }} aktivitas{{ $total > $limit ? ' (ditampilkan ' . number_format($limit, 0, ',', '.') . ' pertama; persempit periode untuk mencetak sisanya)' : '' }}</td>
        </tr>
        <tr>
            <td>Dicetak</td><td style="text-align: center;">:</td>
            <td>{{ $tglStr }} pukul {{ $printedAt->format('H.i') }} WIB oleh {{ $printerName }}</td>
        </tr>
    </table>

    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 3%;">NO</th>
                <th style="width: 9%;">WAKTU</th>
                <th style="width: 15%;">PERSONEL</th>
                <th style="width: 22%;">AKTIVITAS</th>
                <th style="width: 16%;">PERANGKAT</th>
                <th style="width: 20%;">IP &amp; LOKASI</th>
                <th style="width: 7%;">HASIL</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $i => $log)
                <tr>
                    <td class="text-center">{{ $i + 1 }}.</td>
                    <td class="text-center">
                        {{ $log->created_at?->format('d/m/Y') }}<br>
                        <span class="muted">{{ $log->created_at?->format('H.i.s') }} WIB</span>
                    </td>
                    <td>
                        <span class="font-bold">{{ strtoupper($log->user_name ?? '-') }}</span><br>
                        <span class="muted">{{ trim(($log->pangkat ?? '') . ($log->nrp ? ' NRP ' . $log->nrp : '')) ?: '-' }}</span>
                    </td>
                    <td>
                        {{ $log->action }}
                        @if(!empty($log->subject))
                            <span class="muted">(data #{{ collect($log->subject)->implode(', #') }})</span>
                        @endif
                        <br><span class="muted">{{ $log->module }} · {{ $log->method }} {{ $log->path }}</span>
                    </td>
                    <td>
                        {{ $log->device ?: '-' }}<br>
                        <span class="muted">{{ $log->channel === 'android' ? 'Aplikasi Android' : 'Web' }}</span>
                    </td>
                    <td>
                        {{ $log->ip ?: '-' }}<br>
                        <span class="muted">{{ $log->ip_location ?: 'Lokasi IP tidak diketahui' }}</span>
                        @if($log->latitude !== null)
                            <br><span class="muted">GPS {{ number_format($log->latitude, 5, '.', '') }}, {{ number_format($log->longitude, 5, '.', '') }}{{ $log->location_accuracy ? ' (±' . $log->location_accuracy . ' m)' : '' }}</span>
                        @endif
                    </td>
                    <td class="text-center {{ $log->success ? '' : 'gagal' }}">
                        {{ $log->success ? 'Berhasil' : 'Gagal' }}<br>
                        <span class="muted">{{ $log->status }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 20px; font-style: italic;">Tidak ada aktivitas pada periode / filter ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="signature-table">
        <tr>
            <td style="width: 50%;">
                <div class="ttd-kiri">
                    <div style="margin-bottom: 2px;">Pembuat Laporan,</div>
                    <div style="font-size: 9pt; color: #444; margin-bottom: 4px;">{{ $printerJabatan }}</div>
                    <div style="height: 55px;"></div>
                    <div class="font-bold" style="text-decoration: underline;">{{ $printerName }}</div>
                    @if($printerPangkatNrp)
                        <div style="font-size: 9pt; margin-top: 1px;">{{ $printerPangkatNrp }}</div>
                    @endif
                </div>
            </td>
            <td style="width: 50%;">
                <div class="ttd-kanan">
                    <table style="width: 100%; text-align: left; margin-bottom: 3px; font-size: 10pt;">
                        <tr><td style="width: 90px;">Dikeluarkan di</td><td style="width: 10px;">:</td><td style="padding-left: 6px;">Surabaya</td></tr>
                        <tr><td>pada tanggal</td><td>:</td><td style="padding-left: 6px;">{{ $tglStr }}</td></tr>
                    </table>
                    <div style="border-bottom: 1px solid #000; margin-bottom: 5px;"></div>
                    <div style="font-size: 10.5pt; margin-bottom: 2px;">Komandan Detasemen Intelijen Kodaeral V,</div>
                    <div style="height: 60px;"></div>
                    <div class="font-bold" style="text-decoration: underline; font-size: 10.5pt;">{{ $commanderName }}</div>
                    <div style="font-size: 9.5pt; margin-top: 1px;">{{ $commanderPangkatNrp }}</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
