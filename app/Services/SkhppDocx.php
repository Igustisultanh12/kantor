<?php

namespace App\Services;

use App\Models\Skhpp;
use RuntimeException;
use ZipArchive;

/**
 * Naskah SKHPP dalam format Word (.docx), dibangun langsung dari WordprocessingML (tanpa dependensi tambahan).
 * Tata letak meniru resources/views/pdf/skhpp.blade.php: kertas F4 215 x 330 mm,
 * margin atas 1,43 / kanan 1,59 / bawah 2,54 / kiri 2,25 cm, Arial 12pt (kop & judul 11pt).
 * Satuan: twip (1 pt = 20 twip), EMU untuk gambar (1 cm = 360000 EMU).
 */
class SkhppDocx
{
    private const LEBAR_ISI = 10012; // 12189 − 1276 − 901

    /** @var array<int, array{name:string, bytes:string}> */
    private array $media = [];

    public function build(Skhpp $skhpp, array $d): string
    {
        $this->media = [];
        $body = $this->halamanUtama($skhpp, $d);
        if ($skhpp->jumlahPengikut() > 0) {
            $body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>' . $this->halamanLampiran($skhpp, $d);
        }

        $sect = '<w:sectPr><w:pgSz w:w="12189" w:h="18709"/>'
            . '<w:pgMar w:top="811" w:right="901" w:bottom="1440" w:left="1276" w:header="709" w:footer="709" w:gutter="0"/></w:sectPr>';

        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
            . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<w:body>' . $body . $sect . '</w:body></w:document>';

        return $this->zip($document);
    }

    // ------------------------------------------------------------------ halaman

    private function halamanUtama(Skhpp $s, array $d): string
    {
        $x = $this->kop();

        // Judul
        $x .= $this->p($this->r('SURAT KETERANGAN HASIL PENELITIAN PERSONEL', 22), ['jc' => 'center', 'before' => 680]);
        if ($s->kategori_personel === 'perusahaan') {
            $x .= $this->p($this->r('MITRA KERJA TNI ANGKATAN LAUT', 22), ['jc' => 'center']);
        }
        $x .= $this->p($this->r('Nomor : ' . $this->nomor($s)), ['jc' => 'center', 'after' => 360]);

        // 1. Dasar
        $x .= $this->poin('1.', $this->r('Dasar :'), 140);
        $x .= $this->sub('a.', 'Peraturan Kasal Nomor Perkasal/50/XII/2007 tanggal 04 Desember 2007 tentang Petunjuk Pelaksanaan Penelitian Personel di lingkungan TNI AL;');
        $x .= $this->sub('b.', 'Prosedur Tetap Nomor Protap/01/VIII/2024 tanggal 26 Agustus 2024 tentang Pengurusan Surat Keterangan Hasil Penelitian Personel (SKHPP) di Lingkungan Tentara Nasional Indonesia Angkatan Laut; dan');
        $x .= $this->sub('c.', rtrim((string) $s->surat_pengantar, " \t\n\r.,;") . ', dengan ini menerangkan bahwa hasil pemeriksaan terhadap :');

        // Data personel: huruf 35pt, label 126pt, titik dua 7pt, isi 261pt; tabel menjorok 71pt.
        $rows = '';
        $i = 0;
        foreach ($s->barisDataNaskah($d['tanggal_lahir_indo']) as $label => $isi) {
            $rows .= '<w:tr>'
                . $this->tc(700, $this->p($this->r(chr(97 + $i++) . '.')))
                . $this->tc(2520, $this->p($this->r($label)))
                . $this->tc(140, $this->p($this->r(':')))
                . $this->tc(5220, $this->p($this->r((string) $isi)))
                . '</w:tr>';
        }
        $x .= $this->tbl([700, 2520, 140, 5220], $rows, ['ind' => 1420]);
        $x .= $this->p('', ['exact' => 280]); // jarak 14pt setelah tabel

        $x .= $this->poin('2.', $this->r('Hasil Penelitian Personel ') . $this->r('Memenuhi Syarat', 24, true));
        $x .= $this->poin('3.', $this->r('SKHPP ini diberikan ' . rtrim((string) $s->peruntukan, " \t\n\r.") . '.'));
        $x .= $this->poin('4.', $this->r('Apabila kemudian terdapat kekeliruan, SKHPP ini akan dicabut.'), 720);

        // Pas foto 4x6 (kiri, rata kanan) & blok tanda tangan (kanan)
        $foto = '';
        foreach ([$d['foto1'] ?? null, $d['foto2'] ?? null] as $img) {
            if ($img) {
                $foto .= $this->gambar($img, 1440000, 2160000, true); // 4 x 6 cm, dipotong seperti object-fit: cover
            }
        }
        $kiri = $this->p($foto, ['jc' => 'right']);

        [$hari, $sisa] = array_pad(explode(' ', $d['tanggal_skhpp_indo'], 2), 2, '');
        $tanggal = $this->tbl([1400, 1280, 1900],
            '<w:tr>'
            . $this->tc(1400, $this->p($this->r('pada tanggal')), 'bottom')
            . $this->tc(1280, $this->p($this->r($hari), ['jc' => 'center']), 'bottom')
            . $this->tc(1900, $this->p($this->r($sisa), ['jc' => 'right']), 'bottom')
            . '</w:tr>', ['ind' => 160]);

        $kanan = $this->p($this->r('Dikeluarkan di Surabaya'), ['ind' => 160])
            . $tanggal
            . $this->p($this->r('Komandan Detasemen Intelijen Kodaeral V,'), ['jc' => 'center', 'before' => 100])
            . $this->ttd($d)
            . $this->p($this->r('Hari Bagio Wijayanto, M.Tr.Opsla.'), ['jc' => 'center'])
            . $this->p($this->r('Kolonel Laut (E) NRP 16085/P'), ['jc' => 'center']);

        // Rata bawah: baris pangkat/NRP Komandan sejajar dengan tepi bawah pas foto (seperti PDF).
        $x .= $this->tbl([4992, 5020], '<w:tr>' . $this->tc(4992, $kiri, null, null, 'bottom') . $this->tc(5020, $kanan, null, null, 'bottom') . '</w:tr>');

        // Kepada
        $x .= $this->p($this->r('Kepada :'), ['before' => 120, 'after' => 120]);
        $x .= $this->tbl([2920], '<w:tr>' . $this->tc(2920, $this->p($this->r('Yth. Asintel Dankodaeral V')), 'bottom') . '</w:tr>');
        $x .= $this->p('');

        return $x;
    }

    private function halamanLampiran(Skhpp $s, array $d): string
    {
        // Blok lampiran di kanan: judul satu baris, lalu Nomor & Tanggal sejajar; garis bawah selebar blok.
        $keterangan = $this->tbl([1320, 3480],
            '<w:tr>' . $this->tc(4800, $this->p($this->r('Lampiran SKHPP Denintel Kodaeral V')), null, null, null, 2) . '</w:tr>'
            . '<w:tr>' . $this->tc(1320, $this->p($this->r('Nomor'))) . $this->tc(3480, $this->p($this->r($this->nomor($s)))) . '</w:tr>'
            . '<w:tr>' . $this->tc(1320, $this->p($this->r('Tanggal')), 'bottom') . $this->tc(3480, $this->p($this->r($d['tanggal_skhpp_indo'])), 'bottom') . '</w:tr>',
            ['jc' => 'right']);

        $x = $this->tbl([5006, 5006], '<w:tr>' . $this->tc(5006, $this->kop()) . $this->tc(5006, $keterangan) . '</w:tr>');
        $x .= $this->p($this->r($s->judulLampiran()), ['jc' => 'center', 'before' => 1200, 'after' => 480]);

        $w = [600, 3500, 2700, 3212];
        $sel = fn ($i, $teks, $jc = 'left') => $this->tc($w[$i], $this->p($this->r($teks, 22), ['jc' => $jc]), 'all');

        $rows = '<w:tr><w:trPr><w:tblHeader/></w:trPr>'
            . $sel(0, 'NO', 'center') . $sel(1, 'NAMA', 'center')
            . $sel(2, mb_strtoupper($s->labelIdentitasLampiran()), 'center') . $sel(3, 'JABATAN', 'center') . '</w:tr>';
        foreach ($s->members as $idx => $m) {
            $rows .= '<w:tr>'
                . $sel(0, ($idx + 1) . '.', 'center')
                . $sel(1, (string) $m->nama)
                . $sel(2, (string) $m->pangkat_nrp_nik, 'center')
                . $sel(3, (string) $m->jabatan)
                . '</w:tr>';
        }
        $x .= $this->tbl($w, $rows, ['cellMar' => true]);
        $x .= $this->p('', ['after' => 500]);

        $kanan = $this->p($this->r('Komandan Detasemen Intelijen Kodaeral V,'), ['jc' => 'center'])
            . $this->ttd($d)
            . $this->p($this->r('Hari Bagio Wijayanto, M.Tr.Opsla.'), ['jc' => 'center'])
            . $this->p($this->r('Kolonel Laut (E) NRP 16085/P'), ['jc' => 'center']);
        $x .= $this->tbl([4992, 5020], '<w:tr>' . $this->tc(4992, $this->p('')) . $this->tc(5020, $kanan) . '</w:tr>');

        return $x;
    }

    /** Nomor naskah; tanpa nomor jadi "R/ … /SKHPP/…" dengan ruang kosong untuk ditulis tangan (sama dengan PDF). */
    private function nomor(Skhpp $s): string
    {
        if ($s->nomor_skhpp) {
            return (string) $s->nomor_skhpp;
        }
        $nbsp = "\u{00A0}";
        $ruang = filled($s->nomor_urut) ? str_repeat($nbsp, 4) . $s->nomor_urut . str_repeat($nbsp, 4) : str_repeat($nbsp, 16);
        return 'R/' . $ruang . '/SKHPP/' . ($s->bulan_romawi ?? 'VIII') . '/' . ($s->tahun ?? '2026');
    }

    /** Kop surat: dua baris 11pt rata tengah, bergaris bawah, lebar 233pt. */
    private function kop(): string
    {
        return $this->tbl([4660], '<w:tr>' . $this->tc(4660,
            $this->p($this->r('KOMANDO DAERAH TNI ANGKATAN LAUT V', 22), ['jc' => 'center'])
            . $this->p($this->r('DETASEMEN INTELIJEN', 22), ['jc' => 'center']), 'bottom') . '</w:tr>');
    }

    /** Ruang tanda tangan setinggi 66pt; berisi QR TTE bila SKHPP sudah terbit dengan TTE. */
    private function ttd(array $d): string
    {
        if (!empty($d['qr'])) {
            return $this->p($this->gambar($d['qr'], 762000, 762000), ['jc' => 'center', 'before' => 60]); // 60pt
        }
        return $this->p('', ['exact' => 1320]);
    }

    // ------------------------------------------------------------------ primitif

    private function poin(string $no, string $runs, int $after = 280): string
    {
        // Nomor di margin kiri, teks mulai 36pt; baris lanjutan kembali ke margin kiri.
        return $this->p($this->r($no) . '<w:r><w:tab/></w:r>' . $runs, ['jc' => 'both', 'after' => $after, 'tabs' => [720]]);
    }

    private function sub(string $huruf, string $teks): string
    {
        // Huruf di 35pt, teks mulai 72pt; baris lanjutan sejajar huruf.
        return $this->p($this->r($huruf) . '<w:r><w:tab/></w:r>' . $this->r($teks),
            ['jc' => 'both', 'after' => 280, 'ind' => 700, 'tabs' => [1440]]);
    }

    private function r(string $teks, int $size = 24, bool $bold = false): string
    {
        $rPr = '<w:rPr>' . ($bold ? '<w:b/>' : '') . '<w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/></w:rPr>';
        return '<w:r>' . $rPr . '<w:t xml:space="preserve">' . $this->esc($teks) . '</w:t></w:r>';
    }

    private function p(string $runs, array $o = []): string
    {
        $pPr = '';
        if (!empty($o['tabs'])) {
            $pPr .= '<w:tabs>' . implode('', array_map(fn ($t) => '<w:tab w:val="left" w:pos="' . $t . '"/>', $o['tabs'])) . '</w:tabs>';
        }
        $spacing = '<w:spacing w:before="' . ($o['before'] ?? 0) . '" w:after="' . ($o['after'] ?? 0) . '"';
        $spacing .= isset($o['exact']) ? ' w:line="' . $o['exact'] . '" w:lineRule="exact"/>' : ' w:line="240" w:lineRule="auto"/>';
        $pPr .= $spacing;
        if (isset($o['ind'])) {
            $pPr .= '<w:ind w:left="' . $o['ind'] . '"/>';
        }
        if (isset($o['jc'])) {
            $pPr .= '<w:jc w:val="' . $o['jc'] . '"/>';
        }
        $size = $o['size'] ?? 24;
        $pPr .= '<w:rPr><w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/></w:rPr>';

        return '<w:p><w:pPr>' . $pPr . '</w:pPr>' . $runs . '</w:p>';
    }

    /** Tabel lebar tetap tanpa garis (garis diatur per sel). */
    private function tbl(array $grid, string $rows, array $o = []): string
    {
        $mar = !empty($o['cellMar'])
            ? '<w:tblCellMar><w:top w:w="90" w:type="dxa"/><w:left w:w="120" w:type="dxa"/><w:bottom w:w="90" w:type="dxa"/><w:right w:w="120" w:type="dxa"/></w:tblCellMar>'
            : '<w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="0" w:type="dxa"/><w:bottom w:w="20" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar>';
        $tblPr = '<w:tblPr><w:tblW w:w="' . array_sum($grid) . '" w:type="dxa"/>'
            . (isset($o['jc']) ? '<w:jc w:val="' . $o['jc'] . '"/>' : '')
            . '<w:tblInd w:w="' . ($o['ind'] ?? 0) . '" w:type="dxa"/>'
            . '<w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders>'
            . '<w:tblLayout w:type="fixed"/>' . $mar . '</w:tblPr>';
        $tblGrid = '<w:tblGrid>' . implode('', array_map(fn ($g) => '<w:gridCol w:w="' . $g . '"/>', $grid)) . '</w:tblGrid>';

        return '<w:tbl>' . $tblPr . $tblGrid . $rows . '</w:tbl>';
    }

    /** Sel tabel. $garis: null | 'bottom' | 'all'. Isi sel wajib diakhiri paragraf. */
    private function tc(int $w, string $isi, ?string $garis = null, ?string $fill = null, ?string $vAlign = null, int $span = 1): string
    {
        $b = fn ($sisi) => '<w:' . $sisi . ' w:val="single" w:sz="6" w:space="0" w:color="000000"/>';
        $borders = match ($garis) {
            'bottom' => '<w:tcBorders>' . $b('bottom') . '</w:tcBorders>',
            'all' => '<w:tcBorders>' . $b('top') . $b('left') . $b('bottom') . $b('right') . '</w:tcBorders>',
            default => '',
        };
        $shd = $fill ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>' : '';
        if (!str_ends_with($isi, '</w:p>')) {
            $isi .= $this->p('');
        }

        return '<w:tc><w:tcPr><w:tcW w:w="' . $w . '" w:type="dxa"/>'
            . ($span > 1 ? '<w:gridSpan w:val="' . $span . '"/>' : '')
            . $borders . $shd
            . ($vAlign ? '<w:vAlign w:val="' . $vAlign . '"/>' : '')
            . '</w:tcPr>' . $isi . '</w:tc>';
    }

    /** Gambar inline; $cover = potong agar memenuhi bingkai tanpa gepeng (seperti object-fit: cover). */
    private function gambar(array $img, int $cx, int $cy, bool $cover = false): string
    {
        $ext = $img['ext'] === 'jpg' ? 'jpeg' : $img['ext'];
        if (!in_array($ext, ['png', 'jpeg'], true)) {
            return '';
        }
        $n = count($this->media) + 1;
        $this->media[] = ['name' => "image{$n}.{$ext}", 'bytes' => $img['bytes']];

        $crop = '';
        if ($cover && ($size = @getimagesizefromstring($img['bytes'])) && $size[0] > 0 && $size[1] > 0) {
            $rasioGambar = $size[0] / $size[1];
            $rasioBingkai = $cx / $cy;
            if ($rasioGambar > $rasioBingkai) { // terlalu lebar: potong kiri-kanan
                $sisi = (int) round((1 - $rasioBingkai / $rasioGambar) / 2 * 100000);
                $crop = '<a:srcRect l="' . $sisi . '" r="' . $sisi . '"/>';
            } elseif ($rasioGambar < $rasioBingkai) { // terlalu tinggi: potong atas-bawah
                $sisi = (int) round((1 - $rasioGambar / $rasioBingkai) / 2 * 100000);
                $crop = '<a:srcRect t="' . $sisi . '" b="' . $sisi . '"/>';
            }
        }

        $id = 100 + $n;
        return '<w:r><w:rPr><w:sz w:val="24"/></w:rPr><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
            . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/><wp:docPr id="' . $id . '" name="Gambar ' . $n . '"/>'
            . '<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic>'
            . '<pic:nvPicPr><pic:cNvPr id="' . $id . '" name="image' . $n . '.' . $ext . '"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="rIdImg' . $n . '"/>' . $crop . '<a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
    }

    private function esc(string $s): string
    {
        // Buang karakter kontrol yang tidak sah di XML.
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    // ------------------------------------------------------------------ paket

    private function zip(string $document): string
    {
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:eastAsia="Arial" w:cs="Arial"/>'
            . '<w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="id-ID"/></w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            . '<w:style w:type="table" w:default="1" w:styleId="TableNormal"><w:name w:val="Normal Table"/><w:tblPr><w:tblInd w:w="0" w:type="dxa"/>'
            . '<w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="0" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style>'
            . '</w:styles>';

        $imgRels = '';
        foreach ($this->media as $i => $m) {
            $imgRels .= '<Relationship Id="rIdImg' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/' . $m['name'] . '"/>';
        }
        $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . $imgRels . '</Relationships>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Default Extension="jpeg" ContentType="image/jpeg"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '</Types>';

        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';

        $tmp = tempnam(sys_get_temp_dir(), 'skhpp');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat berkas DOCX.');
        }
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rootRels);
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/styles.xml', $styles);
        $zip->addFromString('word/_rels/document.xml.rels', $docRels);
        foreach ($this->media as $m) {
            $zip->addFromString('word/media/' . $m['name'], $m['bytes']);
        }
        $zip->close();

        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }
}
