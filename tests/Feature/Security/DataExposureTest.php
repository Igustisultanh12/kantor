<?php

namespace Tests\Feature\Security;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Audit K02, K03, S01, S09, S10, R05: kebocoran data & rahasia. */
class DataExposureTest extends TestCase
{
    use SecuritySchema;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('print_jobs', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id'); $t->string('document_title')->nullable(); $t->text('original_file_path')->nullable();
            $t->text('preview_pdf_path')->nullable(); $t->text('printable_pdf_path')->nullable(); $t->string('printer_ip')->nullable();
            $t->string('status')->default('draft'); $t->integer('printed_sheets')->default(0); $t->timestamp('completed_at')->nullable(); $t->timestamps();
        });
        foreach (['skhpps' => ['nomor_skhpp', 'status', 'submitted_by', 'approved_by'], 'sp_jagas' => ['nomor_sprin', 'status'], 'signature_requests' => ['letter_number', 'status', 'user_id']] as $table => $cols) {
            Schema::create($table, function (Blueprint $t) use ($cols) {
                $t->id(); $t->string('verification_code')->nullable();
                foreach ($cols as $c) { $t->string($c)->nullable(); }
                $t->timestamps();
            });
        }
        DB::table('settings')->insert([
            ['key' => 'google_client_secret', 'value' => 'GOCSPX-RAHASIA-UJI'],
            ['key' => 'agora_app_certificate', 'value' => 'AGORA-CERT-UJI'],
            ['key' => 'agency_name', 'value' => 'DENINTEL UJI'],
        ]);
    }

    public function test_public_verify_pages_do_not_leak_secret_settings(): void
    {
        foreach (['/verify-doc/TIDAKADA', '/verify-skhpp/TIDAKADA'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('DENINTEL UJI', false)
                ->assertDontSee('GOCSPX-RAHASIA-UJI', false)
                ->assertDontSee('AGORA-CERT-UJI', false);
        }
    }

    public function test_stamp_config_does_not_leak_secret_settings(): void
    {
        $this->actingAs($this->makeUser())->get('/settings/stamp-config')
            ->assertDontSee('GOCSPX-RAHASIA-UJI', false)->assertDontSee('AGORA-CERT-UJI', false);
    }

    public function test_admin_settings_page_shows_only_secret_status_and_saves_encrypted(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $this->actingAs($admin)->get('/settings')->assertOk()->assertDontSee('GOCSPX-RAHASIA-UJI', false);

        \App\Support\SecretSetting::put('google_client_secret', 'GOCSPX-BARU');
        $this->assertStringStartsWith('enc:', DB::table('settings')->where('key', 'google_client_secret')->value('value'));
        $this->assertSame('GOCSPX-BARU', \App\Support\SecretSetting::get('google_client_secret'));
    }

    public function test_reset_token_and_print_paths_are_not_serialized_to_other_users(): void
    {
        $victim = $this->makeUser(['reset_token' => 'ABC123', 'token_expires_at' => now()->addMinutes(5), 'phone' => '0812']);
        DB::table('print_jobs')->insert(['user_id' => $victim->id, 'status' => 'completed', 'original_file_path' => '/srv/rahasia.pdf', 'created_at' => now(), 'updated_at' => now()]);

        $res = $this->actingAs($this->makeUser())->getJson('/layanan-printing/queue-status')->assertOk();
        $json = $res->getContent();
        $this->assertStringNotContainsString('ABC123', $json);
        $this->assertStringNotContainsString('rahasia.pdf', $json);
        $this->assertStringNotContainsString('0812', $json);
        $this->assertSame($victim->name, $res->json('recent_jobs.0.user.name'));
    }

    public function test_public_tracking_returns_only_public_fields(): void
    {
        \App\Models\ScSubmission::ensureSchema();
        DB::table('sc_submissions')->insert([
            'tracking_code' => 'TRK-UJI', 'nama' => 'Pemohon', 'identifier_number' => '1234567', 'phone' => '0812-RAHASIA',
            'file_skhpp' => 'sc_documents/rahasia-skhpp.pdf', 'file_sc_preview' => 'sc_documents/rahasia-sc.pdf',
            'current_stage' => 1, 'status' => 'proses', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->get('/tracking-sc?identifier=1234567')->assertOk()
            ->assertSee('TRK-UJI', false)
            ->assertDontSee('0812-RAHASIA', false)
            ->assertDontSee('rahasia-skhpp.pdf', false)
            ->assertDontSee('rahasia-sc.pdf', false);
    }

    public function test_audit_trail_is_admin_only(): void
    {
        $this->actingAs($this->makeUser())->getJson('/api/audit-trail?target=%')->assertForbidden();
        $this->actingAs($this->makeUser(['role' => 'admin']))->getJson('/api/audit-trail?target=x')->assertOk();
    }

    public function test_error_ref_hides_exception_message(): void
    {
        $ref = \App\Support\ErrorRef::log(new \RuntimeException('SQLSTATE rahasia /www/wwwroot'));
        $this->assertMatchesRegularExpression('/^kode ERR-[A-Z0-9]{8}$/', $ref);
    }
}
