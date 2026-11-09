<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Pc;
use App\Models\PcAccessRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Akses penyimpanan backup: pemilik, admin, dan rekan yang meminta akses
 * (verifikasi kode WhatsApp -> persetujuan pemilik -> notifikasi WA + aplikasi).
 *
 * Skema dibangun manual seperti MobileApiSecurityTest (migrasi repo belum
 * bisa dijalankan di SQLite).
 */
class BackupAccessTest extends TestCase
{
    private User $owner;
    private User $requester;
    private User $stranger;
    private User $admin;
    private Pc $pc;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('nrp')->nullable();
            $t->string('pangkat')->nullable();
            $t->string('korps')->nullable();
            $t->string('jabatan')->nullable();
            $t->string('phone')->nullable();
            $t->string('avatar')->nullable();
            $t->string('role')->default('personel');
            $t->string('password');
            $t->boolean('is_active')->default(true);
            $t->boolean('can_access_mitra')->default(false);
            $t->boolean('can_access_technical_cash')->default(false);
            $t->boolean('can_manage_koperasi')->default(false);
            $t->boolean('can_access_ibu_beti')->default(false);
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('pcs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id');
            $t->string('pc_name');
            $t->string('hardware_id')->nullable();
            $t->unsignedBigInteger('current_usage')->default(0);
            $t->unsignedBigInteger('max_quota')->default(0);
            $t->timestamps();
        });
        Schema::create('backups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pc_id');
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->string('file_name');
            $t->string('file_path')->nullable();
            $t->unsignedBigInteger('file_size')->default(0);
            $t->string('file_type')->nullable();
            $t->boolean('is_folder')->default(false);
            $t->timestamps();
        });
        Schema::create('app_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable();
            $t->string('role')->nullable();
            $t->string('title');
            $t->text('message');
            $t->string('type')->default('info');
            $t->string('link')->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestamps();
        });

        // Tabel permintaan akses dibuat dari file migrasinya sendiri (sekaligus menguji migrasi).
        (require database_path('migrations/2026_11_09_120000_create_pc_access_requests_table.php'))->up();

        // Pencatat kunjungan web tidak relevan di sini (butuh tabel visitor_logs & geolokasi IP).
        $this->withoutMiddleware(\App\Http\Middleware\LogVisitor::class);
        // Gateway WA (port 3000) dipalsukan; pesan yang terkirim diperiksa lewat Http::recorded().
        Http::fake(['*' => Http::response(['status' => true], 200)]);

        $make = fn (string $name, string $role, ?string $phone) => User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)) . '@test.local',
            'nrp' => (string) random_int(1000, 9999),
            'role' => $role,
            'phone' => $phone,
            'password' => Hash::make('rahasia123'),
            'is_active' => true,
        ]);

        $this->owner = $make('Pemilik Satu', 'personel', '081111111111');
        $this->requester = $make('Peminta Dua', 'personel', '082222222222');
        $this->stranger = $make('Orang Tiga', 'personel', '083333333333');
        $this->admin = $make('Admin Empat', 'admin', '084444444444');

        $this->pc = Pc::create([
            'user_id' => $this->owner->id,
            'pc_name' => 'KHUSUS ADMIN',
            'hardware_id' => 'HW-TEST',
            'current_usage' => 0,
            'max_quota' => 1073741824,
        ]);
        \App\Models\Backup::create([
            'pc_id' => $this->pc->id,
            'file_name' => 'LAPORAN',
            'file_path' => 'folder',
            'is_folder' => true,
            'file_type' => 'folder',
        ]);
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken, 'Accept' => 'application/json'];
    }

    /** Ambil kode 6 digit dari pesan WA terakhir yang dikirim ke nomor tertentu. */
    private function lastOtpSentTo(string $phone62): ?string
    {
        $code = null;
        foreach (Http::recorded() as [$request]) {
            /** @var HttpRequest $request */
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            if (($q['number'] ?? null) === $phone62 && preg_match('/\*(\d{6})\*/', (string) ($q['msg'] ?? ''), $m)) {
                $code = $m[1];
            }
        }
        return $code;
    }

    private function waMessagesTo(string $phone62): array
    {
        $out = [];
        foreach (Http::recorded() as [$request]) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            if (($q['number'] ?? null) === $phone62) {
                $out[] = (string) ($q['msg'] ?? '');
            }
        }
        return $out;
    }

    public function test_full_flow_wa_verification_owner_approval_and_revoke(): void
    {
        $req = $this->bearer($this->requester);
        $own = $this->bearer($this->owner);
        $filesUrl = "/api/mobile/backup/pcs/{$this->pc->id}/files";

        // Belum punya akses.
        $this->getJson($filesUrl, $req)->assertStatus(403);
        $this->getJson('/api/mobile/backup', $req)->assertOk()
            ->assertJsonPath('other_pcs.0.access_state', 'none');

        // 1. Minta akses -> kode dikirim ke WA peminta.
        $res = $this->postJson("/api/mobile/backup/pcs/{$this->pc->id}/access", ['reason' => 'Butuh laporan'], $req)
            ->assertOk()->assertJsonPath('request.status', 'verifying');
        $requestId = $res->json('request.id');
        $this->assertSame('0822*****222', $res->json('request.phone_hint'));
        $code = $this->lastOtpSentTo('6282222222222');
        $this->assertNotNull($code, 'Kode WA harus terkirim ke nomor peminta');

        // Pemilik belum melihat permintaan selama belum diverifikasi.
        $this->getJson('/api/mobile/backup', $own)->assertOk()->assertJsonCount(0, 'incoming_requests');

        // 2. Kode salah ditolak, kode benar meneruskan ke pemilik.
        $wrong = $code === '000000' ? '111111' : '000000';
        $this->postJson("/api/mobile/backup/access/{$requestId}/verify", ['code' => $wrong], $req)->assertStatus(422);
        $this->postJson("/api/mobile/backup/access/{$requestId}/verify", ['code' => $code], $req)
            ->assertOk()->assertJsonPath('request.status', 'pending');

        $this->assertTrue(AppNotification::where('user_id', $this->owner->id)->where('title', 'Permintaan Akses Penyimpanan')->exists());
        $this->assertNotEmpty($this->waMessagesTo('6281111111111'), 'Pemilik harus dapat WA');
        $this->getJson('/api/mobile/backup', $own)->assertOk()
            ->assertJsonCount(1, 'incoming_requests')
            ->assertJsonPath('incoming_requests.0.reason', 'Butuh laporan');

        // Masih menunggu: belum bisa membuka.
        $this->getJson($filesUrl, $req)->assertStatus(403);

        // 3. Hanya pemilik yang boleh memutuskan.
        $this->postJson("/api/mobile/backup/access/{$requestId}/approve", [], $this->bearer($this->stranger))->assertStatus(403);
        $this->postJson("/api/mobile/backup/access/{$requestId}/approve", [], $this->bearer($this->admin))->assertStatus(403);
        $this->postJson("/api/mobile/backup/access/{$requestId}/approve", ['note' => 'Silakan'], $own)
            ->assertOk()->assertJsonPath('request.status', 'approved');

        $this->assertTrue(AppNotification::where('user_id', $this->requester->id)->where('title', 'Akses Penyimpanan Disetujui')->exists());
        $this->assertTrue(collect($this->waMessagesTo('6282222222222'))->contains(fn ($m) => str_contains($m, 'Akses Disetujui')));

        // 4. Rekan bisa melihat (tanpa hak kelola), orang lain tetap ditolak.
        $this->getJson($filesUrl, $req)->assertOk()
            ->assertJsonPath('can_manage', false)
            ->assertJsonPath('items.0.name', 'LAPORAN');
        $this->getJson($filesUrl, $this->bearer($this->stranger))->assertStatus(403);

        // Rekan yang diberi akses tidak bisa mengubah isi (web).
        $this->actingAs($this->requester)
            ->post('/pc-backup/create-folder', ['pc_id' => $this->pc->id, 'folder_name' => 'BARU'])
            ->assertStatus(403);

        // 5. Pemilik mencabut -> akses hilang + notifikasi.
        $this->deleteJson("/api/mobile/backup/access/{$requestId}", [], $own)->assertOk();
        $this->assertSame('revoked', PcAccessRequest::find($requestId)->status);
        $this->assertTrue(AppNotification::where('user_id', $this->requester->id)->where('title', 'Akses Penyimpanan Dicabut')->exists());
        $this->getJson($filesUrl, $req)->assertStatus(403);
    }

    public function test_reject_notifies_requester_and_allows_new_request(): void
    {
        $req = $this->bearer($this->requester);
        $id = $this->postJson("/api/mobile/backup/pcs/{$this->pc->id}/access", [], $req)->json('request.id');
        $this->postJson("/api/mobile/backup/access/{$id}/verify", ['code' => $this->lastOtpSentTo('6282222222222')], $req)->assertOk();

        $this->postJson("/api/mobile/backup/access/{$id}/reject", ['note' => 'Tidak perlu'], $this->bearer($this->owner))
            ->assertOk()->assertJsonPath('request.status', 'rejected');

        $this->assertTrue(AppNotification::where('user_id', $this->requester->id)->where('title', 'Akses Penyimpanan Ditolak')->exists());
        $this->assertTrue(collect($this->waMessagesTo('6282222222222'))->contains(fn ($m) => str_contains($m, 'Akses Ditolak')));
        $this->getJson('/api/mobile/backup', $req)->assertJsonPath('other_pcs.0.access_state', 'rejected');

        // Boleh mengajukan lagi.
        $this->postJson("/api/mobile/backup/pcs/{$this->pc->id}/access", [], $req)->assertOk()
            ->assertJsonPath('request.status', 'verifying');
    }

    public function test_owner_and_admin_access_rules(): void
    {
        $filesUrl = "/api/mobile/backup/pcs/{$this->pc->id}/files";
        $this->getJson($filesUrl, $this->bearer($this->owner))->assertOk()->assertJsonPath('can_manage', true);
        $this->getJson($filesUrl, $this->bearer($this->admin))->assertOk()->assertJsonPath('can_manage', true);

        // Pemilik tidak perlu meminta akses ke penyimpanannya sendiri.
        $this->postJson("/api/mobile/backup/pcs/{$this->pc->id}/access", [], $this->bearer($this->owner))->assertStatus(422);

        // Web: orang lain tidak bisa membuka explorer.
        $this->actingAs($this->stranger)->get("/pc-backup/explore/{$this->pc->id}")->assertStatus(403);
    }

    public function test_requires_whatsapp_number(): void
    {
        $this->requester->update(['phone' => null]);
        $this->postJson("/api/mobile/backup/pcs/{$this->pc->id}/access", [], $this->bearer($this->requester))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Nomor WhatsApp'));
    }

    public function test_too_many_wrong_codes_blocks_verification(): void
    {
        $req = $this->bearer($this->requester);
        $id = $this->postJson("/api/mobile/backup/pcs/{$this->pc->id}/access", [], $req)->json('request.id');
        $code = $this->lastOtpSentTo('6282222222222');
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/api/mobile/backup/access/{$id}/verify", ['code' => $wrong], $req)->assertStatus(422);
        }
        // Kode benar pun ditolak setelah 5 kali salah.
        $this->postJson("/api/mobile/backup/access/{$id}/verify", ['code' => $code], $req)->assertStatus(422);
        $this->assertSame('verifying', PcAccessRequest::find($id)->status);
    }
}
