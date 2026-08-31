<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Perintah Jaga - {{ $spJaga->nomor_sprin ?: 'SINDEN' }}</title>
    <style>
        @page {
            margin: 0.8cm 1.5cm 1cm 1.5cm;
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
            padding: 4px 6px;
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
            width: 320px;
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
        <table style="width: 100%; margin-bottom: 10px;">
            <tr>
                <td style="width: 110px; vertical-align: top;">Menimbang</td>
                <td style="width: 20px; vertical-align: top; text-align: center;">:</td>
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

        <!-- Diktum: Kepada & Untuk -->
        <table style="width: 100%; margin-bottom: 15px;">
            <tr>
                <td style="width: 110px; vertical-align: top;">Kepada</td>
                <td style="width: 20px; vertical-align: top; text-align: center;">:</td>
                <td style="vertical-align: top; text-align: justify;">
                    {{ $spJaga->perwira_tertua_nama ?: 'Kapten Laut (P) Indra Gunawan T.Z' }} {{ $spJaga->perwira_tertua_pangkat_nrp ?: 'NRP 19739/P' }}, {{ $spJaga->perwira_tertua_jabatan ?: 'Dan Unit 1 Lid Den Intel Kodaeral V' }}, beserta {{ $spJaga->total_personel_terbilang ?: 'Dua puluh enam' }} ({{ $spJaga->total_personel_count ?: '26' }}) orang sesuai lampiran.
                </td>
            </tr>
            <tr>
                <td style="vertical-align: top; padding-top: 10px;">Untuk</td>
                <td style="vertical-align: top; text-align: center; padding-top: 10px;">:</td>
                <td style="vertical-align: top; padding-top: 10px;">
                    <table style="width: 100%;">
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

        <div style="margin-bottom: 15px;">Selesai.</div>

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
                                {{ $spJaga->penandatangan_nama ?: 'Roni Sumantri' }}
                            </div>
                            <div style="font-size: 10pt;">
                                {{ $spJaga->penandatangan_pangkat_nrp ?: 'Mayor Laut (P) NRP 17456/P' }}
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

        <!-- Tabel Perwira Jaga -->
        <table class="table-bordered" style="margin-bottom: 15px;">
            <thead>
                <tr>
                    <th style="width: 32px;">NO.</th>
                    <th style="width: 170px;">N A M A</th>
                    <th style="width: 130px;">PANGKAT, KORPS</th>
                    <th style="width: 80px;">NRP</th>
                    <th colspan="7">{{ $namaBulanTahun }}<br>TANGGAL</th>
                </tr>
            </thead>
            <tbody>
                @forelse($spJaga->perwiras as $idx => $perwira)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}.</td>
                    <td>{{ $perwira->nama }}</td>
                    <td>{{ $perwira->pangkat_korps }}</td>
                    <td class="text-center">{{ $perwira->nrp }}</td>
                    <td class="text-center" style="width: 30px;">{{ $perwira->tgl_1 ?: '-' }}</td>
                    <td class="text-center" style="width: 30px;">{{ $perwira->tgl_2 ?: '-' }}</td>
                    <td class="text-center" style="width: 30px;">{{ $perwira->tgl_3 ?: '-' }}</td>
                    <td class="text-center" style="width: 30px;">{{ $perwira->tgl_4 ?: '-' }}</td>
                    <td class="text-center" style="width: 30px;">{{ $perwira->tgl_5 ?: '-' }}</td>
                    <td class="text-center" style="width: 30px;">{{ $perwira->tgl_6 ?: '-' }}</td>
                    <td class="text-center" style="width: 30px;">{{ $perwira->tgl_7 ?: '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="text-center">Belum ada daftar perwira jaga.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Catatan SOP Jaga -->
        <div style="font-size: 10pt; margin-bottom: 15px;">
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
        <table style="width: 100%; margin-top: 10px;">
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
                            {{ $spJaga->penandatangan_nama ?: 'Roni Sumantri' }}
                        </div>
                        <div style="font-size: 10pt;">
                            {{ $spJaga->penandatangan_pangkat_nrp ?: 'Mayor Laut (P) NRP 17456/P' }}
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

        <!-- Tabel Anggota Jaga Divisi -->
        <table class="table-bordered" style="margin-bottom: 12px;">
            <thead>
                <tr>
                    <th style="width: 32px;">NO</th>
                    <th style="width: 140px;">TANGGAL</th>
                    <th style="width: 180px;">N A M A</th>
                    <th style="width: 120px;">PANGKAT/KORPS</th>
                    <th style="width: 110px;">NRP/NIP</th>
                    <th style="width: 70px;">KET</th>
                </tr>
            </thead>
            <tbody>
                @forelse($spJaga->anggotas as $idx => $divisi)
                    @php
                        $items = is_array($divisi->anggota_items) ? $divisi->anggota_items : json_decode($divisi->anggota_items, true) ?? [];
                        $rowCount = count($items) > 0 ? count($items) : 1;
                        
                        // Pastikan teks bulan pada tanggal disesuaikan dengan bulan periode SP Jaga aktif
                        $rawTgl = $divisi->tanggal_list_text;
                        $tglClean = preg_replace('/[A-Za-z]+\s+[0-9]{4}/i', $namaBulanTahun, $rawTgl);
                        if (!str_contains($tglClean, $namaBulanTahun)) {
                            $tglClean .= ' ' . $namaBulanTahun;
                        }
                    @endphp
                    @foreach($items as $itemIdx => $item)
                    <tr>
                        @if($itemIdx === 0)
                            <td class="text-center" rowspan="{{ $rowCount }}" style="vertical-align: middle;">{{ $idx + 1 }}.</td>
                            <td class="text-center" rowspan="{{ $rowCount }}" style="vertical-align: middle; font-weight: bold;">
                                {{ $tglClean }}
                            </td>
                        @endif
                        <td>{{ $item['nama'] ?? '-' }}</td>
                        <td>{{ $item['pangkat_korps'] ?? '-' }}</td>
                        <td class="text-center">{{ $item['nrp_nip'] ?? '-' }}</td>
                        <td class="text-center" style="font-weight: bold;">{{ $item['role_jaga'] ?? 'ANGGOTA' }}</td>
                    </tr>
                    @endforeach
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
                            {{ $spJaga->penandatangan_nama ?: 'Roni Sumantri' }}
                        </div>
                        <div style="font-size: 10pt;">
                            {{ $spJaga->penandatangan_pangkat_nrp ?: 'Mayor Laut (P) NRP 17456/P' }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>

    </div>

</body>
</html>