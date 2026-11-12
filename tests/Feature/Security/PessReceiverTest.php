<?php

namespace Tests\Feature\Security;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Audit K05: penerima berkas PESS. */
class PessReceiverTest extends TestCase
{
    use SecuritySchema;

    private const LAMA = 'Malang101202...!!!???Benteng';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Schema::create('applicants', function (Blueprint $t) {
            $t->id(); $t->string('nomor_identitas')->unique(); $t->string('nama_lengkap'); $t->string('no_wa')->nullable();
            $t->string('status')->nullable(); $t->timestamp('verified_at')->nullable(); $t->timestamps();
        });
        Schema::create('submissions', function (Blueprint $t) {
            $t->id(); $t->foreignId('applicant_id'); $t->unsignedBigInteger('submission_category_id')->nullable();
            $t->text('answers_json')->nullable(); $t->string('generated_pdf_path')->nullable(); $t->text('attachment_paths')->nullable();
            $t->string('status')->nullable(); $t->timestamps();
        });
    }

    private function payload(array $extra = []): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($tmp, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");
        return array_merge([
            'nomor_identitas' => 'UJI123', 'nama_lengkap' => 'Pemohon', 'submission_category_id' => 1,
            'answers_json' => '{}', 'master_drh_pdf' => new UploadedFile($tmp, 'drh.pdf', null, null, true),
        ], $extra);
    }

    public function test_gateway_closed_when_token_not_configured(): void
    {
        config(['services.pess.token' => null]);
        $this->withHeader('Authorization', 'Bearer ' . self::LAMA)->post('/api/v1/pess/receive-submission', $this->payload())->assertStatus(503);
    }

    public function test_old_hardcoded_token_is_rejected(): void
    {
        config(['services.pess.token' => 'token-baru-yang-panjang']);
        $this->withHeader('Authorization', 'Bearer ' . self::LAMA)->post('/api/v1/pess/receive-submission', $this->payload())->assertStatus(401);
    }

    public function test_script_attachment_is_rejected(): void
    {
        config(['services.pess.token' => 'token-baru-yang-panjang']);
        $tmp = tempnam(sys_get_temp_dir(), 'php');
        file_put_contents($tmp, "<?php echo 'x';");
        $this->withHeader('Authorization', 'Bearer token-baru-yang-panjang')->withHeader('Accept', 'application/json')
            ->post('/api/v1/pess/receive-submission', $this->payload(['files_x' => new UploadedFile($tmp, 'x.jpg', null, null, true)]))
            ->assertStatus(422);
        $this->assertSame([], Storage::disk('public')->allFiles('submissions'));
    }

    public function test_valid_submission_is_accepted(): void
    {
        config(['services.pess.token' => 'token-baru-yang-panjang']);
        $this->withHeader('Authorization', 'Bearer token-baru-yang-panjang')->withHeader('Accept', 'application/json')
            ->post('/api/v1/pess/receive-submission', $this->payload())->assertOk();
    }
}
