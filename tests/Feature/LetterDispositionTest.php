<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterDispositionTarget;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Security\SecuritySchema;
use Tests\TestCase;

/** Surat masuk bernomor agenda + disposisi wajib (Komandan → jabatan → lapor selesai). */
class LetterDispositionTest extends TestCase
{
    use SecuritySchema;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Schema::create('categories', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('code'); $t->integer('start_number')->default(1); $t->boolean('is_telegram')->default(false); $t->timestamps();
        });
        Schema::create('letters', function (Blueprint $t) {
            $t->id(); $t->string('type'); $t->foreignId('category_id')->nullable(); $t->foreignId('sub_category_id')->nullable(); $t->string('security_level')->nullable();
            $t->string('letter_number')->unique(); $t->string('subject'); $t->date('date')->nullable(); $t->string('issuer')->nullable(); $t->string('file_path'); $t->timestamps();
        });
        Schema::create('app_notifications', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable(); $t->string('role')->nullable(); $t->string('title'); $t->text('message');
            $t->string('type')->nullable(); $t->string('link')->nullable(); $t->boolean('is_read')->default(false); $t->timestamps();
        });
        (require database_path('migrations/2026_11_21_100000_add_incoming_agenda_and_dispositions.php'))->up();
        DB::table('categories')->insert(['id' => 1, 'name' => 'Surat Biasa', 'code' => 'B']);
    }

    private function incoming(array $over = []): array
    {
        return array_merge([
            'letter_number' => 'B/' . uniqid() . '/XI/2026', 'issuer' => 'Kodaeral V', 'subject' => 'Undangan rapat koordinasi',
            'date' => '2026-11-18', 'received_at' => '2026-11-20', 'security_level' => 'biasa', 'category_id' => 1,
            'file' => UploadedFile::fake()->create('surat.pdf', 120, 'application/pdf'),
        ], $over);
    }

    private function recordIncoming(array $over = []): Letter
    {
        $this->actingAs($this->makeUser(['role' => 'staf']))
            ->post('/letters/incoming', $this->incoming($over))
            ->assertSessionHasNoErrors()->assertRedirect();
        return Letter::latest('id')->firstOrFail();
    }

    public function test_incoming_letters_get_sequential_agenda_numbers_per_year(): void
    {
        $a = $this->recordIncoming();
        $b = $this->recordIncoming();
        $c = $this->recordIncoming(['received_at' => '2027-01-03', 'date' => '2027-01-02']);

        $this->assertSame('SM / 1 / XI / 2026', $a->agenda_number);
        $this->assertSame('SM / 2 / XI / 2026', $b->agenda_number);
        $this->assertSame('SM / 1 / I / 2027', $c->agenda_number);
        $this->assertSame(Letter::DISPOSISI_MENUNGGU, $a->disposition_status);
        Storage::disk('local')->assertExists($a->file_path);
        $this->assertDatabaseHas('app_notifications', ['role' => 'komandan', 'title' => 'Surat masuk menunggu disposisi']);
    }

    public function test_manual_sequence_and_format_setting_and_duplicate_rejected(): void
    {
        DB::table('settings')->insert(['key' => 'incoming_agenda_format', 'value' => 'AGD-{urut}/{bulan}/{tahun}']);
        $a = $this->recordIncoming(['agenda_sequence' => '14A']);
        $this->assertSame('AGD-14a/11/2026', $a->agenda_number);

        $this->actingAs($this->makeUser())->post('/letters/incoming', $this->incoming(['agenda_sequence' => '14a']))
            ->assertSessionHasErrors('agenda_sequence');
        $this->actingAs($this->makeUser())->post('/letters/incoming', $this->incoming(['file' => null]))
            ->assertSessionHasErrors('file');
        $this->assertSame(1, Letter::count());
    }

    public function test_only_komandan_can_dispose_and_file_is_locked_until_then(): void
    {
        $letter = $this->recordIncoming();
        $payload = ['roles' => ['pasops', 'kaurmintel'], 'instructions' => ['dilaksanakan'], 'urgency' => 'segera', 'note' => 'Segera'];

        foreach (['admin', 'wadan', 'staf'] as $role) {
            $this->actingAs($this->makeUser(['role' => $role]))->post("/letters/{$letter->id}/disposition", $payload)->assertForbidden();
        }
        $this->actingAs($this->makeUser(['role' => 'pasops']))->get("/letters/{$letter->id}/file")->assertForbidden();
        $this->actingAs($this->makeUser(['role' => 'komandan']))->get("/letters/{$letter->id}/file")->assertOk();

        $this->actingAs($this->makeUser(['role' => 'komandan']))->post("/letters/{$letter->id}/disposition", $payload)
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(Letter::DISPOSISI_DIDISPOSISI, $letter->fresh()->disposition_status);
        $this->assertSame(2, LetterDispositionTarget::count());
        $this->assertDatabaseHas('app_notifications', ['role' => 'pasops', 'title' => 'Disposisi baru dari Komandan']);
        $this->actingAs($this->makeUser(['role' => 'pasops']))->get("/letters/{$letter->id}/file")->assertOk();

        // Tidak bisa didisposisi dua kali.
        $this->actingAs($this->makeUser(['role' => 'komandan']))->post("/letters/{$letter->id}/disposition", $payload)->assertStatus(422);
    }

    public function test_targets_receive_and_complete_then_letter_is_done(): void
    {
        $letter = $this->recordIncoming();
        $this->actingAs($this->makeUser(['role' => 'komandan']))->post("/letters/{$letter->id}/disposition", [
            'roles' => ['pasops', 'paurset'], 'instructions' => ['diketahui', 'arsipkan'], 'urgency' => 'biasa',
        ])->assertRedirect();
        $pasops = LetterDispositionTarget::where('role', 'pasops')->firstOrFail();
        $paurset = LetterDispositionTarget::where('role', 'paurset')->firstOrFail();

        $this->actingAs($this->makeUser(['role' => 'kaurmintel']))->post("/dispositions/targets/{$pasops->id}/receive")->assertForbidden();

        $this->actingAs($this->makeUser(['role' => 'pasops']))->post("/dispositions/targets/{$pasops->id}/receive")->assertRedirect();
        $this->assertSame('diterima', $pasops->fresh()->status);
        $this->actingAs($this->makeUser(['role' => 'pasops']))->post("/dispositions/targets/{$pasops->id}/complete", ['report_note' => ''])
            ->assertSessionHasErrors('report_note');
        $this->actingAs($this->makeUser(['role' => 'pasops']))->post("/dispositions/targets/{$pasops->id}/complete", ['report_note' => 'Sudah dikoordinasikan'])
            ->assertRedirect();
        $this->assertSame(Letter::DISPOSISI_DIDISPOSISI, $letter->fresh()->disposition_status);

        $this->actingAs($this->makeUser(['role' => 'paurset']))->post("/dispositions/targets/{$paurset->id}/complete", ['report_note' => 'Diarsipkan'])
            ->assertRedirect();
        $this->assertSame('selesai', $paurset->fresh()->status);
        $this->assertSame(Letter::DISPOSISI_SELESAI, $letter->fresh()->disposition_status);
    }

    public function test_archive_hides_awaiting_letters_and_shows_disposition(): void
    {
        $waiting = $this->recordIncoming(['subject' => 'Masih menunggu']);
        $done = $this->recordIncoming(['subject' => 'Sudah didisposisi']);
        $this->actingAs($this->makeUser(['role' => 'komandan']))->post("/letters/{$done->id}/disposition", [
            'roles' => ['pasops'], 'instructions' => ['diketahui'], 'urgency' => 'biasa',
        ])->assertRedirect();

        $this->actingAs($this->makeUser(['role' => 'staf']))->get('/letters')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Letters/Index')
                ->where('awaiting_disposition', 1)
                ->has('letters.data', 1)
                ->where('letters.data.0.id', $done->id)
                ->where('letters.data.0.agenda_number', $done->agenda_number)
                ->where('letters.data.0.disposition.targets.0.role', 'pasops'));

        // Pencarian lama ("orWhere" tanpa kurung) tidak boleh membocorkan surat yang menunggu.
        $this->actingAs($this->makeUser(['role' => 'staf']))->get('/letters?search=Masih')
            ->assertInertia(fn ($page) => $page->has('letters.data', 0));
        $this->assertNotNull($waiting->id);
    }

    private function realPdf(int $pages = 2): UploadedFile
    {
        $pdf = new \FPDF();
        $pdf->SetFont('Arial', '', 12);
        for ($i = 1; $i <= $pages; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, "Halaman surat {$i}");
        }
        return UploadedFile::fake()->createWithContent('surat.pdf', $pdf->Output('S'));
    }

    private function pageCount(string $path): int
    {
        return (new \setasign\Fpdi\Fpdi())->setSourceFile(Storage::disk('local')->path($path));
    }

    public function test_telegram_has_own_agenda_sequence_and_requires_twu(): void
    {
        DB::table('categories')->insert(['id' => 2, 'name' => 'Telegram Biasa', 'code' => 'TG', 'is_telegram' => 1]);
        $surat = $this->recordIncoming();
        $tg = $this->recordIncoming(['type' => 'telegram', 'twu' => '201030Z NOV 2026', 'category_id' => 2]);

        $this->assertSame('SM / 1 / XI / 2026', $surat->agenda_number);
        $this->assertSame('TG / 1 / XI / 2026', $tg->agenda_number);
        $this->assertSame('telegram', $tg->type);
        $this->assertSame('201030Z NOV 2026', $tg->twu);
        $this->assertTrue($tg->isAwaitingDisposition());
        $this->assertDatabaseHas('app_notifications', ['role' => 'komandan', 'title' => 'Telegram menunggu disposisi']);

        $this->actingAs($this->makeUser())->post('/letters/incoming', $this->incoming(['type' => 'telegram', 'twu' => '']))
            ->assertSessionHasErrors('twu');
    }

    public function test_disposition_sheet_is_merged_in_front_and_original_upload_deleted(): void
    {
        $letter = $this->recordIncoming(['file' => $this->realPdf(2)]);
        $original = $letter->file_path;
        Storage::disk('local')->assertExists($original);

        $this->actingAs($this->makeUser(['role' => 'komandan', 'pangkat' => 'Kolonel Laut (E)']))->post("/letters/{$letter->id}/disposition", [
            'roles' => ['pasops', 'danunit1'], 'instructions' => ['dilaksanakan'], 'urgency' => 'segera', 'note' => 'Laksanakan',
        ])->assertSessionHasNoErrors();

        $letter->refresh();
        $this->assertStringStartsWith('archives/disposisi/surat-', $letter->file_path);
        $this->assertSame(1, (int) $letter->disposition_sheet_pages);
        $this->assertNull($letter->disposition_sheet_path);
        Storage::disk('local')->assertMissing($original);              // unggahan asli dihapus setelah gabungan tersimpan
        $this->assertSame(3, $this->pageCount($letter->file_path));    // 1 lembar A5 + 2 halaman surat

        // Lapor selesai → lembar dibuat ulang (kolom Paraf), halaman surat tidak berlipat.
        $merged1 = $letter->file_path;
        $target = LetterDispositionTarget::where('role', 'pasops')->firstOrFail();
        $this->travel(2)->seconds();
        $this->actingAs($this->makeUser(['role' => 'pasops']))->post("/dispositions/targets/{$target->id}/complete", ['report_note' => 'Selesai'])
            ->assertRedirect();
        $letter->refresh();
        $this->assertNotSame($merged1, $letter->file_path);
        Storage::disk('local')->assertMissing($merged1);
        $this->assertSame(3, $this->pageCount($letter->file_path));

        $data = \App\Support\LembarDisposisi::viewData($letter->fresh());
        $this->assertSame(['Pasops', 'Dantim I / Lid'], collect($data['rows'])->where('dipilih', true)->pluck('label')->values()->all());
        $this->assertNotNull(collect($data['rows'])->firstWhere('label', 'Pasops')['paraf']);
        $this->assertSame('Yth. Dan Den Intel', $data['yth']);
    }

    public function test_unreadable_pdf_keeps_original_and_stores_sheet_separately(): void
    {
        $letter = $this->recordIncoming(); // berkas palsu, tidak bisa dibaca FPDI
        $original = $letter->file_path;
        $this->actingAs($this->makeUser(['role' => 'komandan']))->post("/letters/{$letter->id}/disposition", [
            'roles' => ['paurset'], 'instructions' => ['arsipkan'], 'urgency' => 'biasa',
        ])->assertSessionHasNoErrors();

        $letter->refresh();
        $this->assertSame($original, $letter->file_path);
        Storage::disk('local')->assertExists($original);
        $this->assertNotNull($letter->disposition_sheet_path);
        Storage::disk('local')->assertExists($letter->disposition_sheet_path);
        $this->actingAs($this->makeUser(['role' => 'paurset']))->get("/letters/{$letter->id}/disposition-sheet")->assertOk();
    }

    public function test_old_incoming_letters_are_not_locked(): void
    {
        Storage::disk('local')->put('archives/lama.pdf', '%PDF-1.4 lama');
        $id = DB::table('letters')->insertGetId([
            'type' => 'masuk', 'category_id' => 1, 'security_level' => 'biasa', 'letter_number' => 'LAMA/1', 'subject' => 'Surat lama',
            'date' => '2026-01-01', 'issuer' => 'X', 'file_path' => 'archives/lama.pdf', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($this->makeUser(['role' => 'pasops']))->get("/letters/{$id}/file")->assertOk();
    }

    public function test_letters_create_with_type_masuk_goes_through_agenda(): void
    {
        $this->actingAs($this->makeUser())->post('/letters', [
            'type' => 'masuk', 'letter_number' => 'UND/9/XI/2026', 'category_id' => 1, 'subject' => 'Undangan', 'date' => '2026-11-19',
            'issuer' => 'Lanal', 'security_level' => 'biasa', 'file' => UploadedFile::fake()->create('s.pdf', 50, 'application/pdf'),
        ])->assertRedirect(route('letter-logs.index', ['tab' => 'disposisi']));

        $l = Letter::firstOrFail();
        $this->assertNotNull($l->agenda_number);
        $this->assertSame(Letter::DISPOSISI_MENUNGGU, $l->disposition_status);
    }
}
