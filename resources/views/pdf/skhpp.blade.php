<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SKHPP {{ $skhpp->nama }}</title>
    <style>
        /*
         * Tata letak mengikuti naskah Word acuan SKHPP Denintel (diukur dari hasil cetaknya):
         * kertas F4 (215 x 330 mm), margin atas 1,43 cm / kanan 1,59 cm / bawah 2,54 cm / kiri 2,25 cm,
         * Arial 12pt (kop & judul 11pt), jarak baris tunggal. Semua ukuran di bawah dalam pt.
         */
        @page {
            margin: 40.5pt 45pt 72pt 63.8pt;
            size: 215mm 330mm portrait;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12pt;
            line-height: 1.15;
            color: #000;
            margin: 0;
            padding: 0;
            font-weight: normal;
        }

        .page-break { page-break-before: always; }

        table { border-collapse: collapse; font-weight: normal; }
        td { padding: 0; vertical-align: top; }

        .kop { width: 233pt; text-align: center; font-size: 11pt; white-space: nowrap; border-bottom: 1px solid #000; padding-bottom: 1pt; }

        /* Poin bernomor: nomor di margin kiri, teks mulai 36pt; baris lanjutan kembali ke margin kiri (seperti naskah acuan). */
        .poin { text-align: justify; margin-bottom: 14pt; }
        .poin .no { display: inline-block; width: 36pt; vertical-align: bottom; }
        /* Sub-poin a/b/c pada "Dasar": huruf di 35pt, teks mulai 72pt; baris lanjutan kembali sejajar huruf. */
        .sub { margin-left: 35pt; text-align: justify; margin-bottom: 14pt; }
        .sub .no { display: inline-block; width: 37pt; vertical-align: bottom; }

        /* Data personel: huruf di 71pt, label di 106pt, titik dua di 232pt. */
        /* Lebar tabel dikunci (lebar isi 500pt − 71pt) supaya isi yang panjang turun baris, bukan terpotong di tepi kanan. */
        .data { margin-left: 71pt; margin-bottom: 14pt; width: 429pt; }
        .data td.i { width: 261pt; word-wrap: break-word; }
        .data td.h { width: 35pt; }
        .data td.l { width: 126pt; }
        .data td.t { width: 7pt; }

        .table-members th, .table-members td {
            border: 1px solid #000;
            padding: 6px 8px;
            font-weight: normal;
        }
    </style>
</head>
<body>
    @php
        $jumlahPengikut = $skhpp->jumlahPengikut();

        // Ruang nomor urut di "R/ … /SKHPP/…": spasi tetap (&nbsp;) supaya tidak turun baris di dompdf;
        // kosong (nomor ditulis tangan / belum bernomor) = ruang lebar untuk pulpen.
        $spasi = fn (int $n) => str_repeat('&nbsp;', $n);
        $ruangNomor = filled($skhpp->nomor_urut) ? $spasi(4) . e($skhpp->nomor_urut) . $spasi(4) : $spasi(16);

        // Baris data personel (label => isi); sumber yang sama dipakai naskah DOCX.
        $barisData = $skhpp->barisDataNaskah($tanggal_lahir_indo);
    @endphp

    <!-- HALAMAN 1: DOKUMEN SKHPP UTAMA -->
    <div class="kop">
        <div>KOMANDO DAERAH TNI ANGKATAN LAUT V</div>
        <div>DETASEMEN INTELIJEN</div>
    </div>

    <!-- Judul Surat -->
    <div style="text-align: center; margin-top: 34pt; margin-bottom: 18pt;">
        <div style="font-size: 11pt;">SURAT KETERANGAN HASIL PENELITIAN PERSONEL</div>
        @if($skhpp->kategori_personel === 'perusahaan')
        <div style="font-size: 11pt;">MITRA KERJA TNI ANGKATAN LAUT</div>
        @endif
        <div>
            @if($skhpp->nomor_skhpp)
                Nomor : {{ $skhpp->nomor_skhpp }}
            @else
                Nomor : R/{!! $ruangNomor !!}/SKHPP/{{ $skhpp->bulan_romawi ?? 'VIII' }}/{{ $skhpp->tahun ?? '2026' }}
            @endif
        </div>
    </div>

    <!-- Poin 1: Dasar (c memuat surat pengantar + kalimat pengantar data personel) -->
    <div class="poin" style="margin-bottom: 7pt;"><span class="no">1.</span>Dasar :</div>
    <div class="sub"><span class="no">a.</span>Peraturan Kasal Nomor Perkasal/50/XII/2007 tanggal 04 Desember 2007 tentang Petunjuk Pelaksanaan Penelitian Personel di lingkungan TNI AL;</div>
    <div class="sub"><span class="no">b.</span>Prosedur Tetap Nomor Protap/01/VIII/2024 tanggal 26 Agustus 2024 tentang Pengurusan Surat Keterangan Hasil Penelitian Personel (SKHPP) di Lingkungan Tentara Nasional Indonesia Angkatan Laut; dan</div>
    <div class="sub"><span class="no">c.</span>{{ rtrim($skhpp->surat_pengantar, " \t\n\r.,;") }}, dengan ini menerangkan bahwa hasil pemeriksaan terhadap :</div>

    <!-- Data personel -->
    <table class="data">
        @foreach($barisData as $labelBaris => $isiBaris)
        <tr>
            <td class="h">{{ chr(97 + $loop->index) }}.</td>
            <td class="l">{{ $labelBaris }}</td>
            <td class="t">:</td>
            <td class="i">{{ $isiBaris }}</td>
        </tr>
        @endforeach
    </table>

    <!-- Poin 2: Hasil (hanya "Memenuhi Syarat" yang tebal) -->
    <div class="poin"><span class="no">2.</span>Hasil Penelitian Personel <span style="font-weight: bold;">Memenuhi Syarat</span></div>

    <!-- Poin 3: Peruntukan -->
    <div class="poin"><span class="no">3.</span>SKHPP ini diberikan {{ rtrim($skhpp->peruntukan, " \t\n\r.") }}.</div>

    <!-- Poin 4: Penutup -->
    <div class="poin" style="margin-bottom: 36pt;"><span class="no">4.</span>Apabila kemudian terdapat kekeliruan, SKHPP ini akan dicabut.</div>

    <!-- Pas foto 4x6 (kiri) & blok tanda tangan Komandan (kanan; sengaja melewati margin kanan seperti naskah acuan) -->
    <table style="width: 525pt; margin-right: -25pt;">
        <tr>
            <td style="text-align: right; padding-right: 6pt; font-size: 0; white-space: nowrap; vertical-align: bottom;">
                @if($foto1_base64)
                    <img src="{{ $foto1_base64 }}" style="width: 4cm; height: 6cm; object-fit: cover; border: none; display: inline-block; vertical-align: top;" />
                @endif
                @if($skhpp->is_pernikahan && $foto2_base64)
                    <img src="{{ $foto2_base64 }}" style="width: 4cm; height: 6cm; object-fit: cover; border: none; display: inline-block; vertical-align: top;" />
                @endif
            </td>
            <td style="width: 251pt; vertical-align: bottom;"><div style="width: 251pt;">
                <div style="margin-left: 8pt; width: 229pt;">
                    <div>Dikeluarkan di Surabaya</div>
                    <table style="width: 229pt; border-bottom: 1px solid #000;">
                        <tr>
                            <td style="width: 70pt; white-space: nowrap; padding-bottom: 1pt;">pada tanggal</td>
                            {{-- Hari di tengah ruang kosong, bulan & tahun menempel kanan (seperti naskah acuan) --}}
                            <td style="text-align: center; padding-bottom: 1pt;">{{ \Illuminate\Support\Str::before($tanggal_skhpp_indo, ' ') }}</td>
                            <td style="width: 1%; text-align: right; white-space: nowrap; padding-bottom: 1pt;">{{ \Illuminate\Support\Str::after($tanggal_skhpp_indo, ' ') }}</td>
                        </tr>
                    </table>
                </div>

                <div style="text-align: center; white-space: nowrap; margin-top: 5pt;">Komandan Detasemen Intelijen Kodaeral V,</div>

                <!-- TTD QR Code -->
                <div style="height: 66pt; text-align: center;">
                    @if($qr_base64 && $skhpp->status === 'approved')
                        <img src="{{ $qr_base64 }}" style="width: 60pt; height: 60pt; margin: 3pt auto 0 auto; display: block;" />
                    @endif
                </div>

                <div style="text-align: center; white-space: nowrap;">Hari Bagio Wijayanto, M.Tr.Opsla.</div>
                {{-- Blok TTD rata bawah: baris pangkat/NRP sejajar dengan tepi bawah pas foto. --}}
                <div style="text-align: center; white-space: nowrap;">Kolonel Laut (E) NRP 16085/P</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Kepada -->
    <div style="margin-top: 6pt;">
        <div style="margin-bottom: 6pt;">Kepada :</div>
        <div style="width: 146pt; white-space: nowrap; border-bottom: 1px solid #000; padding-bottom: 1pt;">Yth. Asintel Dankodaeral V</div>
    </div>

    <!-- HALAMAN 2: LAMPIRAN SKHPP (JIKA ANGGOTA TERSEDIA) -->
    @if($jumlahPengikut > 0)
    <div class="page-break"></div>

    <div style="width: 100%;">

        <!-- Header Lampiran (Kop Kiri & Detail Lampiran Kanan) -->
        <table style="width: 100%; margin-bottom: 60pt;">
            <tr>
                <td style="width: 50%;">
                    <div class="kop">
                        <div>KOMANDO DAERAH TNI ANGKATAN LAUT V</div>
                        <div>DETASEMEN INTELIJEN</div>
                    </div>
                </td>
                <td style="width: 50%;">
                    {{-- Blok lampiran rata kiri; isi Nomor & Tanggal sejajar; garis bawah selebar blok (seperti naskah acuan). --}}
                    <table style="margin-left: auto; border-bottom: 1px solid #000;">
                        <tr><td colspan="2" style="white-space: nowrap;">Lampiran SKHPP Denintel Kodaeral V</td></tr>
                        <tr>
                            <td style="width: 66pt;">Nomor</td>
                            <td style="white-space: nowrap;">
                                @if($skhpp->nomor_skhpp)
                                    {{ $skhpp->nomor_skhpp }}
                                @else
                                    R/{!! $ruangNomor !!}/SKHPP/{{ $skhpp->bulan_romawi ?? 'VIII' }}/{{ $skhpp->tahun ?? '2026' }}
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td style="padding-bottom: 1pt;">Tanggal</td>
                            <td style="white-space: nowrap; padding-bottom: 1pt;">{{ $tanggal_skhpp_indo }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Judul Lampiran -->
        <div style="text-align: center; text-transform: uppercase; margin-bottom: 24pt;">{{ $skhpp->judulLampiran() }}</div>

        <!-- Tabel Anggota Pengikut -->
        <table class="table-members" style="width: 100%; font-size: 11pt; margin-bottom: 25px;">
            <thead>
                <tr style="text-transform: uppercase; text-align: center;">
                    <th style="width: 35px;">NO</th>
                    <th>NAMA</th>
                    <th style="width: 180px;">{{ $skhpp->labelIdentitasLampiran() }}</th>
                    <th>JABATAN</th>
                </tr>
            </thead>
            <tbody>
                @foreach($skhpp->members as $idx => $m)
                <tr>
                    <td style="text-align: center;">{{ $idx + 1 }}.</td>
                    <td>{{ $m->nama }}</td>
                    <td style="text-align: center;">{{ $m->pangkat_nrp_nik }}</td>
                    <td>{{ $m->jabatan }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- TTD Komandan Lampiran -->
        <table style="width: 525pt; margin-right: -25pt;">
            <tr>
                <td></td>
                <td style="width: 251pt;"><div style="width: 251pt; text-align: center;">
                    <div style="white-space: nowrap;">Komandan Detasemen Intelijen Kodaeral V,</div>
                    <div style="height: 66pt; text-align: center;">
                        @if($qr_base64 && $skhpp->status === 'approved')
                            <img src="{{ $qr_base64 }}" style="width: 60pt; height: 60pt; margin: 3pt auto 0 auto; display: block;" />
                        @endif
                    </div>
                    <div style="white-space: nowrap;">Hari Bagio Wijayanto, M.Tr.Opsla.</div>
                    <div style="white-space: nowrap;">Kolonel Laut (E) NRP 16085/P</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    @endif

</body>
</html>
