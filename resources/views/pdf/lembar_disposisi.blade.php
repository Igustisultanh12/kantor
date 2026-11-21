<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $telegram ? 'Lembar Disposisi Telegram' : 'Lembar Disposisi' }} {{ $agenda }}</title>
<style>
    @page { size: A5 portrait; margin: 9mm 10mm 8mm 10mm; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.6pt; color: #000; margin: 0; }
    .kop { display: inline-block; text-align: center; font-size: 8.4pt; line-height: 1.25; }
    .kop .garis { border-bottom: 0.9pt solid #000; margin-top: 1.5pt; }
    .agenda-tg { float: right; font-size: 7pt; text-align: right; line-height: 1.3; }
    .judul { text-align: center; font-weight: bold; font-size: 9.6pt; letter-spacing: 0.4pt; margin: 9pt 0 8pt; }
    table.data { width: 100%; border-collapse: collapse; margin-bottom: 7pt; }
    table.data td { padding: 1.6pt 0; vertical-align: top; font-size: 8.4pt; }
    table.data td.lbl { width: 27%; }
    table.data td.tk { width: 3%; }
    table.data td.isi { border-bottom: 0.5pt dotted #555; }
    table.data td.lbl2 { width: 17%; padding-left: 6pt; white-space: nowrap; }
    table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.grid th { border: 0.9pt solid #000; font-size: 8.4pt; padding: 3pt 2pt; }
    table.grid td.kol { border: 0.9pt solid #000; vertical-align: top; padding: 4pt 4pt; height: 118mm; }
    .baris { height: 7.4mm; line-height: 1.2; }
    .yth { height: 20mm; }
    .tujuan { font-size: 8.4pt; }
    .dipilih { font-weight: bold; }
    .cek { font-family: 'DejaVu Sans', sans-serif; display: inline-block; width: 9pt; }
    .paraf { font-size: 6.4pt; line-height: 1.15; }
    .c { text-align: center; font-weight: bold; font-size: 8.4pt; }
    .c1 { margin-bottom: 4pt; }
    .kecil { font-size: 6.8pt; color: #333; }
    .instruksi { margin: 2pt 0 0 0; padding-left: 10pt; }
    .instruksi li { margin-bottom: 1pt; }
    .catatan { margin-top: 3pt; font-style: italic; }
    .isi-disposisi { height: 90mm; overflow: hidden; }
    table.ttd { width: 96%; margin: 0 auto; border-collapse: collapse; }
    table.ttd td { border: 0.8pt solid #000; text-align: center; font-size: 7.6pt; padding: 2.5pt 2pt; }
    table.ttd td.ruang { height: 10mm; vertical-align: middle; font-size: 6.4pt; }
    .laporan { font-size: 6.4pt; color: #222; }
</style>
</head>
<body>
    <div>
        @if ($telegram && $agenda)
            <div class="agenda-tg">No. Agenda<br><strong>{{ $agenda }}</strong></div>
        @endif
        <div class="kop">
            <div>{{ $kop1 }}</div>
            <div>{{ $kop2 }}</div>
            <div class="garis"></div>
        </div>
    </div>

    <div class="judul">{{ $telegram ? 'LEMBAR DISPOSISI TELEGRAM' : 'LEMBAR DISPOSISI' }}</div>

    <table class="data">
        @if ($telegram)
            <tr>
                <td class="lbl">DARI</td><td class="tk">:</td><td class="isi">{{ $dari }}</td>
                <td class="lbl2">TGL TERIMA :</td><td class="isi" style="width: 24%">{{ $tglTerima }}</td>
            </tr>
            <tr><td class="lbl">NOMOR</td><td class="tk">:</td><td class="isi" colspan="3">{{ $nomor }}</td></tr>
            <tr><td class="lbl">TWU</td><td class="tk">:</td><td class="isi" colspan="3">{{ $twu }}</td></tr>
        @else
            <tr>
                <td class="lbl">AGENDA DEN INTEL</td><td class="tk">:</td><td class="isi">{{ $agenda }}</td>
                <td class="lbl2">TGL TERIMA :</td><td class="isi" style="width: 24%">{{ $tglTerima }}</td>
            </tr>
            <tr><td class="lbl">SURAT DARI</td><td class="tk">:</td><td class="isi" colspan="3">{{ $dari }}</td></tr>
            <tr>
                <td class="lbl">NOMOR SURAT</td><td class="tk">:</td><td class="isi">{{ $nomor }}</td>
                <td class="lbl2">TGL SURAT :</td><td class="isi" style="width: 24%">{{ $tglSurat }}</td>
            </tr>
            <tr><td class="lbl">PERIHAL</td><td class="tk">:</td><td class="isi" colspan="3">{{ \Illuminate\Support\Str::limit($perihal, 170) }}</td></tr>
        @endif
    </table>

    <table class="grid">
        <thead>
            <tr><th style="width: 32%">KEPADA</th><th style="width: 52%">DISPOSISI / CATATAN</th><th style="width: 16%">PARAF</th></tr>
        </thead>
        <tbody>
            <tr>
                <td class="kol tujuan" style="width: 32%">
                    <div class="yth">{{ $yth }}</div>
                    @foreach ($rows as $r)
                        <div class="baris {{ $r['dipilih'] ? 'dipilih' : '' }}"><span class="cek">{{ $r['dipilih'] ? '✓' : '' }}</span>{{ $r['label'] }}</div>
                    @endforeach
                </td>
                <td class="kol" style="width: 52%">
                    <div class="isi-disposisi">
                    <div class="c">C - 1</div>
                    <div class="c1">
                        Mohon Disposisi
                        <div style="text-align: right; padding-right: 8pt;">{{ $pemohon }}</div>
                        <div class="kecil">{{ $tglCatat }}</div>
                    </div>

                    <div class="c">C - 2</div>
                    @if (count($instruksi))
                        <div style="margin-top: 2pt;">{{ implode(' · ', $instruksi) }}</div>
                    @endif
                    @if ($catatan)<div class="catatan">"{{ \Illuminate\Support\Str::limit($catatan, 300) }}"</div>@endif
                    <div class="kecil" style="margin-top: 3pt;">
                        @if ($urgensi)Sifat: {{ $urgensi }}@endif
                        @if ($batas) · Batas: {{ $batas }}@endif
                    </div>

                    </div>
                    <table class="ttd">
                        <tr><td colspan="2">{{ $jabatanTtd }}</td></tr>
                        <tr><td class="ruang" style="width: 28%"></td><td class="ruang">Disposisi elektronik<br>{{ $tglDisposisi }}</td></tr>
                        <tr><td colspan="2">{{ $namaKomandan }}</td></tr>
                    </table>
                </td>
                <td class="kol paraf" style="width: 16%">
                    <div class="yth"></div>
                    @foreach ($rows as $r)
                        <div class="baris">@if ($r['paraf'])<span class="cek">✓</span>{{ $r['paraf']['nama'] }}<br>{{ $r['paraf']['waktu'] }}@endif</div>
                    @endforeach
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>
