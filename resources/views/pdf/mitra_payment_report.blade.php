<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daftar Pembayaran Mitra {{ $tahun }}</title>
    <style>
        /* Format disamakan dengan PDF Daftar Hadir Rapat (Folio, kop & tanda tangan dinas). */
        @page { margin: 1.3cm 1.3cm 1.6cm 1.3cm; size: 330mm 215mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9.5pt; line-height: 1.3; color: #000; margin: 0; padding: 0; }
        .text-center { text-align: center; } .font-bold { font-weight: bold; }
        .kop-box { width: 320px; text-align: center; margin-bottom: 8px; }
        .kop-box .line { font-size: 10.5pt; letter-spacing: .5px; }
        .kop-box .divider { border-bottom: 1.5px solid #000; margin-top: 3px; }
        .logo-center { text-align: center; margin: 2px 0 6px; }
        .logo-center img { width: 80px; height: auto; }
        .title-section { text-align: center; margin-bottom: 12px; }
        .title-main { font-size: 12pt; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }
        .title-sub { font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-top: 2px; }
        .table-data { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-bottom: 8px; }
        .table-data th, .table-data td { border: 1px solid #000; padding: 4px 4px; vertical-align: middle; }
        .table-data th { background: #f2f2f2; font-weight: bold; text-align: center; text-transform: uppercase; font-size: 7.5pt; }
        .month { width: 4.3%; text-align: center; }
        /* Centang memakai DejaVu Sans (bawaan dompdf) yang memuat glyph ✓; font Arial/Times di dompdf tidak. */
        .check { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; font-weight: bold; line-height: 1; }
        .legend { font-size: 8pt; margin-bottom: 10px; }
        .summary td { background: #f7f7f7; font-weight: bold; }
        .signature-table { width: 100%; margin-top: 12px; page-break-inside: avoid; font-size: 10pt; border-collapse: collapse; }
        .signature-table td { vertical-align: top; padding: 0; }
        .ttd-kanan { width: 320px; margin-left: auto; text-align: center; }
        .ttd-kiri { width: 280px; text-align: center; }
    </style>
</head>
<body>
    @php
        $bwLogoPath = public_path('images/logo_tni_al_bw.png');
        $colorLogoPath = public_path('images/logo_tni_al.png');
        $logoSrc = file_exists($bwLogoPath) ? $bwLogoPath : (file_exists($colorLogoPath) ? $colorLogoPath : '');
        $bulan = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $short = [1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR', 5 => 'MEI', 6 => 'JUN', 7 => 'JUL', 8 => 'AGU', 9 => 'SEP', 10 => 'OKT', 11 => 'NOV', 12 => 'DES'];
        $now = now();
        $tglStr = $now->format('j') . ' ' . $bulan[(int) $now->format('n')] . ' ' . $now->format('Y');
        $perBulan = array_fill(1, 12, 0);
    @endphp

    <div class="kop-box">
        <div class="line">KOMANDO DAERAH TNI ANGKATAN LAUT V</div>
        <div class="line">DETASEMEN INTELIJEN</div>
        <div class="divider"></div>
    </div>

    @if($logoSrc)
        <div class="logo-center"><img src="{{ $logoSrc }}" alt="Logo TNI AL" /></div>
    @endif

    <div class="title-section">
        <div class="title-main">DAFTAR PEMBAYARAN MITRA</div>
        <div class="title-sub">TAHUN {{ $tahun }}</div>
    </div>

    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 3%;">NO</th>
                <th style="width: 13%;">NAMA</th>
                <th style="width: 19%;">PT / PERUSAHAAN</th>
                <th style="width: 10%;">NO. TLP</th>
                @foreach($short as $m)
                    <th class="month">{{ $m }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($mitras as $i => $mitra)
                <tr>
                    <td class="text-center">{{ $i + 1 }}.</td>
                    <td class="font-bold">{{ $mitra->nama }}</td>
                    <td>{{ $mitra->pt ?: '-' }}</td>
                    <td class="text-center" style="font-family: 'Courier New', monospace;">{{ $mitra->no_tlp ?: '-' }}</td>
                    @for($m = 1; $m <= 12; $m++)
                        @php $paid = (bool) ($mitra->payment_matrix[$m] ?? false); if ($paid) { $perBulan[$m]++; } @endphp
                        <td class="month">
                            @if($paid)<span class="check">&#10003;</span>@endif
                        </td>
                    @endfor
                </tr>
            @empty
                <tr><td colspan="16" class="text-center" style="padding: 18px; font-style: italic;">Belum ada data mitra.</td></tr>
            @endforelse
            @if(count($mitras))
                <tr class="summary">
                    <td colspan="4" style="text-align: right;">JUMLAH LUNAS</td>
                    @for($m = 1; $m <= 12; $m++)
                        <td class="month">{{ $perBulan[$m] }}</td>
                    @endfor
                </tr>
            @endif
        </tbody>
    </table>
    <div class="legend">Keterangan: <span class="check">&#10003;</span> = sudah membayar pada bulan tersebut; kosong = belum membayar.</div>

    <table class="signature-table">
        <tr>
            <td style="width: 50%;">
                <div class="ttd-kiri">
                    <div style="margin-bottom: 2px;">Pembuat Daftar,</div>
                    <div style="font-size: 9pt; color: #444; margin-bottom: 4px;">{{ $creatorJabatan }}</div>
                    <div style="height: 55px;"></div>
                    <div class="font-bold" style="text-decoration: underline;">{{ $creatorName }}</div>
                    @if($creatorPangkatNrp)
                        <div style="font-size: 9pt; margin-top: 1px;">{{ $creatorPangkatNrp }}</div>
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
                    <div class="font-bold" style="text-decoration: underline; font-size: 10.5pt;">{{ $commander['name'] }}</div>
                    <div style="font-size: 9.5pt; margin-top: 1px;">{{ $commander['pangkat_nrp'] }}</div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
