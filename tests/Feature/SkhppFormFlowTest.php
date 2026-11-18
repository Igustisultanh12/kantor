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
        (require database_path('migrations/2026_11_18_120000_add_lampiran_title_fields_to_skhpps_table.php'))->up();
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
        Schema::create('letters', function (Blueprint $t) {
            $t->id(); $t->string('type'); $t->foreignId('category_id')->nullable(); $t->foreignId('sub_category_id')->nullable(); $t->string('security_level')->nullable();
            $t->string('letter_number'); $t->string('subject'); $t->date('date')->nullable(); $t->string('issuer')->nullable(); $t->string('file_path'); $t->timestamps();
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
            'satuan' => 'Denma Kodaeral V',
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

    public function test_verification_page_shows_real_tte_time_not_midnight(): void
    {
        // Halaman verifikasi memeriksa kode TTD dokumen lebih dulu.
        Schema::create('signature_requests', function (Blueprint $t) {
            $t->id(); $t->string('verification_code')->nullable(); $t->timestamps();
        });
        $this->actingAs($this->makeUser())->post('/skhpp', $this->payload())->assertSessionHasNoErrors();
        $skhpp = Skhpp::firstOrFail();
        $this->travelTo(now()->setTime(14, 37));
        $this->actingAs($this->makeUser(['role' => 'komandan']))->post("/skhpp/{$skhpp->id}/approve");

        $this->get('/verify-skhpp/' . $skhpp->fresh()->verification_code)->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p->where('skhpp.waktu_tte', fn ($t) => str_ends_with($t, 'pukul 14.37 WIB')));
    }

    public function test_without_manual_number_the_next_automatic_number_is_used(): void
    {
        $this->actingAs($this->makeUser())->post('/skhpp', $this->payload())->assertSessionHasNoErrors();
        $skhpp = Skhpp::firstOrFail();
        // Nomor otomatis langsung dibooking saat diajukan (tercatat di Agenda Surat, belum ber-PDF).
        $this->assertStringStartsWith('R / 1 / SKHPP / ', $skhpp->nomor_skhpp);
        $this->assertDatabaseHas('letter_logs', ['full_number' => $skhpp->nomor_skhpp, 'is_archived' => false]);

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
        // Sudah masuk Agenda Surat, menunggu PDF.
        $this->assertDatabaseHas('letter_logs', ['full_number' => $nomor, 'sequence' => '55b', 'is_archived' => false]);
        $this->assertDatabaseCount('letters', 0);

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
        // Agenda Surat ikut terisi: entri yang sama kini ber-PDF dan arsipnya tersimpan.
        $this->assertDatabaseCount('letter_logs', 1);
        $this->assertDatabaseHas('letter_logs', ['full_number' => $nomor, 'sequence' => '55b', 'is_archived' => true]);
        $arsip = DB::table('letters')->where('letter_number', $nomor)->first();
        $this->assertNotNull($arsip);
        Storage::disk('local')->assertExists($arsip->file_path);
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
        $this->assertStringStartsWith('R / 1 / SKHPP / ', $skhpp->nomor_skhpp);
    }

    public function test_pending_tte_is_in_the_agenda_and_is_archived_automatically_when_signed(): void
    {
        $this->actingAs($this->makeUser())->post('/skhpp', $this->payload(['nomor_manual' => '40']))->assertSessionHasNoErrors();
        $skhpp = Skhpp::firstOrFail();
        $this->assertDatabaseCount('letter_logs', 1);
        $this->assertDatabaseHas('letter_logs', ['full_number' => $skhpp->nomor_skhpp, 'sequence' => '40', 'is_archived' => false]);

        // Komandan mengganti nomor saat TTE: entri yang sama diperbarui, lalu langsung ber-PDF.
        $this->actingAs($this->makeUser(['role' => 'komandan']))->post("/skhpp/{$skhpp->id}/approve", ['custom_nomor_urut' => 41])->assertRedirect();

        $skhpp->refresh();
        $this->assertStringStartsWith('R / 41 / SKHPP / ', $skhpp->nomor_skhpp);
        $this->assertDatabaseCount('letter_logs', 1);
        $this->assertDatabaseHas('letter_logs', ['full_number' => $skhpp->nomor_skhpp, 'is_archived' => true]);
        $arsip = DB::table('letters')->where('letter_number', $skhpp->nomor_skhpp)->first();
        $this->assertNotNull($arsip);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($arsip->file_path));
    }

    public function test_sync_command_books_waiting_and_archives_signed_legacy_skhpp(): void
    {
        $operator = $this->makeUser();
        $this->actingAs($operator)->post('/skhpp', $this->payload(['nama' => 'Lama Menunggu']))->assertSessionHasNoErrors();
        $this->actingAs($operator)->post('/skhpp', $this->payload(['nama' => 'Lama Terbit']))->assertSessionHasNoErrors();
        // Keadaan data lama: belum ada di Agenda Surat; yang menunggu belum bernomor, yang terbit bernomor.
        DB::table('letter_logs')->delete();
        Skhpp::where('nama', 'Lama Menunggu')->update(['nomor_skhpp' => null, 'nomor_urut' => null]);
        Skhpp::where('nama', 'Lama Terbit')->update(['status' => 'approved', 'nomor_skhpp' => 'R / 250 / SKHPP / VIII / 2026', 'nomor_urut' => 250, 'tanggal_skhpp' => '2026-08-20']);

        $this->artisan('skhpp:sinkron-agenda')->assertSuccessful();
        $this->assertDatabaseCount('letter_logs', 0); // pratinjau tidak mengubah apa pun

        $this->artisan('skhpp:sinkron-agenda --simpan')->assertSuccessful();
        $menunggu = Skhpp::where('nama', 'Lama Menunggu')->first();
        $this->assertNotNull($menunggu->nomor_skhpp);
        $this->assertDatabaseHas('letter_logs', ['full_number' => $menunggu->nomor_skhpp, 'is_archived' => false]);
        $this->assertDatabaseHas('letter_logs', ['full_number' => 'R / 250 / SKHPP / VIII / 2026', 'is_archived' => true]);
        $this->assertDatabaseCount('letters', 1);

        $this->artisan('skhpp:sinkron-agenda --simpan')->assertSuccessful();
        $this->assertDatabaseCount('letter_logs', 2); // aman dijalankan ulang
        $this->assertDatabaseCount('letters', 1);
    }

    public function test_backfill_adds_waiting_manual_skhpp_to_the_agenda_once(): void
    {
        $this->actingAs($this->makeUser())->post('/skhpp', $this->payload(['ttd_mode' => 'manual']))->assertSessionHasNoErrors();
        $nomor = Skhpp::firstOrFail()->nomor_skhpp;
        DB::table('letter_logs')->delete(); // keadaan sebelum sinkronisasi ada

        $migrasi = require database_path('migrations/2026_11_16_180000_backfill_manual_skhpp_into_letter_logs.php');
        $migrasi->up();
        $migrasi->up();

        $this->assertDatabaseCount('letter_logs', 1);
        $this->assertDatabaseHas('letter_logs', ['full_number' => $nomor, 'category_id' => 1, 'is_archived' => false]);
    }

    public function test_revising_manual_skhpp_number_updates_the_same_agenda_entry(): void
    {
        $operator = $this->makeUser();
        $this->actingAs($operator)->post('/skhpp', $this->payload(['ttd_mode' => 'manual', 'nomor_manual' => '70']))->assertSessionHasNoErrors();
        $skhpp = Skhpp::firstOrFail();
        $this->assertDatabaseHas('letter_logs', ['full_number' => $skhpp->nomor_skhpp, 'sequence' => '70']);

        $this->actingAs($operator)->post("/skhpp/{$skhpp->id}/update", $this->payload(['ttd_mode' => 'manual', 'nomor_manual' => '71']))->assertSessionHasNoErrors();

        $skhpp->refresh();
        $this->assertStringStartsWith('R / 71 / SKHPP / ', $skhpp->nomor_skhpp);
        $this->assertDatabaseCount('letter_logs', 1);
        $this->assertDatabaseHas('letter_logs', ['full_number' => $skhpp->nomor_skhpp, 'sequence' => '71', 'is_archived' => false]);
    }

    public function test_handwritten_number_is_left_blank_and_skips_the_agenda_on_tte(): void
    {
        $this->actingAs($this->makeUser())->post('/skhpp', $this->payload(['nomor_tulis_tangan' => true, 'nomor_manual' => '99']))->assertSessionHasNoErrors();
        $skhpp = Skhpp::firstOrFail();
        $this->assertTrue($skhpp->nomor_tulis_tangan);
        $this->assertNull($skhpp->nomor_skhpp);
        $this->assertNull($skhpp->nomor_manual);

        $this->actingAs($this->makeUser(['role' => 'komandan']))->post("/skhpp/{$skhpp->id}/approve", ['custom_nomor_urut' => 5])->assertRedirect();

        $skhpp->refresh();
        $this->assertSame('approved', $skhpp->status);
        $this->assertNull($skhpp->nomor_skhpp);
        $this->assertNull($skhpp->nomor_urut);
        $this->assertSame((int) date('Y'), (int) $skhpp->tahun);
        $this->assertDatabaseCount('letter_logs', 0);
    }

    public function test_handwritten_number_with_manual_signature_releases_booked_number(): void
    {
        $operator = $this->makeUser();
        $this->actingAs($operator)->post('/skhpp', $this->payload(['ttd_mode' => 'manual', 'nomor_manual' => '70']))->assertSessionHasNoErrors();
        $skhpp = Skhpp::firstOrFail();
        $this->assertDatabaseCount('letter_logs', 1);

        // Diubah menjadi nomor tulis tangan: nomor yang sempat dibooking dilepas dari Agenda Surat.
        $this->actingAs($operator)->post("/skhpp/{$skhpp->id}/update", $this->payload(['ttd_mode' => 'manual', 'nomor_tulis_tangan' => true]))->assertSessionHasNoErrors();
        $skhpp->refresh();
        $this->assertNull($skhpp->nomor_skhpp);
        $this->assertSame('manual', $skhpp->ttd_mode);
        $this->assertDatabaseCount('letter_logs', 0);

        $this->actingAs($operator)->post("/skhpp/{$skhpp->id}/ttd-manual/unggah", [
            'berkas_ttd' => UploadedFile::fake()->create('ttd.pdf', 50, 'application/pdf'), 'tanggal_ttd' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $skhpp->refresh();
        $this->assertSame('approved', $skhpp->status);
        $this->assertNull($skhpp->nomor_skhpp);
        $this->assertDatabaseCount('letter_logs', 0);
        $this->assertDatabaseCount('letters', 0);

        // Revisi lama tanpa field nomor_tulis_tangan tidak mematikan pilihan ini.
        $this->assertTrue($skhpp->nomor_tulis_tangan);
    }

    public function test_unit_and_short_purpose_build_the_attachment_title(): void
    {
        $this->actingAs($this->makeUser())->post('/skhpp', $this->payload([
            'satuan' => 'Pomal Kodaeral V',
            'kegiatan' => 'Seleksi Diktukpa LVI/56 TA. 2026',
            'peruntukan' => 'dalam rangka melengkapi administrasi seleksi Diktukpa LVI/56 TA. 2026',
            'has_pengikut' => true,
            'members' => [['nama' => 'Ani', 'pangkat_nrp_nik' => 'Serda NRP 1', 'jabatan' => 'Anggota']],
        ]))->assertSessionHasNoErrors();

        $skhpp = Skhpp::firstOrFail();
        $this->assertSame('DAFTAR NAMA PERSONEL POMAL KODAERAL V YANG MELAKSANAKAN SC SELEKSI DIKTUKPA LVI/56 TA. 2026', $skhpp->judulLampiran());

        // Satuan wajib bila ada pengikut.
        $this->actingAs($this->makeUser())->post('/skhpp', $this->payload([
            'has_pengikut' => true, 'members' => [['nama' => 'Ani', 'pangkat_nrp_nik' => '1', 'jabatan' => 'A']],
        ]))->assertSessionHasErrors('satuan');
    }

    public function test_legacy_rows_derive_attachment_title_from_purpose(): void
    {
        $lama = new Skhpp(['satuan' => 'Denma Kodaeral V', 'peruntukan' => 'dalam rangka melengkapi administrasi seleksi Dikmaba TA. 2026.']);
        $this->assertSame('DAFTAR NAMA PERSONEL DENMA KODAERAL V YANG MELAKSANAKAN SC SELEKSI DIKMABA TA. 2026', $lama->judulLampiran());
        $this->assertSame('DAFTAR NAMA-NAMA ANGGOTA PENGIKUT', (new Skhpp(['peruntukan' => 'uji']))->judulLampiran());
    }
}