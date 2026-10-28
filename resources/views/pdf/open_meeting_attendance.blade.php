<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daftar Hadir - {{ $meeting->title }}</title>
    <style>
        @page {
            margin-top: 1.5cm;
            margin-right: 1.5cm;
            margin-bottom: 2cm;
            margin-left: 1.5cm;
            size: 215mm 330mm {{ $orientation ?? 'portrait' }}; /* Folio Standar Kedinasan TNI AL */
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #000000;
            margin: 0;
            padding: 0;
        }

        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }

        /* KOP SURAT KIRI */
        .kop-box {
            width: 320px;
            text-align: center;
            margin-bottom: 8px;
        }
        .kop-box .line1 {
            font-size: 10.5pt;
            font-weight: normal;
            letter-spacing: 0.5px;
        }
        .kop-box .line2 {
            font-size: 10.5pt;
            font-weight: normal;
            letter-spacing: 0.5px;
        }
        .kop-box .divider {
            border-bottom: 1.5px solid #000000;
            margin-top: 3px;
        }

        /* LOGO KEDINASAN */
        .logo-center {
            text-align: center;
            margin-top: 2px;
            margin-bottom: 6px;
        }
        .logo-center img {
            width: 90px;
            height: auto;
        }

        /* JUDUL DAFTAR HADIR */
        .title-section {
            text-align: center;
            margin-bottom: 12px;
        }
        .title-main {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .title-sub {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 2px;
        }

        /* META RAPAT (HARI, TANGGAL, WAKTU, TEMPAT) */
        .meta-table {
            width: 100%;
            margin-bottom: 12px;
            font-size: 10pt;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        /* TABEL DAFTAR PESERTA */
        .table-data {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin-bottom: 15px;
        }
        .table-data th, .table-data td {
            border: 1px solid #000000;
            padding: 5px 6px;
            vertical-align: middle;
        }
        .table-data th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 9.5pt;
        }

        /* AREA TANDA TANGAN KEDINASAN */
        .signature-table {
            width: 100%;
            margin-top: 15px;
            page-break-inside: avoid;
            font-size: 10pt;
            border-collapse: collapse;
        }
        .signature-table td {
            vertical-align: top;
            padding: 0;
        }
        .ttd-kanan {
            width: 320px;
            margin-left: auto;
            text-align: center;
        }
        .ttd-kiri {
            width: 280px;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- KOP RESMI DETASEMEN INTELIJEN KODAERAL V -->
    <div class="kop-box">
        <div class="line1">KOMANDO DAERAH TNI ANGKATAN LAUT V</div>
        <div class="line2">DETASEMEN INTELIJEN</div>
        <div class="divider"></div>
    </div>

    @php
        $bwLogoPath = public_path('images/logo_tni_al_bw.png');
        $colorLogoPath = public_path('images/logo_tni_al.png');
        $logoSrc = file_exists($bwLogoPath) ? $bwLogoPath : (file_exists($colorLogoPath) ? $colorLogoPath : '');
    @endphp
    @if($logoSrc)
        <div class="logo-center">
            <img src="{{ $logoSrc }}" alt="Logo TNI AL" />
        </div>
    @endif

    <!-- JUDUL DOKUMEN RESMI -->
    <div class="title-section">
        <div class="title-main">DAFTAR HADIR PESERTA RAPAT</div>
        <div class="title-sub">{{ $meeting->title }}</div>
    </div>

    <!-- METADATA RAPAT -->
    <table class="meta-table">
        <tr>
            <td style="width: 110px;">Hari / Tanggal</td>
            <td style="width: 12px; text-align: center;">:</td>
            <td>{{ $meeting->formatted_date }}</td>
        </tr>
        @if($meeting->time_start)
        <tr>
            <td>Waktu</td>
            <td style="text-align: center;">:</td>
            <td>Pukul {{ $meeting->time_start }} {{ $meeting->time_end ? 's.d. ' . $meeting->time_end : '' }} WIB</td>
        </tr>
        @endif
        @if($meeting->location)
        <tr>
            <td>Tempat</td>
            <td style="text-align: center;">:</td>
            <td>{{ $meeting->location }}</td>
        </tr>
        @endif
        @if($meeting->description)
        <tr>
            <td>Agenda / Ket.</td>
            <td style="text-align: center;">:</td>
            <td>{{ $meeting->description }}</td>
        </tr>
        @endif
    </table>

    @php
        $customFields = $meeting->custom_fields ?? [];
    @endphp

    <!-- TABEL REKAPITULASI KEHADIRAN (FORMAT DINAS MILITER) -->
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 4%;">NO</th>
                <th style="width: 24%;">NAMA LENGKAP</th>
                <th style="width: 16%;">PANGKAT / NRP</th>
                <th style="width: 18%;">JABATAN</th>
                @foreach($customFields as $field)
                    <th>{{ strtoupper($field['label']) }}</th>
                @endforeach
                <th style="width: 18%;">TANDA TANGAN / PARAF</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendees as $index => $att)
                @php
                    $cData = is_array($att->custom_data) ? $att->custom_data : json_decode($att->custom_data, true) ?? [];
                    $isOdd = (($index + 1) % 2 === 1);
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}.</td>
                    <td class="font-bold">{{ strtoupper($att->name) }}</td>
                    <td class="text-center">{{ $att->pangkat_nrp ?: '-' }}</td>
                    <td>{{ $att->jabatan ?: '-' }}</td>
                    @foreach($customFields as $field)
                        @php
                            $fName = $field['name'];
                            $val = $cData[$fName]['value'] ?? ($cData[$fName] ?? '-');
                        @endphp
                        <td class="text-center">{{ is_string($val) || is_numeric($val) ? $val : '-' }}</td>
                    @endforeach
                    <td style="height: 32px; vertical-align: middle;">
                        @if(!empty($att->signature))
                            <div class="text-center">
                                <img src="{{ $att->signature }}" style="max-height: 28px; max-width: 90px;" alt="Paraf" />
                            </div>
                        @else
                            @if($isOdd)
                                <div style="text-align: left; padding-left: 5px; font-size: 9pt;">
                                    {{ $index + 1 }}. .......................
                                </div>
                            @else
                                <div style="text-align: center; padding-left: 25px; font-size: 9pt;">
                                    {{ $index + 1 }}. .......................
                                </div>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 5 + count($customFields) }}" class="text-center" style="padding: 20px; font-style: italic;">
                        Belum ada peserta yang mengisi daftar hadir.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @php
        $bulanBaku = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $tglCetak = \Carbon\Carbon::parse($meeting->date);
        $tglStr = $tglCetak->format('j') . ' ' . ($bulanBaku[(int)$tglCetak->format('n')] ?? $tglCetak->format('F')) . ' ' . $tglCetak->format('Y');
    @endphp

    <!-- BLOK TANDA TANGAN (KIRI: PEMBUAT DAFTAR / PETUGAS | KANAN: KOMANDAN) -->
    <table class="signature-table">
        <tr>
            <!-- SISI KIRI: PETUGAS PEMBUAT ABSENSI -->
            <td style="width: 50%;">
                <div class="ttd-kiri">
                    <div style="margin-bottom: 2px;">Pembuat Daftar,</div>
                    <div style="font-size: 9pt; color: #444; margin-bottom: 4px;">{{ $creatorJabatan }}</div>

                    <div style="height: 55px;"></div>

                    <div class="font-bold" style="text-decoration: underline;">
                        {{ $creatorName }}
                    </div>
                    @if($creatorPangkatNrp && $creatorPangkatNrp !== '-')
                        <div style="font-size: 9pt; margin-top: 1px;">
                            {{ $creatorPangkatNrp }}
                        </div>
                    @endif
                </div>
            </td>

            <!-- SISI KANAN: KOMANDAN DETASEMEN INTELIJEN KODAERAL V -->
            <td style="width: 50%;">
                <div class="ttd-kanan">
                    <table style="width: 100%; text-align: left; margin-bottom: 3px; font-size: 10pt;">
                        <tr>
                            <td style="width: 90px;">Dikeluarkan di</td>
                            <td style="width: 10px;">:</td>
                            <td style="padding-left: 6px;">Surabaya</td>
                        </tr>
                        <tr>
                            <td>pada tanggal</td>
                            <td>:</td>
                            <td style="padding-left: 6px;">{{ $tglStr }}</td>
                        </tr>
                    </table>
                    <div style="border-bottom: 1px solid #000; margin-bottom: 5px;"></div>

                    <div style="font-size: 10.5pt; font-weight: normal; margin-bottom: 2px;">
                        Komandan Detasemen Intelijen Kodaeral V,
                    </div>

                    @if(!empty($qr_base64))
                        <div style="margin: 3px auto; text-align: center;">
                            <img src="{{ $qr_base64 }}" style="width: 65px; height: 65px; display: inline-block;" alt="QR Validasi" />
                            <div style="font-size: 7pt; font-family: monospace; color: #333; margin-top: 1px;">
                                DOKUMEN RESMI SINDEN
                            </div>
                        </div>
                    @else
                        <div style="height: 60px;"></div>
                    @endif

                    <div class="font-bold" style="text-decoration: underline; font-size: 10.5pt;">
                        {{ $commanderName }}
                    </div>
                    <div style="font-size: 9.5pt; margin-top: 1px;">
                        {{ $commanderPangkatNrp }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
