<?php

namespace Tests\Feature;

use App\Models\Skhpp;
use App\Models\SkhppMember;
use Tests\TestCase;

/** Dokumen SKHPP: label identitas mengikuti jenis_identitas, poin 2h memuat jumlah pengikut. */
class SkhppPdfViewTest extends TestCase
{
    private function render(array $attrs, array $members = []): string
    {
        $skhpp = new Skhpp(array_merge([
            'kategori_personel' => 'militer', 'nama' => 'Budi', 'jabatan_pekerjaan' => 'Staf',
            'tempat_lahir' => 'Surabaya', 'jenis_kelamin' => 'Laki-laki', 'agama' => 'Hindu',
            'alamat' => 'Jl. Uji', 'surat_pengantar' => 'Surat', 'peruntukan' => 'uji', 'status' => 'pending',
        ], $attrs));
        $skhpp->setRelation('members', collect($members)->map(fn ($n) => new SkhppMember([
            'nama' => $n, 'pangkat_nrp_nik' => '123', 'jabatan' => 'Anggota',
        ])));

        return view('pdf.skhpp', [
            'skhpp' => $skhpp, 'foto1_base64' => null, 'foto2_base64' => null, 'qr_base64' => null,
            'tanggal_lahir_indo' => '1 Januari 1990', 'tanggal_skhpp_indo' => '16 November 2026', 'bulan_tahun_indo' => 'November 2026',
        ])->render();
    }

    public function test_label_follows_identity_type(): void
    {
        $this->assertStringContainsString('Pangkat/Korp/NRP', $this->render(['jenis_identitas' => 'NRP', 'pangkat_korps_nrp' => 'Kapten Laut (P) NRP 1/P']));
        $this->assertStringContainsString('Pangkat/Gol/NIP', $this->render(['jenis_identitas' => 'NIP', 'pangkat_korps_nrp' => 'Penata III/c NIP 19']));

        $nim = $this->render(['jenis_identitas' => 'NIM', 'nik' => 'A11.2020']);
        $this->assertStringContainsString('>NIM<', $nim);
        $this->assertStringContainsString('Pekerjaan', $nim);

        $this->assertStringContainsString('>NIK<', $this->render(['jenis_identitas' => 'NIK', 'nik' => '3515']));
    }

    public function test_legacy_rows_without_identity_type_keep_old_layout(): void
    {
        $this->assertStringContainsString('Pangkat/Korp/NRP', $this->render(['pangkat_korps_nrp' => 'Serka NRP 9']));
        $this->assertStringContainsString('>NIK<', $this->render(['nik' => '3515']));
    }

    public function test_point_2h_shows_follower_count_and_attachment_lists_names(): void
    {
        $html = $this->render(['jenis_identitas' => 'NIK', 'nik' => '3515', 'has_pengikut' => true], ['Ani Pengikut', 'Made Pengikut']);
        $this->assertStringContainsString('Pengikut</td>', $html);
        $this->assertStringContainsString('2 orang (daftar nama terlampir)', $html);
        $this->assertStringContainsString('DAFTAR NAMA-NAMA ANGGOTA PENGIKUT', $html);
        $this->assertStringContainsString('Ani Pengikut', $html);
        $this->assertStringContainsString('Made Pengikut', $html);
    }

    public function test_attachment_header_title_and_member_column_follow_the_form(): void
    {
        $html = $this->render([
            'jenis_identitas' => 'NRP', 'pangkat_korps_nrp' => 'Serda NRP 1', 'has_pengikut' => true,
            'satuan' => 'Pomal Kodaeral V', 'kegiatan' => 'Seleksi Diktukpa LVI/56 TA. 2026',
            'nomor_tulis_tangan' => true, 'bulan_romawi' => 'XI', 'tahun' => 2026,
        ], ['Ani Pengikut']);
        $this->assertStringContainsString('Lampiran SKHPP Denintel Kodaeral V', $html);
        $this->assertStringContainsString('DAFTAR NAMA PERSONEL POMAL KODAERAL V YANG MELAKSANAKAN SC SELEKSI DIKTUKPA LVI/56 TA. 2026', $html);
        $this->assertStringContainsString('>Pangkat/Korp/NRP</th>', $html);
        // Nomor ditulis tangan: kosong di halaman 1 dan di lampiran.
        $this->assertSame(2, substr_count($html, 'R/' . str_repeat('&nbsp;', 16) . '/SKHPP/XI/2026'));
    }

    public function test_no_point_2h_without_followers(): void
    {
        $html = $this->render(['jenis_identitas' => 'NIK', 'nik' => '3515', 'has_pengikut' => false]);
        $this->assertStringNotContainsString('daftar nama terlampir', $html);
        $this->assertStringNotContainsString('DAFTAR NAMA-NAMA ANGGOTA PENGIKUT', $html);
    }

    public function test_docx_is_valid_word_package_with_same_content(): void
    {
        $skhpp = new Skhpp([
            'kategori_personel' => 'militer', 'nama' => 'Budi & <Uji>', 'jabatan_pekerjaan' => 'Staf',
            'tempat_lahir' => 'Surabaya', 'jenis_kelamin' => 'Laki-laki', 'agama' => 'Hindu', 'alamat' => 'Jl. Uji',
            'surat_pengantar' => 'Surat', 'peruntukan' => 'uji', 'status' => 'pending',
            'jenis_identitas' => 'NIP', 'pangkat_korps_nrp' => 'Penata III/c NIP 19', 'has_pengikut' => true,
        ]);
        $skhpp->setRelation('members', collect([new SkhppMember(['nama' => 'Ani Pengikut', 'pangkat_nrp_nik' => '1', 'jabatan' => 'Anggota'])]));

        $bytes = (new \App\Services\SkhppDocx())->build($skhpp, [
            'foto1' => null, 'foto2' => null, 'qr' => null,
            'tanggal_lahir_indo' => '1 Januari 1990', 'tanggal_skhpp_indo' => '16 November 2026', 'bulan_tahun_indo' => 'November 2026',
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmp, $bytes);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);

        $this->assertNotFalse(simplexml_load_string($xml), 'document.xml harus XML yang sah');
        $this->assertStringContainsString('w:w="12189" w:h="18709"', $xml); // F4
        $this->assertStringContainsString('Budi &amp; &lt;Uji&gt;', $xml);
        $this->assertStringContainsString('Pangkat/Gol/NIP', $xml);
        $this->assertStringContainsString('1 orang (daftar nama terlampir)', $xml);
        $this->assertStringContainsString('Ani Pengikut', $xml);
    }
}
