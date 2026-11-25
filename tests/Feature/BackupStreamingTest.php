<?php

namespace Tests\Feature;

use App\Services\FileSecurityService;
use App\Services\StreamingZipService;
use App\Services\ThumbnailService;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Unduh ZIP, pratinjau gambar, dan pemutaran video di Backup Explorer & folder berbagi (tamu).
 */
class BackupStreamingTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/backup_stream_' . uniqid());
        @mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) @unlink($f);
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** Berkas terenkripsi berisi $plain di disk, seperti hasil unggah. */
    private function encrypted(string $plain, string $name = 'berkas.bin'): string
    {
        $src = $this->dir . '/plain_' . $name;
        file_put_contents($src, $plain);
        $enc = $this->dir . '/enc_' . $name;
        FileSecurityService::encryptAndStoreFile($src, $enc);
        return $enc;
    }

    private function withRequest(array $server = []): void
    {
        $this->app->instance('request', Request::create('/preview', 'GET', [], [], [], $server));
    }

    private function zipEntries(callable $build): array
    {
        $path = $this->dir . '/uji.zip';
        $out = fopen($path, 'wb');
        $zip = new StreamingZipService($out);
        $build($zip);
        $zip->finish();
        fclose($out);

        $za = new \ZipArchive();
        $this->assertTrue($za->open($path, \ZipArchive::CHECKCONS), 'ZIP harus valid');
        $names = [];
        for ($i = 0; $i < $za->numFiles; $i++) $names[] = $za->getNameIndex($i);
        $za->close();
        return $names;
    }

    public function test_zip_renames_duplicate_entries_so_windows_can_extract(): void
    {
        $names = $this->zipEntries(function (StreamingZipService $zip) {
            $dir = $zip->addEmptyDir('DOKUMEN');
            $dir2 = $zip->addEmptyDir('dokumen'); // Windows tidak membedakan huruf besar/kecil
            foreach ([$dir, $dir, $dir2] as $d) {
                $zip->addFileStream($d . '/foto.jpg', fn ($w) => $w('isi'));
            }
        });

        $this->assertSame(['DOKUMEN/', 'dokumen (2)/', 'DOKUMEN/foto.jpg', 'DOKUMEN/foto (2).jpg', 'dokumen (2)/foto.jpg'], $names);
    }

    public function test_zip_replaces_characters_windows_forbids(): void
    {
        $nama = StreamingZipService::namaAman('RAPAT 10:00 "RAHASIA"?') . '/' . StreamingZipService::namaAman('notulen<1>|a*.txt ');
        $names = $this->zipEntries(fn (StreamingZipService $zip) => $zip->addFileStream($nama, fn ($w) => $w('isi')));

        $this->assertSame(['RAPAT 10_00 _RAHASIA__/notulen_1__a_.txt'], $names);
        $this->assertSame('KEGIATAN 17_08_2026', StreamingZipService::namaAman('KEGIATAN 17/08/2026'));
        $this->assertSame('CON_', StreamingZipService::namaAman('CON'));
        $this->assertSame('_', StreamingZipService::namaAman('..'));
    }

    public function test_open_ended_video_range_is_served_in_pieces_not_whole_file(): void
    {
        $plain = random_bytes(6 * 1024 * 1024 + 123);
        $enc = $this->encrypted($plain, 'video.mp4');

        $this->withRequest(['HTTP_RANGE' => 'bytes=1000-']);
        $res = FileSecurityService::streamMediaWithRange($enc, 'video.mp4');

        $this->assertSame(206, $res->getStatusCode());
        $akhir = 1000 + FileSecurityService::MEDIA_RANGE_CHUNK - 1;
        $this->assertSame("bytes 1000-{$akhir}/" . strlen($plain), $res->headers->get('Content-Range'));
        $this->assertSame((string) FileSecurityService::MEDIA_RANGE_CHUNK, $res->headers->get('Content-Length'));
        $this->assertSame('no', $res->headers->get('X-Accel-Buffering'));

        // Rentang eksplisit tetap dihormati apa adanya.
        $this->withRequest(['HTTP_RANGE' => 'bytes=0-1']);
        $this->assertSame('bytes 0-1/' . strlen($plain), FileSecurityService::streamMediaWithRange($enc, 'video.mp4')->headers->get('Content-Range'));
    }

    public function test_inline_preview_sends_size_and_supports_not_modified(): void
    {
        $plain = random_bytes(200000);
        $enc = $this->encrypted($plain, 'dok.pdf');

        $this->withRequest();
        $res = FileSecurityService::streamDecryptedInline($enc, 'dok.pdf');
        $this->assertSame((string) strlen($plain), $res->headers->get('Content-Length'));
        $etag = $res->headers->get('ETag');
        $this->assertNotEmpty($etag);
        ob_start();
        $res->sendContent();
        $this->assertSame($plain, ob_get_clean());

        // Dibuka lagi: browser cukup memastikan berkas belum berubah (304, tanpa isi).
        $this->withRequest(['HTTP_IF_NONE_MATCH' => $etag]);
        $this->assertSame(304, FileSecurityService::streamDecryptedInline($enc, 'dok.pdf')->getStatusCode());
    }

    public function test_large_photo_preview_is_downscaled_and_cached_encrypted(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD tidak tersedia');
        }
        // Foto 4000x3000 (lebih besar dari batas pratinjau) dengan isi acak supaya berukuran besar.
        $img = imagecreatetruecolor(4000, 3000);
        for ($y = 0; $y < 3000; $y += 20) {
            for ($x = 0; $x < 4000; $x += 20) {
                imagefilledrectangle($img, $x, $y, $x + 19, $y + 19, random_int(0, 0xFFFFFF));
            }
        }
        ob_start();
        imagejpeg($img, null, 95);
        $jpeg = ob_get_clean();
        imagedestroy($img);
        $enc = $this->encrypted($jpeg, 'foto.jpg');

        $this->withRequest();
        $res = ThumbnailService::getPreviewResponse($enc, 'foto.jpg');
        ob_start();
        $res->sendContent();
        $isi = ob_get_clean();

        [$w, $h] = getimagesizefromstring($isi);
        $this->assertSame(ThumbnailService::PREVIEW_MAX, max($w, $h));
        $this->assertSame(round(4000 / 3000, 2), round($w / $h, 2));
        $this->assertLessThan(strlen($jpeg), strlen($isi));
        $this->assertSame((string) strlen($isi), $res->headers->get('Content-Length'));

        // Salinan pratinjau di cache tetap terenkripsi.
        $cache = glob(storage_path('app/private/cache_previews/*'));
        $this->assertNotEmpty($cache);
        foreach ($cache as $c) {
            $this->assertTrue(FileSecurityService::isEncrypted($c));
            @unlink($c);
        }
    }

    public function test_small_photo_preview_is_served_as_is(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        $enc = $this->encrypted($png, 'kecil.png');

        $this->withRequest();
        $res = ThumbnailService::getPreviewResponse($enc, 'kecil.png');
        ob_start();
        $res->sendContent();
        $this->assertSame($png, ob_get_clean());
    }
}
