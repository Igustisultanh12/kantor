<!DOCTYPE html>
<html>
<head>
    <title>LAPORAN REKENING KOMANDAN</title>
    <style> body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            line-height: 1.4;
        }
        .header {
            text-transform: uppercase;
            font-weight: bold;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
            width: fit-content;
            margin-bottom: 25px;
        }
        .title {
            text-align: center;
            margin-bottom: 30px;
        }
        .title h3 {
            font-size: 16px;
            text-decoration: underline;
            margin: 0;
            font-weight: bold;
        }
        .title p {
            font-size: 11px;
            margin: 5px 0 0 0;
            font-style: italic;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        table, th, td {
            border: 1px solid #000;
        }
        th {
            background-color: #f2f2f2;
            padding: 8px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            text-align: center;
        }
        td {
            padding: 8px;
            vertical-align: top;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        
        .footer-sign {
            margin-top: 40px;
            float: right;
            text-align: center;
            width: 250px;
        }
        .footer-sign p {
            margin: 0;
        }
        .space-sign {
            height: 70px;
        }
    </style>
</head>
<body>

    <div class="header">
        <p>KOMANDO DAERAH TNI ANGKATAN LAUT V<br>
        <span style="padding-left: 25px;">DETASEMEN INTELIJEN</span></p>
    </div>

    <div class="title">
        <h3>DAFTAR MUTASI DAN ALOKASI DANA REKENING KOMANDAN</h3>
        <p>Periode Tahun Anggaran: {{ date('Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 15%;">TANGGAL</th>
                <th style="width: 50%;">KETERANGAN MEKANISME</th>
                <th style="width: 15%;">DEBET (MASUK)</th>
                <th style="width: 15%;">KREDIT (KELUAR)</th>
            </tr>
        </thead>
        <tbody>
            @php $saldoBerjalan = 0; @endphp
            @foreach($logs as $log)
                @php 
                    if($log->jenis == 'MASUK') $saldoBerjalan += $log->jumlah;
                    else $saldoBerjalan -= $log->jumlah;
                @endphp
                <tr>
                    <td class="text-center">{{ date('d M Y', strtotime($log->tanggal)) }}</td>
                    <td style="text-transform: uppercase;">
                        <span class="font-bold">{{ $log->keterangan }}</span><br>
                        <span style="font-size: 8px; color: #555;">Log: {{ $log->petugas_input }}</span>
                    </td>
                    <td class="text-right" style="color: green;">
                        {{ $log->jenis == 'MASUK' ? 'Rp ' . number_format($log->jumlah, 2, ',', '.') : '-' }}
                    </td>
                    <td class="text-right" style="color: red;">
                        {{ $log->jenis == 'KELUAR' ? 'Rp ' . number_format($log->jumlah, 2, ',', '.') : '-' }}
                    </td>
                </tr>
            @endforeach
            <tr style="background-color: #f9f9f9; font-weight: bold;">
                <td colspan="2" class="text-right uppercase">TOTAL REKAPITULASI ARUS DANA:</td>
                <td class="text-right" style="color: green;">Rp {{ number_format($totalMasuk, 2, ',', '.') }}</td>
                <td class="text-right" style="color: red;">Rp {{ number_format($totalKeluar, 2, ',', '.') }}</td>
            </tr>
            <tr style="background-color: #f2f2f2; font-weight: bold; font-size: 12px;">
                <td colspan="2" class="text-right uppercase">SALDO AKHIR KOMANDAN :</td>
                <td colspan="2" class="text-center" style="color: #4f46e5;"> Rp {{ number_format($saldo, 2, ',', '.') }}
                </td>
            </tr>
        </tbody>
    </table>

    <div class="footer-sign">
        <p>Surabaya, {{ date('d F Y') }}</p>
        <p>A.n. Komandan Detasemen Intelijen</p>
        <p>Petugas Logistik Keuangan,</p>
        <div class="space-sign"></div>
        <p class="font-bold" style="text-decoration: underline;">{{ Auth::user()->name }}</p>
        <p>{{ strtoupper(Auth::user()->pangkat) }} NRP. {{ Auth::user()->nrp ?? '--------' }}</p>
    </div>

</body>
</html>