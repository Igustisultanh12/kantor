<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Perintah Jaga - {{ $spJaga->nomor_sprin ?: 'SINDEN' }}</title>
    <style>
        @page {
            margin: 1cm 1.5cm 1cm 1.5cm;
            size: 215mm 330mm portrait; /* Format F4 / Folio Resmi Kedinasan TNI */
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.35;
            color: #000000;
            margin: 0;
            padding: 0;
        }

        .page-break {
            page-break-before: always;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .table-bordered {
            border-collapse: collapse;
            width: 100%;
            font-size: 10pt;
        }

        .table-bordered th, .table-bordered td {
            border: 1px solid #000000;
            padding: 5px 6px;
            text-align: left;
        }

        .table-bordered th {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
        }

        .text-center { text-align: center; }
        .text-justify { text-align: justify; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }

        .kop-surat {
            width: 340px;
            text-align: center;
            margin-bottom: 5px;
        }
        .kop-surat .line1 { font-size: 10.5pt; font-weight: normal; }
        .kop-surat .line2 { font-size: 10.5pt; font-weight: normal; }
        .kop-surat .divider { border-bottom: 1.5px solid #000000; margin-top: 2px; }

        .logo-tni {
            display: block;
            margin: 0 auto;
            width: 70px;
            height: auto;
        }

        .ttd-box {
            width: 310px;
            margin-left: auto;
            text-align: center;
            font-size: 10.5pt;
        }
    </style>
</head>
<body>

    <!-- ========================================================================= -->
    <!-- HALAMAN 1: SURAT PERINTAH (SP JAGA UTAMA)                                -->
    <!-- ========================================================================= -->
    <div style="width: 100%;">
        
        <!-- Kop Surat Kiri -->
        <div class="kop-surat">
            <div class="line1">KOMANDO DAERAH TNI ANGKATAN LAUT V</div>
            <div class="line2">DETASEMEN INTELIJEN</div>
            <div class="divider"></div>
        </div>

        <!-- Logo Hitam Putih TNI AL Jalesveva Jayamahe -->
        <div style="text-align: center; margin-top: 5px; margin-bottom: 5px;">
            @php
                $bwLogoPath = public_path('images/logo_tni_al_bw.png');
                $colorLogoPath = public_path('images/logo_tni_al.png');
                $logoSrc = file_exists($bwLogoPath) ? $bwLogoPath : (file_exists($colorLogoPath) ? $colorLogoPath : '');
            @endphp
            @if($logoSrc)
                <img src="{{ $logoSrc }}" class="logo-tni" alt="Logo TNI AL" />
            @endif
        </div>

        <!-- Judul Surat Perintah -->
        <div style="text-align: center; margin-bottom: 15px;">
            <div style="font-size: 11.5pt; font-weight: normal; text-decoration: underline; text-transform: uppercase;">
                SURAT PERINTAH
            </div>
            <div style="font-size: 11pt; margin-top: 2px;">
                Nomor: {{ $spJaga->nomor_sprin ?: 'Sprin/ ' . ($spJaga->nomor_urut ?? '   ') . ' /' . ($spJaga->bulan_romawi ?? 'VI') . '/' . ($spJaga->tahun ?? '2026') }}
            </div>
        </div>

        <!-- Konsideran: Menimbang & Dasar -->
        <table style="width: 100%; margin-bottom: 10px; font-size: 11pt;">
            <tr>
                <td style="width: 100px; vertical-align: top;">Menimbang</td>
                <td style="width: 15px; vertical-align: top; text-align: center;">:</td>
                <td style="vertical-align: top; text-align: justify;">
                    bahwa dalam rangka melaksanakan tugas jaga Siaga Sintel Kepada Perwira Sintel/Den Intel/Pam Denma Kodaeral V, maka perlu dikeluarkan surat perintah.
                </td>
            </tr>
            <tr>
                <td style="vertical-align: top; padding-top: 8px;">Dasar</td>
                <td style="vertical-align: top; text-align: center; padding-top: 8px;">:</td>
                <td style="vertical-align: top; text-align: justify; padding-top: 8px;">
                    Prosedur Tetap Dankodaeral V Nomor Protap/01/II/2015 tanggal 18 Maret 2015 tentang Pengamanan Basis TNI AL dan Obyek Vital di Ujung Surabaya.
                </td>
            </tr>
        </table>

        <!-- DIPERINTAHKAN -->
        <div style="text-align: center; font-weight: normal; margin: 12px 0; text-transform: uppercase; letter-spacing: 1px;">
            DIPERINTAHKAN
        </div>

        <!-- Diktum: Kepada & Untuk (Font 11pt) -->
        <table style="width: 100%; margin-bottom: 15px; font-size: 11pt;">
            <tr>
                <td style="width: 100px; vertical-align: top;">Kepada</td>
                <td style="width: 15px; vertical-align: top; text-align: center;">:</td>
                <td style="vertical-align: top; text-align: justify;">
                    {{ $danunitPangkat ?? 'Kapten Laut (P)' }} {{ $danunitNama ?? 'Indra Gunawan' }} {{ $danunitNrp ?? 'NRP 19739/P' }}, {{ $danunitJabatan ?? 'Dan Unit 1 Lid Den Intel Kodaeral V' }}, beserta Dua Puluh Enam (26) orang sesuai lampiran.
                </td>
            </tr>
            <tr>
                <td style="vertical-align: top; padding-top: 10px;">Untuk</td>
                <td style="vertical-align: top; text-align: center; padding-top: 10px;">:</td>
                <td style="vertical-align: top; padding-top: 10px;">
                    <table style="width: 100%; font-size: 11pt;">
                        <tr>
                            <td style="width: 25px; vertical-align: top;">1.</td>
                            <td style="text-align: justify;">
                                Seterimanya surat perintah ini disamping tugas dan tanggungjawab yang ada, ditunjuk menjabat sebagai Perwira Siaga dan Anggota Siaga Sintel Kodaeral V sesuai dengan Jadwal terlampir.
                            </td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top; padding-top: 6px;">2.</td>
                            <td style="text-align: justify; padding-top: 6px;">
                                @php
                                    $tmtMulai = $spJaga->tmt_mulai ? \Carbon\Carbon::parse($spJaga->tmt_mulai)->isoFormat('DD') : '01';
                                    $tmtSelesai = $spJaga->tmt_selesai ? \Carbon\Carbon::parse($spJaga->tmt_selesai)->isoFormat('D MMMM Y') : '30 September 2026';
                                @endphp
                                Pelaksanaan TMT {{ $tmtMulai }} s.d. {{ $tmtSelesai }}
                            </td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top; padding-top: 6px;">3.</td>
                            <td style="text-align: justify; padding-top: 6px;">
                                Melaksanakan perintah ini dengan penuh rasa tanggung jawab, serta melaporkan hasil pelaksanaannya kepada Asintel Dankodaeral V dan Danden Intel Kodaeral V.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div style="margin-bottom: 15px; font-size: 11pt;">Selesai.</div>

        <!-- Kolom Tanda Tangan & Tanggal Keluar -->
        <table style="width: 100%; margin-top: 5px;">
            <tr>
                <td style="width: 45%; vertical-align: bottom;">
                    <!-- Tembusan -->
                    <div style="font-size: 10pt;">
                        <u>Tembusan:</u>
                        <ol style="margin: 2px 0 0 0; padding-left: 18px;">
                            <li>Asintel Dankodaeral V</li>
                            <li>Dandenma Kodaeral V</li>
                        </ol>
                    </div>
                </td>
                <td style="width: 55%; vertical-align: top;">
                    <div class="ttd-box">
                        <table style="width: 100%; text-align: left; margin-bottom: 4px;">
                            <tr>
                                <td style="width: 95px;">Dikeluarkan di</td>
                                <td style="width: 10px;">:</td>
                                <td>Surabaya</td>
                            </tr>
                            <tr>
                                <td>pada tanggal</td>
                                <td>:</td>
                                <td>{{ $spJaga->tanggal_surat ? \Carbon\Carbon::parse($spJaga->tanggal_surat)->isoFormat('D MMMM Y') : '30 Agustus 2026' }}</td>
                            </tr>
                        </table>
                        <div style="border-bottom: 1px solid #000; margin-bottom: 5px;"></div>

                        <div style="text-align: center;">
                            <div>a.n. Komandan Detasemen Intelijen Kodaeral V,</div>
                            <div style="margin-bottom: 5px;">Pasiops,</div>

                            <!-- Area TTE QR Code vs TTD Basah -->
                            @if($spJaga->ttd_type === 'tte' && $spJaga->status === 'published' && !empty($qr_base64))
                                <div style="margin: 6px auto; text-align: center;">
                                    <img src="{{ $qr_base64 }}" style="width: 75px; height: 75px; display: inline-block;" alt="QR TTE" />
                                    <div style="font-size: 7.5pt; font-family: monospace; color: #333; margin-top: 2px;">
                                        TTE VALID: {{ $spJaga->verification_code }}
                                    </div>
                                </div>
                            @else
                                <div style="height: 65px;"></div>
                            @endif

                            <div style="font-weight: normal; text-decoration: none;">
                                {{ $pasiopsNama ?? ($spJaga->penandatangan_nama ?: 'Roni Sumantri') }}
                            </div>
                            <div style="font-size: 10pt;">
                                {{ $pasiopsPangkatNrp ?? ($spJaga->penandatangan_pangkat_nrp ?: 'Mayor Laut (P) NRP 17456/P') }}
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

    </div>

    <!-- ========================================================================= -->
    <!-- HALAMAN 2: LAMPIRAN 1 - TABEL PERWIRA JAGA SINTEL                         -->
    <!-- ========================================================================= -->
    <div class="page-break" style="width: 100%;">
        
        <!-- Header Lampiran -->
        <table style="width: 100%; margin-bottom: 15px;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <div class="kop-surat" style="width: 320px;">
                        <div class="line1">KOMANDO DAERAH TNI ANGKATAN LAUT V</div>
                        <div class="line2">DETASEMEN INTELIJEN</div>
                        <div class="divider"></div>
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top; text-align: right; font-size: 10pt;">
                    <div style="width: 290px; margin-left: auto; text-align: left;">
                        <table style="width: 100%;">
                            <tr>
                                <td style="vertical-align: top; width: 70px;">Lampiran</td>
                                <td style="vertical-align: top; width: 10px;">:</td>
                                <td>Sprin Danden Intel Kodaeral V</td>
                            </tr>
                            <tr>
                                <td style="vertical-align: top;">Nomor</td>
                                <td style="vertical-align: top;">:</td>
                                <td>{{ $spJaga->nomor_sprin ?: 'Sprin/ ' . ($spJaga->nomor_urut ?? '   ') . ' /' . ($spJaga->bulan_romawi ?? 'VI') . '/' . ($spJaga->tahun ?? '2026') }}</td>
                            </tr>
                            <tr>
                                <td style="vertical-align: top;">Tanggal</td>
                                <td style="vertical-align: top;">:</td>
                                <td>{{ $spJaga->tanggal_surat ? \Carbon\Carbon::parse($spJaga->tanggal_surat)->isoFormat('D MMMM Y') : '30 Agustus 2026' }}</td>
                            </tr>
                        </table>
                        <div style="border-bottom: 1px solid #000; margin-top: 3px;"></div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Judul Lampiran 1 -->
        @php
            $namaBulanTahun = strtoupper(\Carbon\Carbon::createFromDate($spJaga->tahun, $spJaga->bulan, 1)->isoFormat('MMMM Y'));
        @endphp
        <div style="text-align: center; margin-bottom: 15px;">
            <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase;">
                DAFTAR NAMA PERWIRA JAGA SINTEL KODAERAL V
            </div>
            <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase;">
                JL. STASIUN BENTENG UJUNG SURABAYA BULAN {{ $namaBulanTahun }}
            </div>
        </div>

        <!-- Tabel Perwira Jaga (Width 100% Fixed, Font 10pt) -->
        <table class="table-bordered" style="width: 100%; table-layout: fixed; margin-bottom: 12px; font-size: 10pt;">
            <thead>
                <tr>
                    <th style="width: 5%;">NO.</th>
                    <th style="width: 25%;">N A M A</th>
                    <th style="width: 20%;">PANGKAT, KORPS</th>
                    <th style="width: 15%;">NRP</th>
                    <th colspan="7" style="width: 35%;">{{ $namaBulanTahun }}<br>TANGGAL</th>
                </tr>
            </thead>
            <tbody>
                @forelse($spJaga->perwiras as $idx => $perwira)
                <tr>
                    <td class="text-center" style="padding: 5px 2px;">{{ $idx + 1 }}.</td>
                    <td style="padding: 5px 6px;">{{ $perwira->nama }}</td>
                    <td style="padding: 5px 6px;">{{ $perwira->pangkat_korps }}</td>
                    <td class="text-center" style="padding: 5px 4px;">{{ $perwira->nrp }}</td>
                    <td class="text-center" style="width: 5%; padding: 5px 2px;">{{ $perwira->tgl_1 ?: '-' }}</td>
                    <td class="text-center" style="width: 5%; padding: 5px 2px;">{{ $perwira->tgl_2 ?: '-' }}</td>
                    <td class="text-center" style="width: 5%; padding: 5px 2px;">{{ $perwira->tgl_3 ?: '-' }}</td>
                    <td class="text-center" style="width: 5%; padding: 5px 2px;">{{ $perwira->tgl_4 ?: '-' }}</td>
                    <td class="text-center" style="width: 5%; padding: 5px 2px;">{{ $perwira->tgl_5 ?: '-' }}</td>
                    <td class="text-center" style="width: 5%; padding: 5px 2px;">{{ $perwira->tgl_6 ?: '-' }}</td>
                    <td class="text-center" style="width: 5%; padding: 5px 2px;">{{ $perwira->tgl_7 ?: '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="text-center">Belum ada daftar perwira jaga.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Catatan SOP Jaga -->
        <div style="font-size: 10pt; margin-bottom: 12px;">
            <div style="font-weight: normal; margin-bottom: 2px;">Catatan:</div>
            <table style="width: 100%;">
                <tr>
                    <td style="width: 20px; vertical-align: top;">1.</td>
                    <td style="text-align: justify;">Perwira Siaga Intel bertanggung jawab atas keamanan dan kebersihan kantor Sintel dan Kantor Tim Intel.</td>
                </tr>
                <tr>
                    <td style="vertical-align: top; padding-top: 3px;">2.</td>
                    <td style="text-align: justify; padding-top: 3px;">Apabila anggota Jaga yang melaksanakan ijin/cuti, sehari sebelumnya laporan kepada Wadantim/Pasops.</td>
                </tr>
                <tr>
                    <td style="vertical-align: top; padding-top: 3px;">3.</td>
                    <td style="text-align: justify; padding-top: 3px;">Apabila ada pertukaran/ganti jaga harap melaporkan ke Wadantim/Pasops.</td>
                </tr>
            </table>
        </div>

        <!-- Kolom Tanda Tangan Lampiran 1 -->
        <table style="width: 100%; margin-top: 5px;">
            <tr>
                <td style="width: 50%;"></td>
                <td style="width: 50%;">
                    <div class="ttd-box">
                        <div>a.n. Komandan Detasemen Intelijen Kodaeral V,</div>
                        <div style="margin-bottom: 5px;">Pasiops,</div>

                        @if($spJaga->ttd_type === 'tte' && $spJaga->status === 'published' && !empty($qr_base64))
                            <div style="margin: 6px auto; text-align: center;">
                                <img src="{{ $qr_base64 }}" style="width: 70px; height: 70px; display: inline-block;" alt="QR TTE" />
                            </div>
                        @else
                            <div style="height: 60px;"></div>
                        @endif

                        <div style="font-weight: normal; text-decoration: none;">
                            {{ $pasiopsNama ?? ($spJaga->penandatangan_nama ?: 'Roni Sumantri') }}
                        </div>
                        <div style="font-size: 10pt;">
                            {{ $pasiopsPangkatNrp ?? ($spJaga->penandatangan_pangkat_nrp ?: 'Mayor Laut (P) NRP 17456/P') }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>

    </div>

    <!-- ========================================================================= -->
    <!-- HALAMAN 3: LAMPIRAN 2 - TABEL ANGGOTA JAGA SINTEL (KELOMPOK DIVISI)       -->
    <!-- ========================================================================= -->
    <div class="page-break" style="width: 100%;">
        
        <!-- Kop Kiri -->
        <div class="kop-surat" style="width: 320px; margin-bottom: 10px;">
            <div class="line1">KOMANDO DAERAH TNI ANGKATAN LAUT V</div>
            <div class="line2">DETASEMEN INTELIJEN</div>
            <div class="divider"></div>
        </div>

        <!-- Judul Lampiran 2 -->
        <div style="text-align: center; margin-bottom: 12px;">
            <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase;">
                DAFTAR NAMA ANGGOTA JAGA SINTEL KODAERAL V
            </div>
            <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase;">
                JL. STASIUN BENTENG UJUNG SURABAYA BULAN {{ $namaBulanTahun }}
            </div>
        </div>

        <!-- Tabel Anggota Jaga Divisi (Width 100% Fixed, Font 10pt) -->
        <table class="table-bordered" style="width: 100%; table-layout: fixed; margin-bottom: 12px; font-size: 10pt;">
            <thead>
                <tr>
                    <th style="width: 4%;">NO</th>
                    <th style="width: 21%;">TANGGAL</th>
                    <th style="width: 25%;">NAMA</th>
                    <th style="width: 17%;">PANGKAT/<br>KORPS</th>
                    <th style="width: 22%;">NRP/NIP</th>
                    <th style="width: 11%;">KET</th>
                </tr>
                <tr style="background-color: #fafafa; font-size: 9pt;">
                    <th style="font-weight: normal; padding: 2px;">1</th>
                    <th style="font-weight: normal; padding: 2px;">2</th>
                    <th style="font-weight: normal; padding: 2px;">3</th>
                    <th style="font-weight: normal; padding: 2px;">4</th>
                    <th style="font-weight: normal; padding: 2px;">5</th>
                    <th style="font-weight: normal; padding: 2px;">6</th>
                </tr>
            </thead>
            <tbody>
                @forelse($spJaga->anggotas as $idx => $divisi)
                    @php
                        $items = is_array($divisi->anggota_items) ? $divisi->anggota_items : json_decode($divisi->anggota_items, true) ?? [];
                        $rawTgl = $divisi->tanggal_list_text;
                        $tglClean = preg_replace('/[A-Za-z]+\s+[0-9]{4}/i', $namaBulanTahun, $rawTgl);
                        if (!str_contains($tglClean, $namaBulanTahun)) {
                            $tglClean .= ' ' . $namaBulanTahun;
                        }
                    @endphp
                    <tr>
                        <td class="text-center" style="vertical-align: middle; font-weight: normal; padding: 6px 2px;">{{ $idx + 1 }}.</td>
                        <td class="text-center" style="vertical-align: middle; font-weight: bold; padding: 6px 4px; line-height: 1.4;">
                            {{ $tglClean }}
                        </td>
                        <td style="vertical-align: middle; padding: 6px 6px; line-height: 1.5;">
                            @foreach($items as $item)
                                <div style="font-weight: normal; white-space: nowrap;">{{ $item['nama'] ?? '-' }}</div>
                            @endforeach
                        </td>
                        <td style="vertical-align: middle; padding: 6px 6px; line-height: 1.5;">
                            @foreach($items as $item)
                                <div style="white-space: nowrap;">{{ $item['pangkat_korps'] ?? '-' }}</div>
                            @endforeach
                        </td>
                        <td class="text-center" style="vertical-align: middle; padding: 6px 2px; line-height: 1.5; font-size: 8.5pt;">
                            @foreach($items as $item)
                                <div style="white-space: nowrap; letter-spacing: -0.2px;">{{ $item['nrp_nip'] ?? '-' }}</div>
                            @endforeach
                        </td>
                        <td class="text-center" style="vertical-align: middle; padding: 6px 2px; line-height: 1.5;">
                            @foreach($items as $item)
                                <div style="font-weight: normal; white-space: nowrap;">{{ $item['role_jaga'] ?? 'ANGGOTA' }}</div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">Belum ada data regu anggota jaga.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Catatan SOP Jaga -->
        <div style="font-size: 10pt; margin-bottom: 12px;">
            <div style="font-weight: normal; margin-bottom: 2px;">Catatan:</div>
            <table style="width: 100%;">
                <tr>
                    <td style="width: 20px; vertical-align: top;">1.</td>
                    <td style="text-align: justify;">Anggota Siaga Sintel bertanggung jawab atas keamanan dan kebersihan kantor Sintel dan kantor Tim intel.</td>
                </tr>
                <tr>
                    <td style="vertical-align: top; padding-top: 3px;">2.</td>
                    <td style="text-align: justify; padding-top: 3px;">Apabila anggota Divisi Jaga yang melaksanakan ijin / cuti, sehari sebelumnya laporan kepada Wadantim / Pasops.</td>
                </tr>
                <tr>
                    <td style="vertical-align: top; padding-top: 3px;">3.</td>
                    <td style="text-align: justify; padding-top: 3px;">Apabila ada pertukaran/ganti jaga harap melaporkan ke Wadantim/Pasops.</td>
                </tr>
            </table>
        </div>

        <!-- Kolom Tanda Tangan Lampiran 2 -->
        <table style="width: 100%; margin-top: 5px;">
            <tr>
                <td style="width: 50%;"></td>
                <td style="width: 50%;">
                    <div class="ttd-box">
                        <table style="width: 100%; text-align: left; margin-bottom: 4px;">
                            <tr>
                                <td style="width: 95px;">Dikeluarkan di</td>
                                <td style="width: 10px;">:</td>
                                <td>Surabaya</td>
                            </tr>
                            <tr>
                                <td>pada tanggal</td>
                                <td>:</td>
                                <td>{{ $spJaga->tanggal_surat ? \Carbon\Carbon::parse($spJaga->tanggal_surat)->isoFormat('D MMMM Y') : '30 Agustus 2026' }}</td>
                            </tr>
                        </table>
                        <div style="border-bottom: 1px solid #000; margin-bottom: 5px;"></div>

                        <div>a.n. Komandan Detasemen Intelijen Kodaeral V,</div>
                        <div style="margin-bottom: 5px;">Pasiops,</div>

                        @if($spJaga->ttd_type === 'tte' && $spJaga->status === 'published' && !empty($qr_base64))
                            <div style="margin: 6px auto; text-align: center;">
                                <img src="{{ $qr_base64 }}" style="width: 70px; height: 70px; display: inline-block;" alt="QR TTE" />
                            </div>
                        @else
                            <div style="height: 60px;"></div>
                        @endif

                        <div style="font-weight: normal; text-decoration: none;">
                            {{ $pasiopsNama ?? ($spJaga->penandatangan_nama ?: 'Roni Sumantri') }}
                        </div>
                        <div style="font-size: 10pt;">
                            {{ $pasiopsPangkatNrp ?? ($spJaga->penandatangan_pangkat_nrp ?: 'Mayor Laut (P) NRP 17456/P') }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>

    </div>

</body>
</html>