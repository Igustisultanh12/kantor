<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SKHPP {{ $skhpp->nama }}</title>
    <style>
        @page {
            margin: 1cm 1.5cm;
            size: A4 portrait;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.3;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .page-break {
            page-break-before: always;
        }

        table {
            border-collapse: collapse;
        }

        .table-data td {
            padding: 2px 0;
        }

        .table-members th, .table-members td {
            border: 1px solid #000;
            padding: 5px 8px;
        }
    </style>
</head>
<body>

    <!-- HALAMAN 1: DOKUMEN SKHPP UTAMA -->
    <div style="width: 100%;">
        
        <!-- Kop Surat (Rata Kiri dengan Garis Kop) -->
        <div style="margin-bottom: 20px;">
            <div style="font-weight: bold; text-transform: uppercase; font-size: 11pt;">KOMANDO DAERAH TNI ANGKATAN LAUT V</div>
            <div style="font-weight: bold; text-transform: uppercase; font-size: 11pt; padding-left: 25px;">DETASEMEN INTELIJEN</div>
            <div style="border-bottom: 1px solid #000; width: 310px; margin-top: 2px;"></div>
        </div>

        <!-- Judul Surat -->
        <div style="text-align: center; margin-top: 15px; margin-bottom: 20px;">
            <div style="font-weight: bold; text-transform: uppercase; font-size: 12pt;">
                SURAT KETERANGAN HASIL PENELITIAN PERSONEL(SKHPP)
            </div>
            @if($skhpp->kategori_personel === 'perusahaan')
            <div style="font-weight: bold; text-transform: uppercase; font-size: 12pt;">
                MITRA KERJA TNI ANGKATAN LAUT
            </div>
            @endif
            <div style="font-weight: bold; font-size: 11pt; margin-top: 3px;">
                Nomor : R/ &nbsp;&nbsp;{{ $skhpp->nomor_urut ?? '      ' }}&nbsp;&nbsp; /SKHPP/{{ $skhpp->bulan_romawi ?? 'VIII' }}/{{ $skhpp->tahun ?? '2026' }}
            </div>
        </div>

        <!-- Point 1: Dasar -->
        <table style="width: 100%; margin-bottom: 8px;">
            <tr>
                <td style="width: 25px; vertical-align: top; font-weight: bold;">1.</td>
                <td style="vertical-align: top;">
                    <div style="font-weight: bold;">Dasar :</div>
                    <table style="width: 100%; margin-top: 3px;">
                        <tr>
                            <td style="width: 20px; vertical-align: top;">a.</td>
                            <td style="text-align: justify;">Peraturan Kasal Nomor Perkasal/50/XII/2007 tanggal 04 Desember 2007 tentang Petunjuk Pelaksanaan Penelitian Personel di lingkungan TNI AL;</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">b.</td>
                            <td style="text-align: justify;">Prosedur Tetap Nomor Protap/01/VIII/2024 tanggal 26 Agustus 2024 tentang Pengurusan Surat Keterangan Hasil Penelitian Personel (SKHPP) di Lingkungan Tentara Nasional Indonesia Angkatan Laut; dan</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">c.</td>
                            <td style="text-align: justify;">{{ $skhpp->surat_pengantar }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Point 2: Data Personel -->
        <table style="width: 100%; margin-bottom: 8px;">
            <tr>
                <td style="width: 25px; vertical-align: top; font-weight: bold;">2.</td>
                <td style="vertical-align: top;">
                    <div>Dengan ini menerangkan bahwa hasil penelitian terhadap :</div>
                    @if($skhpp->pangkat_korps_nrp)
                    <table class="table-data" style="width: 100%; margin-top: 4px;">
                        <tr>
                            <td style="width: 20px; vertical-align: top;">a.</td>
                            <td style="width: 140px; vertical-align: top;">Nama</td>
                            <td style="width: 10px; vertical-align: top;">:</td>
                            <td style="vertical-align: top; font-weight: bold;">{{ $skhpp->nama }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">b.</td>
                            <td style="vertical-align: top;">Pangkat/Korp/NRP</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->pangkat_korps_nrp }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">c.</td>
                            <td style="vertical-align: top;">Jabatan</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->jabatan_pekerjaan }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">d.</td>
                            <td style="vertical-align: top;">Tempat/Tgl. lahir</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->tempat_lahir }}, {{ $tanggal_lahir_indo }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">e.</td>
                            <td style="vertical-align: top;">Jenis kelamin</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->jenis_kelamin }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">f.</td>
                            <td style="vertical-align: top;">Agama</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->agama }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">g.</td>
                            <td style="vertical-align: top;">Alamat rumah</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->alamat }}</td>
                        </tr>
                    </table>
                    @else
                    <table class="table-data" style="width: 100%; margin-top: 4px;">
                        <tr>
                            <td style="width: 20px; vertical-align: top;">a.</td>
                            <td style="width: 140px; vertical-align: top;">Nama</td>
                            <td style="width: 10px; vertical-align: top;">:</td>
                            <td style="vertical-align: top; font-weight: bold;">{{ $skhpp->nama }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">b.</td>
                            <td style="vertical-align: top;">NIK</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->nik ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">c.</td>
                            <td style="vertical-align: top;">Tempat/Tgl. lahir</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->tempat_lahir }}, {{ $tanggal_lahir_indo }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">d.</td>
                            <td style="vertical-align: top;">Jenis kelamin</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->jenis_kelamin }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">e.</td>
                            <td style="vertical-align: top;">Agama</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->agama }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">f.</td>
                            <td style="vertical-align: top;">Pekerjaan</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->jabatan_pekerjaan }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">g.</td>
                            <td style="vertical-align: top;">Alamat rumah</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->alamat }}</td>
                        </tr>
                    </table>
                    @endif
                </td>
            </tr>
        </table>

        <!-- Point 3: Hasil -->
        <table style="width: 100%; margin-bottom: 8px;">
            <tr>
                <td style="width: 25px; vertical-align: top; font-weight: bold;">3.</td>
                <td style="vertical-align: top;">
                    Hasil Penelitian Personel <span style="font-weight: bold;">Memenuhi Syarat</span>
                </td>
            </tr>
        </table>

        <!-- Point 4: Peruntukan -->
        <table style="width: 100%; margin-bottom: 8px;">
            <tr>
                <td style="width: 25px; vertical-align: top; font-weight: bold;">4.</td>
                <td style="vertical-align: top; text-align: justify;">
                    SKHPP ini diberikan {{ $skhpp->peruntukan }}.
                </td>
            </tr>
        </table>

        <!-- Point 5: Penutup -->
        <table style="width: 100%; margin-bottom: 15px;">
            <tr>
                <td style="width: 25px; vertical-align: top; font-weight: bold;">5.</td>
                <td style="vertical-align: top; text-align: justify;">
                    Apabila kemudian terdapat kekeliruan, SKHPP ini akan dicabut dan diadakan pembetulan seperlunya.
                </td>
            </tr>
        </table>

        <!-- Pas Foto & Signature Block Komandan -->
        <table style="width: 100%; margin-top: 15px;">
            <tr>
                <td style="width: 45%; vertical-align: top;">
                    @if($foto1_base64)
                        <img src="{{ $foto1_base64 }}" style="width: 3.2cm; height: 4.2cm; object-fit: cover; border: 1px solid #000;" />
                    @endif
                    @if($skhpp->is_pernikahan && $foto2_base64)
                        <img src="{{ $foto2_base64 }}" style="width: 3.2cm; height: 4.2cm; object-fit: cover; border: 1px solid #000; margin-left: 5px;" />
                    @endif
                </td>
                <td style="width: 55%; vertical-align: top; text-align: left;">
                    <div>Dikeluarkan di Surabaya</div>
                    <div style="border-bottom: 1px solid #000; width: 230px; padding-bottom: 2px;">
                        pada tanggal &nbsp;&nbsp;&nbsp;&nbsp;{{ $tanggal_skhpp_indo }}
                    </div>
                    <div style="font-weight: bold; margin-top: 5px; line-height: 1.2;">
                        Komandan Detasemen Intelijen Kodaeral V,
                    </div>

                    <div style="height: 75px; margin-top: 5px; margin-bottom: 5px;">
                        @if($qr_base64 && $skhpp->status === 'approved')
                            <img src="{{ $qr_base64 }}" style="width: 65px; height: 65px; border: 1px solid #ccc; padding: 2px;" />
                        @endif
                    </div>

                    <div style="font-weight: bold; text-decoration: underline;">Hari Bagio Wijayanto, M.Tr.Opsla.</div>
                    <div style="font-weight: bold;">Kolonel Laut (E) NRP 16085/P</div>
                </td>
            </tr>
        </table>

        <!-- Footer Kepada -->
        <div style="margin-top: 25px;">
            <div>Kepada :</div>
            <div style="font-weight: bold; border-bottom: 1px solid #000; width: 180px; padding-bottom: 2px;">Yth. Asintel Dankodaeral V</div>
        </div>
    </div>

    <!-- HALAMAN 2: LAMPIRAN SKHPP (JIKA ANGGOTA TERSEDIA) -->
    @if($skhpp->has_pengikut && count($skhpp->members) > 0)
    <div class="page-break"></div>

    <div style="width: 100%; padding-top: 10px;">
        
        <!-- Header Lampiran (Kop Kiri & Detail Lampiran Kanan) -->
        <table style="width: 100%; margin-bottom: 20px;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <div style="font-weight: bold; text-transform: uppercase; font-size: 11pt;">KOMANDO DAERAH TNI ANGKATAN LAUT V</div>
                    <div style="font-weight: bold; text-transform: uppercase; font-size: 11pt; padding-left: 25px;">DETASEMEN INTELIJEN</div>
                    <div style="border-bottom: 1px solid #000; width: 310px; margin-top: 2px;"></div>
                </td>
                <td style="width: 50%; vertical-align: top; text-align: right; font-size: 10pt; line-height: 1.3;">
                    <div>Lampiran SKHPP Den Intel Kodaeral V</div>
                    <div style="border-bottom: 1px solid #000; display: inline-block; padding-bottom: 2px;">
                        Nomor SKHPP/ &nbsp;&nbsp;{{ $skhpp->nomor_urut ?? '   ' }}&nbsp;&nbsp; /{{ $skhpp->bulan_romawi ?? 'VII' }}/{{ $skhpp->tahun ?? '2026' }}<br>
                        Tanggal &nbsp;&nbsp;&nbsp;&nbsp;{{ $tanggal_skhpp_indo }}
                    </div>
                </td>
            </tr>
        </table>

        <!-- Judul Lampiran -->
        <div style="text-align: center; font-weight: bold; text-transform: uppercase; margin-bottom: 15px; font-size: 12pt;">
            DAFTAR NAMA-NAMA ANGGOTA PENGIKUT
        </div>

        <!-- Tabel Anggota Pengikut -->
        <table class="table-members" style="width: 100%; font-size: 10pt; margin-bottom: 25px;">
            <thead>
                <tr style="background-color: #f8f8f8; text-transform: uppercase; text-align: center; font-weight: bold;">
                    <th style="width: 35px;">NO</th>
                    <th>NAMA</th>
                    <th style="width: 180px;">NIK / NRP / NIP</th>
                    <th>JABATAN</th>
                </tr>
            </thead>
            <tbody>
                @foreach($skhpp->members as $idx => $m)
                <tr>
                    <td style="text-align: center; font-weight: bold;">{{ $idx + 1 }}.</td>
                    <td style="font-weight: bold;">{{ $m->nama }}</td>
                    <td style="text-align: center;">{{ $m->pangkat_nrp_nik }}</td>
                    <td>{{ $m->jabatan }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- TTD Komandan Lampiran -->
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%;"></td>
                <td style="width: 50%; text-align: left;">
                    <div style="font-weight: bold; line-height: 1.2;">
                        Komandan Detasemen Intelijen Kodaeral V,
                    </div>
                    <div style="height: 75px; margin-top: 5px; margin-bottom: 5px;">
                        @if($qr_base64 && $skhpp->status === 'approved')
                            <img src="{{ $qr_base64 }}" style="width: 65px; height: 65px; border: 1px solid #ccc; padding: 2px;" />
                        @endif
                    </div>
                    <div style="font-weight: bold; text-decoration: underline;">Hari Bagio Wijayanto, M.Tr.Opsla.</div>
                    <div style="font-weight: bold;">Kolonel Laut (E) NRP 16085/P</div>
                </td>
            </tr>
        </table>
    </div>
    @endif

</body>
</html>
