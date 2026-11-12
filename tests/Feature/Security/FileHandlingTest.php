<?php

namespace Tests\Feature\Security;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Audit T05, T06, S04: berkas & unggahan. */
class FileHandlingTest extends TestCase
{
    use SecuritySchema;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('soldier_violations', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('nrp'); $t->string('rank')->nullable(); $t->string('position')->nullable();
            $t->string('unit')->nullable(); $t->text('case_description')->nullable(); $t->date('incident_date')->nullable();
            $t->text('case_development')->nullable(); $t->string('status')->nullable(); $t->text('lampiran_berkas')->nullable();
            $t->text('dokumen_putusan')->nullable(); $t->timestamps();
        });
        Schema::create('letters', function (Blueprint $t) {
            $t->id(); $t->string('letter_number')->nullable(); $t->string('subject')->nullable(); $t->string('file_path')->nullable(); $t->timestamps();
        });
    }

    private function realFile(string $name, string $content): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, $content);
        return new UploadedFile($tmp, $name, null, null, true);
    }

    public function test_preview_rejects_path_traversal(): void
    {
        Storage::disk('local')->put('print_jobs/originals/rahasia.pdf', "%PDF-1.4\n% RAHASIA\n%%EOF");
        $this->actingAs($this->makeUser())
            ->get('/soldier-violations/preview-file?file_path=' . urlencode('../private/print_jobs/originals/rahasia.pdf'))
            ->assertNotFound();
        Storage::disk('local')->delete('print_jobs/originals/rahasia.pdf');
    }

    public function test_preview_still_serves_violation_files(): void
    {
        Storage::disk('public')->put('pelanggaran/lampiran/uji.pdf', "%PDF-1.4\n%%EOF");
        $this->actingAs($this->makeUser())->get('/soldier-violations/preview-file?file_path=pelanggaran/lampiran/uji.pdf')->assertOk();
        Storage::disk('public')->delete('pelanggaran/lampiran/uji.pdf');
    }

    public function test_violation_upload_extension_comes_from_content_not_client_name(): void
    {
        Storage::fake('public');
        $poly = $this->realFile('poc.html', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n" . str_repeat("% pad\n", 50) . "<html><script>alert(1)</script></html>\n%%EOF\n");
        $this->actingAs($this->makeUser())->post('/soldier-violations', [
            'nama' => 'X', 'nrp' => 'N1', 'pangkat' => 'P', 'jabatan' => 'J', 'satuan' => 'S', 'kasus' => 'K',
            'tmt' => '2026-11-01', 'perkembangan_kasus' => 'awal', 'status' => 'PROSES', 'lampiran' => [$poly],
        ])->assertSessionHasNoErrors();

        $files = Storage::disk('public')->files('pelanggaran/lampiran');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.pdf', $files[0]);
        $this->assertStringNotContainsString('.html', $files[0]);
    }

    public function test_letter_archive_served_only_through_authorized_route(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('archives/surat.pdf', "%PDF-1.4\n%%EOF");
        $id = DB::table('letters')->insertGetId(['letter_number' => 'B/1', 'subject' => 'Rahasia', 'file_path' => 'archives/surat.pdf']);

        $this->get("/letters/{$id}/file")->assertRedirect(); // tamu -> login
        $this->actingAs($this->makeUser(['role' => 'anggotasintel']))->get("/letters/{$id}/file")->assertForbidden();
        $this->actingAs($this->makeUser(['role' => 'staf']))->get("/letters/{$id}/file")->assertOk();
    }

    public function test_stamp_manual_file_must_be_pdf(): void
    {
        $komandan = $this->makeUser(['role' => 'komandan']);
        $this->actingAs($komandan)->patch('/otoritas-stempel/eksekusi', [
            'subject' => 'x', 'x' => 0.5, 'y' => 0.5, 'width' => 0.2, 'target_page' => 1,
            'pdf_file' => $this->realFile('a.pdf', "<?php echo 1;"),
        ])->assertSessionHasErrors('pdf_file');
    }
}
