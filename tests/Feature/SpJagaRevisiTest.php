<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\SpJaga;
use App\Models\SpJagaPerwira;
use App\Support\WaktuTte;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Security\SecuritySchema;
use Tests\TestCase;

/** Revisi SP Jaga bertanda tangan: hanya pengaju, OTP WhatsApp, TTE Pasops, status revisi di QR. */
class SpJagaRevisiTest extends TestCase
{
    use SecuritySchema;

    protected function setUp(): void
    {
        parent::setUp(); // SecuritySchema dipasang otomatis (setUpSecuritySchema)
        (require database_path('migrations/2026_08_31_110000_create_sp_jagas_tables.php'))->up();
        (require database_path('migrations/2026_11_18_100000_add_revision_to_sp_jagas_table.php'))->up();
        (require database_path('migrations/2026_08_07_120000_create_app_notifications_table.php'))->up();
        // Halaman verifikasi QR memeriksa kode SKHPP lebih dulu.
        (require database_path('migrations/2026_08_05_130000_create_skhpps_and_skhpp_members_tables.php'))->up();
    }

    private function spTerbit(int $pembuatId, string $kode = 'TTE-SPJAGA-UJI-ASLI'): SpJaga
    {
        $sp = SpJaga::create([
            'nomor_sprin' => 'Sprin/ 50 /XI/2026', 'nomor_urut' => '50', 'bulan' => 11, 'tahun' => 2026, 'bulan_romawi' => 'XI',
            'tmt_mulai' => '2026-11-01', 'tmt_selesai' => '2026-11-30', 'tanggal_surat' => '2026-10-28',
            'perwira_tertua_nama' => 'Perwira Uji', 'perwira_tertua_pangkat_nrp' => 'Kapten Laut (P) NRP 1/P',
            'total_personel_count' => 1, 'total_personel_terbilang' => 'Satu', 'ttd_type' => 'tte', 'status' => 'published',
            'verification_code' => $kode, 'approved_at' => '2026-10-28 09:15:00', 'created_by' => $pembuatId,
        ]);
        SpJagaPerwira::create(['sp_jaga_id' => $sp->id, 'no_urut' => 1, 'nama' => 'Perwira Uji', 'tgl_1' => '1']);
        return $sp;
    }

    private function payloadRevisi(): array
    {
        return [
            'alasan_revisi' => 'Pergantian personel jaga',
            'tmt_mulai' => '2026-11-01', 'tmt_selesai' => '2026-11-30',
            'perwiras' => [['nama' => 'Perwira Pengganti', 'pangkat_korps' => 'Lettu Laut (P)', 'nrp' => '2/P', 'tgl_1' => '1']],
            'anggotas' => [['tanggal_list_text' => '1, 5', 'anggota_items' => [['nama' => 'Anggota A', 'role_jaga' => 'ANGGOTA']]]],
            // Diabaikan server: nomor & periode terkunci ke SP asli.
            'nomor_urut' => '99', 'bulan' => 3, 'tahun' => 2030,
        ];
    }

    /** Ambil kode OTP dari pesan WhatsApp terakhir yang dikirim ke gateway (dipalsukan). */
    private function kodeOtpTerakhir(): string
    {
        $pesan = collect(Http::recorded())->map(fn ($r) => $r[0]->data()['msg'] ?? '')->filter(fn ($m) => str_contains($m, 'KODE VERIFIKASI REVISI'))->last();
        $this->assertNotNull($pesan, 'Pesan OTP tidak terkirim.');
        preg_match('/\*(\d{6})\*/', $pesan, $m);
        return $m[1];
    }

    public function test_only_the_submitter_can_start_a_revision(): void
    {
        $pengaju = $this->makeUser(['can_manage_sp_jaga' => true, 'phone' => '081200000001']);
        $sp = $this->spTerbit($pengaju->id);

        $lain = $this->makeUser(['can_manage_sp_jaga' => true, 'phone' => '081200000002']);
        $this->actingAs($lain)->postJson("/sp-jaga/{$sp->id}/revisi/otp")->assertForbidden();
        $this->actingAs($this->makeUser(['phone' => '0812']))->postJson("/sp-jaga/{$sp->id}/revisi/otp")->assertForbidden(); // tanpa izin SP Jaga
    }

    public function test_revision_requires_otp_then_is_signed_immediately_and_keeps_the_number(): void
    {
        $pengaju = $this->makeUser(['can_manage_sp_jaga' => true, 'phone' => '081200000001']);
        $sp = $this->spTerbit($pengaju->id);

        $pasops = $this->makeUser(['role' => 'pasops']);

        // Tanpa OTP: form & simpan ditolak.
        $this->actingAs($pengaju)->get("/sp-jaga/{$sp->id}/revisi")->assertRedirect('/sp-jaga');
        $this->actingAs($pengaju)->post("/sp-jaga/{$sp->id}/revisi", $this->payloadRevisi())->assertForbidden();

        $this->actingAs($pengaju)->postJson("/sp-jaga/{$sp->id}/revisi/otp")->assertOk()->assertJsonPath('ok', true);
        $kode = $this->kodeOtpTerakhir();

        $salah = $kode === '000000' ? '111111' : '000000';
        $this->actingAs($pengaju)->postJson("/sp-jaga/{$sp->id}/revisi/otp/verifikasi", ['kode' => $salah])->assertStatus(422);
        $this->actingAs($pengaju)->postJson("/sp-jaga/{$sp->id}/revisi/otp/verifikasi", ['kode' => $kode])
            ->assertOk()->assertJsonPath('redirect', route('sp-jaga.revisi', $sp->id));

        $this->actingAs($pengaju)->get("/sp-jaga/{$sp->id}/revisi")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('SpJaga/Edit')->where('revisi.revisi_ke', 1)->where('revisi.nomor_sprin', 'Sprin/ 50 /XI/2026'));

        $this->actingAs($pengaju)->post("/sp-jaga/{$sp->id}/revisi", $this->payloadRevisi())->assertSessionHasNoErrors()->assertRedirect('/sp-jaga');

        $rev = SpJaga::where('revisi_dari_id', $sp->id)->firstOrFail();
        // Lolos OTP + simpan = langsung disahkan dengan TTE Pasops hari ini.
        $this->assertSame(1, (int) $rev->revisi_ke);
        $this->assertSame('published', $rev->status);
        $this->assertSame('tte', $rev->ttd_type);
        $this->assertNotNull($rev->approved_at);
        $this->assertSame(now()->toDateString(), $rev->tanggal_surat->toDateString());
        $this->assertSame('Sprin/ 50 /XI/2026', $rev->nomor_sprin);
        $this->assertSame('50', (string) $rev->nomor_urut);
        $this->assertSame(11, (int) $rev->bulan);
        $this->assertNotSame($sp->verification_code, $rev->verification_code);
        $this->assertTrue(AppNotification::where('role', 'pasops')->where('title', 'TTE Revisi SP Jaga')->exists());

        // Izin OTP hanya sekali pakai: simpan lagi tanpa OTP baru ditolak.
        $this->actingAs($pengaju)->post("/sp-jaga/{$sp->id}/revisi", $this->payloadRevisi())->assertForbidden();
        $this->assertSame(1, SpJaga::where('revisi_dari_id', $sp->id)->count());

        // "Setujui & TTE" pada SP yang sudah terbit tidak mengubah waktu TTE.
        $waktu = $rev->approved_at->toDateTimeString();
        $this->travel(2)->hours();
        $this->actingAs($pasops)->post("/sp-jaga/{$rev->id}/approve-tte");
        $this->assertSame($waktu, $rev->fresh()->approved_at->toDateTimeString());
        // Daftar: revisi tampil di bawah SP asli, bukan baris tersendiri.
        $this->actingAs($pengaju)->get('/sp-jaga')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('spJagas.total', 1)
            ->where('spJagas.data.0.id', $sp->id)
            ->where('spJagas.data.0.revisions.0.revisi_ke', 1)
            ->where('spJagas.data.0.can_revise', true));

        // QR revisi: status revisi ke-1. QR dokumen asli: sudah direvisi.
        $this->get('/verify-doc/' . $rev->verification_code)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('doc.revisi.jenis', 'revisi')->where('doc.revisi.revisi_ke', 1)
            ->where('doc.revisi.alasan', 'Pergantian personel jaga'));
        $this->get('/verify-doc/' . $sp->verification_code)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('doc.revisi.jenis', 'asli_digantikan')->where('doc.revisi.pengganti_ke', 1)
            ->where('doc.waktu_tte', '28 Oktober 2026, pukul 09.15 WIB'));
    }

    public function test_otp_locks_after_too_many_wrong_codes(): void
    {
        $pengaju = $this->makeUser(['can_manage_sp_jaga' => true, 'phone' => '081200000001']);
        $sp = $this->spTerbit($pengaju->id);
        $this->actingAs($pengaju)->postJson("/sp-jaga/{$sp->id}/revisi/otp")->assertOk();
        $kode = $this->kodeOtpTerakhir();
        $salah = $kode === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($pengaju)->postJson("/sp-jaga/{$sp->id}/revisi/otp/verifikasi", ['kode' => $salah])->assertStatus(422);
        }
        // Kode yang benar pun tidak berlaku lagi.
        $this->actingAs($pengaju)->postJson("/sp-jaga/{$sp->id}/revisi/otp/verifikasi", ['kode' => $kode])->assertStatus(422);
    }

    public function test_user_without_whatsapp_number_gets_a_clear_message(): void
    {
        $pengaju = $this->makeUser(['can_manage_sp_jaga' => true, 'phone' => null]);
        $sp = $this->spTerbit($pengaju->id);
        $this->actingAs($pengaju)->postJson("/sp-jaga/{$sp->id}/revisi/otp")->assertStatus(422)->assertJsonPath('ok', false);
    }

    public function test_personal_notification_without_recipient_goes_to_admin_not_everyone(): void
    {
        $n = AppNotification::notify(null, null, 'Pribadi', 'isi');
        $this->assertSame('admin', $n->role);

        $siaran = AppNotification::notify(null, null, 'Siaran', 'isi', 'info', null, null, null, true);
        $this->assertNull($siaran->role);
        $this->assertNull($siaran->user_id);
    }

    public function test_tte_time_text_uses_real_time_and_omits_midnight_for_date_only(): void
    {
        $this->assertSame('28 Oktober 2026, pukul 09.15 WIB', WaktuTte::teks('2026-10-28 09:15:00'));
        $this->assertSame('28 Oktober 2026', WaktuTte::teks(null, '2026-10-28'));
        $this->assertNull(WaktuTte::teks(null, null));
    }

    public function test_signed_sp_cannot_be_edited_or_deleted_but_pending_one_can(): void
    {
        $admin = $this->makeUser(['role' => 'admin', 'can_manage_sp_jaga' => true]);
        $sp = $this->spTerbit($admin->id);

        $this->actingAs($admin)->get("/sp-jaga/{$sp->id}/edit")->assertRedirect('/sp-jaga');
        $this->actingAs($admin)->put("/sp-jaga/{$sp->id}", $this->payloadRevisi() + ['tanggal_surat' => '2026-11-01', 'ttd_type' => 'tte'])
            ->assertRedirect('/sp-jaga');
        $this->assertSame('Perwira Uji', $sp->fresh()->perwiras()->first()->nama);

        $this->actingAs($admin)->delete("/sp-jaga/{$sp->id}")->assertRedirect();
        $this->assertNotNull(SpJaga::find($sp->id));

        // SP yang belum ditandatangani tetap bisa diedit & dihapus.
        $draf = $this->spTerbit($admin->id, 'TTE-SPJAGA-UJI-DRAF');
        $draf->update(['status' => 'pending_signature']);
        $this->actingAs($admin)->get("/sp-jaga/{$draf->id}/edit")->assertOk();
        $this->actingAs($admin)->delete("/sp-jaga/{$draf->id}")->assertRedirect();
        $this->assertNull(SpJaga::find($draf->id));
    }

    public function test_sp_edited_after_tte_before_this_feature_becomes_revision_one(): void
    {
        $admin = $this->makeUser(['role' => 'admin', 'can_manage_sp_jaga' => true]);

        // Diedit setelah TTE: daftar jaga dibuat ulang sesudah approved_at.
        $diedit = $this->spTerbit($admin->id, 'TTE-SPJAGA-UJI-EDIT');
        // Tidak diedit: daftar jaga dibuat sebelum TTE.
        $utuh = $this->spTerbit($admin->id, 'TTE-SPJAGA-UJI-UTUH');
        $utuh->update(['approved_at' => now()->addMinute()]);

        $migrasi = require database_path('migrations/2026_11_18_110000_convert_post_tte_edits_to_sp_jaga_revisions.php');
        $migrasi->up();
        $migrasi->up(); // aman dijalankan ulang

        $rev = SpJaga::where('revisi_dari_id', $diedit->id)->get();
        $this->assertCount(1, $rev);
        $rev = $rev->first();
        $this->assertSame(1, (int) $rev->revisi_ke);
        $this->assertSame('published', $rev->status);
        $this->assertSame($diedit->nomor_sprin, $rev->nomor_sprin);
        $this->assertNotSame($diedit->verification_code, $rev->verification_code);
        $this->assertSame('Perwira Uji', $rev->perwiras()->first()->nama);
        $this->assertSame(1, $diedit->perwiras()->count()); // SP asli tetap utuh
        $this->assertSame(0, SpJaga::where('revisi_dari_id', $utuh->id)->count());

        // QR SP asli kini menunjukkan sudah direvisi.
        $this->get('/verify-doc/TTE-SPJAGA-UJI-EDIT')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('doc.revisi.jenis', 'asli_digantikan')->where('doc.revisi.pengganti_ke', 1));
    }
}