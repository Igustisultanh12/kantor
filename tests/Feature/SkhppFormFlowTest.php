<?php

namespace Tests\Feature;

use App\Models\Skhpp;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Security\SecuritySchema;
use Tests\TestCase;

/** Form SKHPP → pengesahan: jenis identitas, pengikut, nomor manual, dan sinkron Jenis Surat ke Agenda Surat. */
class SkhppFormFlowTest extends TestCase
{
    use SecuritySchema;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        (require database_path('migrations/2026_08_05_130000_create_skhpps_and_skhpp_members_tables.php'))->up();
        (require database_path('migrations/2026_11_16_100000_add_form_fields_to_skhpps_table.php'))->up();
        (require database_path('migrations/2026_11_16_170000_add_manual_signature_to_skhpps_table.php'))->up();
        Schema::create('categories', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('code'); $t->integer('start_number')->default(1); $t->boolean('is_telegram')->default(false); $t->timestamps();
        });
        Schema::create('sub_categories', function (Blueprint $t) {
            $t->id(); $t->foreignId('category_id'); $t->string('name'); $t->string('sub_code')->nullable(); $t->timestamps();
        });
        Schema::create('letter_logs', function (Blueprint $t) {
            $t->id(); $t->string('full_number'); $t->string('sequence'); $t->string('priority')->nullable(); $t->foreignId('category_id');
            $t->foreignId('sub_category_id')->nullable(); $t->text('subject')->nullable(); $t->string('recipient')->nullable(); $t->date('date')->nullable();
            $t->boolean('is_archived')->default(false); $t->string('number')->nullable(); $t->foreignId('user_id')->nullable(); $t->timestamps();
        });
        DB::table('categories')->insert(['id' => 1, 'name' => 'SKHPP Dinas Militer & PNS', 'code' => 'SKHPP-D']);
        DB::table('sub_categories')->insert(['id' => 7, 'category_id' => 1, 'name' => 'PNS']);
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'kategori_personel' => 'sipil_dinas', 'is_pernikahan' => false, 'surat_pengantar' => 'Surat uji',
            'nama' => 'Ketut Uji', 'jenis_identitas' => 'NIP', 'pangkat_korps_nrp' => 'Penata III/c NIP 197305121998031002',
            'jabatan_pekerjaan' => 'Staf', 'tempat_lahir' => 'Denpasar', 'tanggal_lahir' => '1973-05-12',
            'jenis_kelamin' => 'Laki-laki', 'agama' => 'Hindu', 'alamat' => 'Jl. Uji', 'peruntukan' => 'uji',
            'foto_1' => UploadedFile::fake()->image('foto.jpg', 30, 40),
        ], $over);
    }

    public function test_manual_number_followers_and_letter_type_flow_through_approval(): void
    {
        $this->actingAs($this->makeUser())->post('/skhpp', $this->payload([
            'letter_sub_category_id' => 7,
            'nomor_manual' => '171a',
            'has_pengikut' => true,
            'members' => [
                ['nama' => 'Ani', 'pangkat_nrp_nik' => '3515', 'jabatan' => 'Anggota'],
                ['nama' => 'Made', 'pangkat_nrp_nik' => '3516', 'jabatan' => 'Anggota'],
            ],
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][date('n') - 1];
        $nomor = 'R / 171a / SKHPP / ' . $roman . ' / ' . date('Y');
        $skhpp = Skhpp::with('members')->firstOrFail();
        $this->assertSame('NIP', $skhpp->jenis_identitas);
        $this->assertSame($nomor, $skhpp->nomor_skhpp);
        $this->assertCount(2, $skhpp->members);

        $this->actingAs($this->makeUser(['role' => 'komandan']))->post("/skhpp/{$skhpp->id}/approve")->assertRedirect();

        $skhpp->refresh();
        $this->assertSame('approved', $skhpp->status);
        $this->assertSame($nomor, $skhpp->nomor_skhpp);
        $this->assertSame(171, (int) $skhpp->nomor_urut);
        $this->assertDatabaseHas('letter_logs', [
            'full_number' => $nomor, 'sequence' => '171a', 'category_id' => 1, 'sub_category_id' => 7,
        ]);
    }

    public function test_without_manual_number_the_next_automatic_number_is_used(): void
    {
        $this->actingAs($this->makeUser())->post('/skhpp', $this->payload())->assertSessionHasNoErrors();
        $skhpp = Skhpp::firstOrFail();
        $this->assertNull($skhpp->nomor_skhpp);

        $this->actingAs($this->makeUser(['role' => 'komandan']))->post("/skhpp/{$skhpp->id}/approve")->assertRedirect();

        $skhpp->refresh();
        $this->assertSame(1, (int) $skhpp->nomor_urut);
        $this->assertStringStartsWith('R / 1 / SKHPP / ', $skhpp->nomor_skhpp);
        $this->assertDatabaseHas('letter_logs', ['full_number' => $skhpp->nomor_skhpp, 'sub_category_id' => null]);
    }

    public function test_manual_signature_flow_books_number_then_publishes_on_upload(): void
    {
        $operator = $this->makeUser();
        $this->actingAs($operator)->post('/skhpp', $this->payload(['nomor_manual' => '55b']))->assertSessionHasNoErrors();
        $skhpp = Skhpp::firstOrFail();

        // Orang lain (bukan pengaju/Admin) tidak boleh mengajukan TTD manual.
        $this->actingAs($this->makeUser())->post("/skhpp/{$skhpp->id}/ttd-manual")->assertForbidden();
        // Belum diajukan manual → unggah ditolak.
        $this->actingAs($operator)->post("/skhpp/{$skhpp->id}/ttd-manual/unggah", [
            'berkas_ttd' => UploadedFile::fake()->create('ttd.pdf', 50, 'application/pdf'), 'tanggal_ttd' => now()->toDateString(),
        ])->assertStatus(422);

        $this->actingAs($operator)->post("/skhpp/{$skhpp->id}/ttd-manual")->assertRedirect();
        $skhpp->refresh();
        $this->assertSame('manual', $skhpp->ttd_mode);
        $this->assertSame('pending', $skhpp->status);
        $nomor = $skhpp->nomor_skhpp;
        $this->assertStringStartsWith('R / 55b / SKHPP / ', $nomor);
        $this->assertDatabaseCount('letter_logs', 0);

        $this->actingAs($operator)->post("/skhpp/{$skhpp->id}/ttd-manual/unggah", [
            'berkas_ttd' => UploadedFile::fake()->create('ttd.pdf', 50, 'application/pdf'),
            'tanggal_ttd' => now()->subDay()->toDateString(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $skhpp->refresh();
        $this->assertSame('approved', $skhpp->status);
        $this->assertSame($nomor, $skhpp->nomor_skhpp);
        $this->assertSame(now()->subDay()->toDateString(), $skhpp->tanggal_skhpp->toDateString());
        $this->assertNull($skhpp->approved_by);
        $this->assertSame($operator->id, (int) $skhpp->signed_uploaded_by);
        Storage::disk('local')->assertExists($skhpp->signed_file);
        $this->assertDatabaseHas('letter_logs', ['full_number' => $nomor, 'sequence' => '55b']);
        $this->assertArrayNotHasKey('signed_file', $skhpp->toArray());
        $this->assertTrue($skhpp->toArray()['has_signed_file']);

        $this->actingAs($operator)->get("/skhpp/{$skhpp->id}/ttd-manual/berkas")->assertOk();
    }

    public function test_choosing_manual_signature_in_the_form_books_the_number_on_save(): void
    {
        $operator = $this->makeUser();
        $this->actingAs($operator)->post('/skhpp', $this->payload(['ttd_mode' => 'manual']))->assertSessionHasNoErrors()->assertRedirect();

        $skhpp = Skhpp::firstOrFail();
        $this->assertSame('manual', $skhpp->ttd_mode);
        $this->assertSame('pending', $skhpp->status);
        $this->assertStringStartsWith('R / 1 / SKHPP / ', $skhpp->nomor_skhpp);

        // Revisi dengan pilihan TTE mengembalikannya ke jalur TTE; tanpa pilihan tidak mengubah apa pun.
        $this->actingAs($operator)->post("/skhpp/{$skhpp->id}/update", $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('manual', $skhpp->fresh()->ttd_mode);
        $this->actingAs($operator)->post("/skhpp/{$skhpp->id}/update", $this->payload(['ttd_mode' => 'tte']))->assertSessionHasNoErrors();
        $this->assertNull($skhpp->fresh()->ttd_mode);
    }

    public function test_choosing_tte_in_the_form_keeps_the_normal_flow(): void
    {
        $this->actingAs($this->makeUser())->post('/skhpp', $this->payload(['ttd_mode' => 'tte']))->assertSessionHasNoErrors();
        $skhpp = Skhpp::firstOrFail();
        $this->assertNull($skhpp->ttd_mode);
        $this->assertNull($skhpp->nomor_skhpp);
    }
}