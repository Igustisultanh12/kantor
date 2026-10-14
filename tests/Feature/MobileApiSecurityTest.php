<?php

namespace Tests\Feature;

use App\Models\SpJaga;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pengujian keamanan & kontrak API mobile.
 *
 * Catatan: migrasi bawaan repo belum dapat dijalankan di SQLite (ada tabel
 * yang dibuat dua kali, lihat docs/SISINDEN_ANDROID_AUDIT.md), sehingga test
 * ini membangun skema minimal yang dibutuhkan secara eksplisit.
 */
class MobileApiSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Turnstile nonaktif secara default di test (diaktifkan per-test bila perlu).
        config(['services.turnstile.secret_key' => null]);

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
            $t->string('activation_token')->nullable();
            $t->boolean('must_change_password')->default(false);
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
        Schema::create('cashes', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->string('description');
            $t->decimal('debit', 15, 2)->default(0);
            $t->decimal('credit', 15, 2)->default(0);
            $t->decimal('balance', 15, 2)->default(0);
            $t->text('receipt_path')->nullable();
            $t->timestamps();
        });
        Schema::create('attendances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id');
            $t->date('attendance_date');
            $t->string('time_in')->nullable();
            $t->string('status')->default('hadir');
            $t->string('ip_address')->nullable();
            $t->string('device')->nullable();
            $t->string('user_agent')->nullable();
            $t->double('latitude')->nullable();
            $t->double('longitude')->nullable();
            $t->string('location_name')->nullable();
            $t->text('notes')->nullable();
            $t->string('photo')->nullable();
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
        Schema::create('sp_jagas', function (Blueprint $t) {
            $t->id();
            $t->string('nomor_sprin')->nullable();
            $t->string('nomor_urut')->nullable();
            $t->integer('bulan');
            $t->integer('tahun');
            $t->string('bulan_romawi')->nullable();
            $t->date('tmt_mulai')->nullable();
            $t->date('tmt_selesai')->nullable();
            $t->date('tanggal_surat')->nullable();
            $t->foreignId('perwira_tertua_user_id')->nullable();
            $t->string('perwira_tertua_nama')->nullable();
            $t->string('perwira_tertua_pangkat_nrp')->nullable();
            $t->string('perwira_tertua_jabatan')->nullable();
            $t->integer('total_personel_count')->default(0);
            $t->string('total_personel_terbilang')->nullable();
            $t->string('ttd_type')->default('tte');
            $t->string('status')->default('draft');
            $t->string('signed_file_path')->nullable();
            $t->string('verification_code')->nullable();
            $t->foreignId('penandatangan_user_id')->nullable();
            $t->string('penandatangan_nama')->nullable();
            $t->string('penandatangan_pangkat_nrp')->nullable();
            $t->string('penandatangan_jabatan')->nullable();
            $t->foreignId('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('created_by');
            $t->timestamps();
        });
        Schema::create('sp_jaga_perwiras', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sp_jaga_id');
            $t->foreignId('user_id')->nullable();
            $t->integer('no_urut')->default(1);
            $t->string('nama');
            $t->string('pangkat_korps')->nullable();
            $t->string('nrp')->nullable();
            foreach (range(1, 7) as $i) {
                $t->string("tgl_{$i}")->nullable();
            }
            $t->json('tgl_list')->nullable();
            $t->timestamps();
        });
        Schema::create('sp_jaga_anggotas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sp_jaga_id');
            $t->integer('divisi_no')->default(1);
            $t->string('tanggal_list_text')->nullable();
            $t->json('tanggal_array')->nullable();
            $t->json('anggota_items')->nullable();
            $t->timestamps();
        });
        Schema::create('live_chat_threads', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('user_id');
            $t->foreignId('operator_id')->nullable();
            $t->string('subject')->default('Pusat Komunikasi Dinas');
            $t->string('status')->default('OPEN');
            $t->dateTime('last_message_at')->nullable();
            $t->unsignedInteger('unread_admin')->default(0);
            $t->unsignedInteger('unread_user')->default(0);
            $t->timestamps();
        });
        Schema::create('live_chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('thread_id');
            $t->string('sender_type');
            $t->unsignedBigInteger('sender_id')->nullable();
            $t->string('sender_name')->default('Operator Dinas');
            $t->text('message')->nullable();
            $t->json('attachments')->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestamps();
        });
        Schema::create('signature_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id');
            $t->string('subject')->nullable();
            $t->string('file_path')->nullable();
            $t->string('status')->default('pending');
            $t->text('note')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Di produksi setiap request adalah proses baru; di test, guard auth tetap
     * hidup antar-request. Reset agar perilaku identik dengan produksi.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();
        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    public function json($method, $uri, array $data = [], array $headers = [], $options = 0)
    {
        $this->app['auth']->forgetGuards();
        return parent::json($method, $uri, $data, $headers, $options);
    }

    private function makeUser(array $attrs = []): User
    {
        static $n = 0;
        $n++;
        return User::forceCreate(array_merge([
            'name' => "Personel {$n}",
            'email' => "p{$n}@example.test",
            'nrp' => "NRP{$n}",
            'role' => 'personel',
            'password' => Hash::make('rahasia123'),
            'is_active' => true,
        ], $attrs));
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_data_endpoints_reject_requests_without_token(): void
    {
        foreach (['/api/mobile/cash', '/api/mobile/users', '/api/mobile/me', '/api/mobile/attendances', '/api/mobile/duty-roster'] as $uri) {
            $this->getJson($uri)->assertStatus(401)->assertJson(['code' => 'unauthenticated']);
        }
        $this->postJson('/api/mobile/users', ['name' => 'x', 'role' => 'admin'])->assertStatus(401);
        $this->deleteJson('/api/mobile/cash/1')->assertStatus(401);
    }

    public function test_forged_identity_headers_are_ignored(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $this->withHeaders(['X-User-Id' => (string) $admin->id])
            ->getJson('/api/mobile/users')
            ->assertStatus(401);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withToken('1|bukan-token-asli')->getJson('/api/mobile/me')->assertStatus(401);
    }

    public function test_login_returns_permissions_and_working_token(): void
    {
        $this->makeUser(['nrp' => '12345', 'role' => 'personel']);

        $res = $this->postJson('/api/mobile/login', ['username' => '12345', 'password' => 'rahasia123'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('user.can_access_cash', false);

        $perms = $res->json('user.permissions');
        $this->assertContains('duty_roster.view', $perms);
        $this->assertNotContains('cash.view', $perms);
        $this->assertNotContains('users.manage', $perms);

        $this->withToken($res->json('token'))->getJson('/api/mobile/me')
            ->assertOk()->assertJsonPath('user.nrp', '12345');
    }

    public function test_login_error_does_not_reveal_whether_user_exists(): void
    {
        $this->makeUser(['nrp' => '777']);
        $a = $this->postJson('/api/mobile/login', ['username' => '777', 'password' => 'salah'])->assertStatus(401)->json('message');
        $b = $this->postJson('/api/mobile/login', ['username' => 'tidakada', 'password' => 'salah'])->assertStatus(401)->json('message');
        $this->assertSame($a, $b);
    }

    public function test_inactive_user_cannot_login_or_use_token(): void
    {
        $u = $this->makeUser(['nrp' => '888']);
        $token = $this->tokenFor($u);
        $u->forceFill(['is_active' => false])->save();

        $this->withToken($token)->getJson('/api/mobile/me')->assertStatus(403);
        $this->postJson('/api/mobile/login', ['username' => '888', 'password' => 'rahasia123'])->assertStatus(403);
    }

    public function test_permissions_are_enforced_by_backend(): void
    {
        $personel = $this->makeUser(['role' => 'personel']);
        $token = $this->tokenFor($personel);

        $this->withToken($token)->getJson('/api/mobile/cash')
            ->assertStatus(403)->assertJsonPath('required_permission', 'cash.view');
        $this->withToken($token)->getJson('/api/mobile/users')->assertStatus(403);
        $this->withToken($token)->postJson('/api/mobile/users', [
            'name' => 'Penyusup', 'email' => 'x@x.test', 'nrp' => '1', 'role' => 'admin', 'password' => 'rahasia123',
        ])->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'x@x.test']);
    }

    public function test_admin_can_access_cash_and_validation_applies(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $token = $this->tokenFor($admin);

        $this->withToken($token)->getJson('/api/mobile/cash')->assertOk()->assertJsonPath('status', 'success');
        $this->withToken($token)->postJson('/api/mobile/cash', ['description' => 'x'])->assertStatus(422);
        $this->withToken($token)->postJson('/api/mobile/cash', [
            'date' => '2026-10-01', 'description' => 'Saldo awal', 'type' => 'masuk', 'amount' => 1,
        ])->assertOk();
        $this->withToken($token)->postJson('/api/mobile/cash', [
            'date' => '2026-10-02', 'description' => 'Belanja ATK', 'type' => 'keluar', 'amount' => 0.5,
        ])->assertOk();
        $this->withToken($token)->getJson('/api/mobile/cash')->assertJsonPath('balance', 0.5);
    }

    public function test_permission_change_by_admin_applies_on_next_request(): void
    {
        $u = $this->makeUser(['role' => 'personel']);
        $token = $this->tokenFor($u);
        $this->withToken($token)->getJson('/api/mobile/me')->assertOk();
        $this->assertNotContains('technical_cash.view', $this->withToken($token)->getJson('/api/mobile/me')->json('user.permissions'));

        $u->forceFill(['can_access_technical_cash' => true])->save();
        $this->assertContains('technical_cash.view', $this->withToken($token)->getJson('/api/mobile/me')->json('user.permissions'));
    }

    public function test_anggota_sintel_gets_restricted_menu(): void
    {
        $sintel = $this->makeUser(['role' => 'anggotasintel']);
        $perms = $this->withToken($this->tokenFor($sintel))->getJson('/api/mobile/me')->json('user.permissions');
        $this->assertContains('duty_roster.view', $perms);
        $this->assertContains('attendance.create', $perms);
        $this->assertNotContains('letter_logs.view', $perms);
        $this->assertNotContains('skhpp.view', $perms);
    }

    public function test_attendance_is_recorded_for_token_owner_only(): void
    {
        $victim = $this->makeUser();
        $actor = $this->makeUser();

        $this->withToken($this->tokenFor($actor))
            ->withHeaders(['X-User-Id' => (string) $victim->id])
            ->postJson('/api/mobile/attendances', ['status' => 'hadir', 'user_id' => $victim->id])
            ->assertOk();

        $this->assertSame(1, DB::table('attendances')->where('user_id', $actor->id)->count());
        $this->assertSame(0, DB::table('attendances')->where('user_id', $victim->id)->count());

        // Tanggal presensi harus dikirim sebagai Y-m-d lokal (bukan ISO UTC yang bergeser sehari).
        $this->withToken($this->tokenFor($actor))->getJson('/api/mobile/attendances')
            ->assertOk()
            ->assertJsonPath('today_attendance.attendance_date', now()->toDateString());
    }

    public function test_activation_requires_valid_token(): void
    {
        $u = $this->makeUser(['nrp' => '999', 'is_active' => false, 'activation_token' => 'TOKEN-ABC']);
        $oldHash = $u->password;

        $this->postJson('/api/mobile/activate', ['nrp' => '999', 'password' => 'barubaru123'])->assertStatus(422);
        $this->postJson('/api/mobile/activate', ['nrp' => '999', 'token' => 'SALAH', 'password' => 'barubaru123'])->assertStatus(422);
        $this->assertSame($oldHash, $u->fresh()->password);

        $this->postJson('/api/mobile/activate', ['nrp' => '999', 'token' => 'TOKEN-ABC', 'password' => 'barubaru123'])->assertOk();
        $this->assertTrue($u->fresh()->is_active);
        $this->assertNull($u->fresh()->activation_token);
    }

    public function test_login_requires_valid_turnstile_when_secret_configured(): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);
        $this->makeUser(['nrp' => '4242']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

        // Tanpa token → ditolak sebelum kredensial diperiksa.
        $this->postJson('/api/mobile/login', ['username' => '4242', 'password' => 'rahasia123'])
            ->assertStatus(422)->assertJsonPath('code', 'turnstile_failed')->assertJsonMissing(['token']);

        // Token ditolak Cloudflare → tetap gagal.
        $this->postJson('/api/mobile/login', ['username' => '4242', 'password' => 'rahasia123', 'cf_turnstile_token' => 'palsu'])
            ->assertStatus(422)->assertJsonPath('code', 'turnstile_failed');
    }

    public function test_login_and_activation_pass_with_valid_turnstile(): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);
        $this->makeUser(['nrp' => '5151']);
        $u = $this->makeUser(['nrp' => '6161', 'is_active' => false, 'activation_token' => 'TOK-1']);

        Http::fake(function ($request) {
            $action = str_contains((string) $request->body(), 'act-token') ? 'activate' : 'login';
            return Http::response(['success' => true, 'action' => $action]);
        });

        $this->postJson('/api/mobile/login', ['username' => '5151', 'password' => 'rahasia123', 'cf_turnstile_token' => 'login-token'])
            ->assertOk()->assertJsonPath('status', 'success');

        // Token untuk action "login" tidak boleh dipakai untuk aktivasi.
        $this->postJson('/api/mobile/activate', ['nrp' => '6161', 'token' => 'TOK-1', 'password' => 'barubaru123', 'cf_turnstile_token' => 'login-token'])
            ->assertStatus(422)->assertJsonPath('code', 'turnstile_failed');
        $this->postJson('/api/mobile/activate', ['nrp' => '6161', 'token' => 'TOK-1', 'password' => 'barubaru123', 'cf_turnstile_token' => 'act-token'])
            ->assertOk();
        $this->assertTrue($u->fresh()->is_active);

        Http::assertSent(fn ($r) => $r->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify' && $r['secret'] === 'test-secret');
    }

    public function test_turnstile_widget_page_and_config_expose_only_site_key(): void
    {
        config(['services.turnstile.site_key' => 'SITE-KEY-PUBLIK', 'services.turnstile.secret_key' => 'RAHASIA-JANGAN-BOCOR']);

        $page = $this->get('/mobile/turnstile?action=activate')->assertOk();
        $page->assertSee('SITE-KEY-PUBLIK', false)->assertSee('"activate"', false)->assertDontSee('RAHASIA-JANGAN-BOCOR', false);

        $cfg = $this->getJson('/api/mobile/config')->assertOk()
            ->assertJsonPath('turnstile_site_key', 'SITE-KEY-PUBLIK')
            ->assertJsonPath('turnstile_enabled', true);
        $this->assertStringNotContainsString('RAHASIA-JANGAN-BOCOR', $cfg->getContent());
    }

    public function test_logout_revokes_token(): void
    {
        $u = $this->makeUser();
        $token = $this->tokenFor($u);
        $this->withToken($token)->postJson('/api/mobile/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/mobile/me')->assertStatus(401);
    }

    public function test_notifications_are_scoped_to_user(): void
    {
        $me = $this->makeUser(['role' => 'personel']);
        $other = $this->makeUser(['role' => 'komandan']);
        DB::table('app_notifications')->insert([
            ['user_id' => $me->id, 'role' => null, 'title' => 'Untuk saya', 'message' => 'a', 'type' => 'info', 'is_read' => false, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $other->id, 'role' => null, 'title' => 'Rahasia komandan', 'message' => 'b', 'type' => 'info', 'is_read' => false, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => null, 'role' => 'komandan', 'title' => 'Role komandan', 'message' => 'c', 'type' => 'info', 'is_read' => false, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => null, 'role' => null, 'title' => 'Broadcast', 'message' => 'd', 'type' => 'info', 'is_read' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $titles = collect($this->withToken($this->tokenFor($me))->getJson('/api/mobile/notifications')->json('notifications'))->pluck('title')->all();
        sort($titles);
        $this->assertSame(['Broadcast', 'Untuk saya'], $titles);
    }

    public function test_duty_roster_is_built_from_sp_jaga_and_marks_my_schedule(): void
    {
        $me = $this->makeUser(['name' => 'Bambang', 'nrp' => '82068']);
        $creator = $this->makeUser(['role' => 'admin']);
        $sp = SpJaga::forceCreate([
            'nomor_sprin' => 'Sprin/ 29 /X/2026', 'bulan' => 10, 'tahun' => 2026,
            'tmt_mulai' => '2026-10-01', 'tmt_selesai' => '2026-10-31',
            'status' => 'published', 'ttd_type' => 'tte', 'created_by' => $creator->id,
        ]);
        DB::table('sp_jaga_perwiras')->insert([
            'sp_jaga_id' => $sp->id, 'nama' => 'Bambang', 'pangkat_korps' => 'Peltu Saa', 'nrp' => '82068',
            'tgl_1' => '04', 'tgl_2' => '11', 'tgl_3' => '-', 'tgl_4' => '-', 'tgl_5' => '-', 'tgl_6' => '-', 'tgl_7' => '-',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sp_jaga_anggotas')->insert([
            'sp_jaga_id' => $sp->id, 'divisi_no' => 2, 'tanggal_list_text' => '05, 11 OKTOBER 2026',
            'anggota_items' => json_encode([['nama' => 'Erwan Junaidi', 'pangkat_korps' => 'Peltu Ttg', 'nrp_nip' => '84025', 'role_jaga' => 'BAGA']]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // SP draft tidak boleh terlihat oleh personel biasa
        SpJaga::forceCreate(['bulan' => 11, 'tahun' => 2026, 'status' => 'draft', 'created_by' => $creator->id]);

        $res = $this->withToken($this->tokenFor($me))
            ->getJson('/api/mobile/duty-roster?bulan=10&tahun=2026')
            ->assertOk()
            ->assertJsonPath('sp.id', $sp->id)
            ->assertJsonPath('my_dates', ['2026-10-04', '2026-10-11']);

        $days = collect($res->json('days'))->keyBy('date');
        $this->assertSame(['2026-10-04', '2026-10-05', '2026-10-11'], $days->keys()->all());
        $this->assertTrue($days['2026-10-04']['has_me']);
        $this->assertFalse($days['2026-10-05']['has_me']);
        $this->assertSame('Bintara Jaga (BAGA)', $days['2026-10-05']['entries'][0]['role']);
        $this->assertCount(2, $days['2026-10-11']['entries']);
        $this->assertCount(1, $res->json('available'), 'SP draft tidak boleh tampil untuk personel');

        $this->withToken($this->tokenFor($me))
            ->getJson('/api/mobile/duty-roster?bulan=11&tahun=2026')
            ->assertOk()->assertJsonPath('sp', null);
    }

    public function test_dashboard_only_contains_permitted_modules(): void
    {
        Schema::create('technical_unit_cashes', function (Blueprint $t) {
            $t->id(); $t->date('date'); $t->string('description'); $t->decimal('debit', 15, 2)->default(0);
            $t->decimal('credit', 15, 2)->default(0); $t->decimal('balance', 15, 2)->default(0); $t->timestamps();
        });
        Schema::create('letter_logs', function (Blueprint $t) {
            $t->id(); $t->foreignId('category_id')->nullable(); $t->string('sequence')->nullable(); $t->string('full_number')->nullable();
            $t->string('subject')->nullable(); $t->string('recipient')->nullable(); $t->date('date')->nullable(); $t->timestamps();
        });
        Schema::create('skhpps', function (Blueprint $t) {
            $t->id(); $t->string('status')->default('pending'); $t->foreignId('submitted_by')->nullable(); $t->timestamps();
        });
        if (!Schema::hasTable('signature_requests')) {
            Schema::create('signature_requests', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable(); $t->string('status')->default('pending'); $t->timestamps();
        });
        }
        $personel = $this->makeUser(['role' => 'personel']);
        $summary = $this->withToken($this->tokenFor($personel))->getJson('/api/mobile/dashboard')
            ->assertOk()->json('summary');
        $this->assertArrayNotHasKey('cash_balance', $summary);
        $this->assertArrayHasKey('attendance', $summary);

        $admin = $this->makeUser(['role' => 'admin']);
        DB::table('cashes')->insert(['date' => '2026-10-01', 'description' => 'x', 'debit' => 5000, 'credit' => 0, 'balance' => 5000, 'created_at' => now(), 'updated_at' => now()]);
        $adminSummary = $this->withToken($this->tokenFor($admin))->getJson('/api/mobile/dashboard')
            ->assertOk()->json('summary');
        $this->assertEquals(5000, $adminSummary['cash_balance']);
    }

    public function test_cash_pdf_is_real_pdf_and_permission_guarded(): void
    {
        $personel = $this->makeUser(['role' => 'personel']);
        $this->withToken($this->tokenFor($personel))->getJson('/api/mobile/pdf/cash')->assertStatus(403);

        $admin = $this->makeUser(['role' => 'admin']);
        DB::table('cashes')->insert(['date' => '2026-10-01', 'description' => 'Saldo awal', 'debit' => 1, 'credit' => 0, 'balance' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->app['auth']->forgetGuards();
        $res = $this->withToken($this->tokenFor($admin))->get('/api/mobile/pdf/cash');
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_unpublished_sp_jaga_pdf_is_forbidden_for_regular_personnel(): void
    {
        $me = $this->makeUser();
        $creator = $this->makeUser(['role' => 'admin']);
        $draft = SpJaga::forceCreate(['bulan' => 11, 'tahun' => 2026, 'status' => 'draft', 'created_by' => $creator->id]);
        $this->withToken($this->tokenFor($me))->getJson("/api/mobile/pdf/sp-jaga/{$draft->id}")->assertStatus(403);
    }

    public function test_laravel_log_viewer_is_no_longer_public(): void
    {
        $this->get('/admin/laravel-logs')->assertRedirect();
    }

    // ================================================================ v1.3

    public function test_turnstile_keys_come_from_admin_settings_and_secret_is_encrypted_and_never_shared(): void
    {
        \App\Models\Setting::updateOrCreate(['key' => 'turnstile_site_key'], ['value' => 'SITE-DARI-ADMIN']);
        \App\Models\Setting::updateOrCreate(['key' => 'turnstile_enabled'], ['value' => '1']);
        \App\Support\Turnstile::storeSecret('RAHASIA-ADMIN');
        \App\Models\Setting::updateOrCreate(['key' => 'google_client_secret'], ['value' => 'GOOGLE-RAHASIA']);
        \App\Models\Setting::updateOrCreate(['key' => 'agora_app_certificate'], ['value' => 'AGORA-RAHASIA']);

        $stored = \App\Models\Setting::where('key', 'turnstile_secret_key')->value('value');
        $this->assertNotSame('RAHASIA-ADMIN', $stored, 'secret harus terenkripsi di database');
        $this->assertSame('RAHASIA-ADMIN', \App\Support\Turnstile::secretKey());
        $this->assertTrue(\App\Support\Turnstile::enabled());

        $cfg = $this->getJson('/api/mobile/config')->assertOk()
            ->assertJsonPath('turnstile_site_key', 'SITE-DARI-ADMIN')
            ->assertJsonPath('turnstile_enabled', true);
        $this->assertStringNotContainsString('RAHASIA', $cfg->getContent());

        // Props Inertia yang dibagikan ke semua halaman web tidak memuat kredensial.
        $public = \App\Http\Middleware\HandleInertiaRequests::publicSettings(\App\Models\Setting::pluck('value', 'key')->toArray());
        $this->assertArrayHasKey('turnstile_site_key', $public);
        foreach (['turnstile_secret_key', 'google_client_secret', 'agora_app_certificate'] as $k) {
            $this->assertArrayNotHasKey($k, $public);
        }

        // Login wajib token Turnstile saat Admin mengaktifkannya.
        $this->makeUser(['nrp' => '7373']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true, 'action' => 'login'])]);
        $this->postJson('/api/mobile/login', ['username' => '7373', 'password' => 'rahasia123'])->assertStatus(422)->assertJsonPath('code', 'turnstile_failed');
        $this->postJson('/api/mobile/login', ['username' => '7373', 'password' => 'rahasia123', 'cf_turnstile_token' => 'tok'])->assertOk();
        Http::assertSent(fn ($r) => $r['secret'] === 'RAHASIA-ADMIN');

        // Admin mematikan → login berjalan tanpa token.
        \App\Models\Setting::updateOrCreate(['key' => 'turnstile_enabled'], ['value' => '0']);
        $this->assertFalse(\App\Support\Turnstile::enabled());
        $this->postJson('/api/mobile/login', ['username' => '7373', 'password' => 'rahasia123'])->assertOk();
    }

    public function test_settings_value_column_is_widened_for_encrypted_secret(): void
    {
        // Skema produksi lama: settings.value VARCHAR(255) → secret terenkripsi tidak muat (error 500).
        Schema::drop('settings');
        (require database_path('migrations/2026_02_05_070818_create_settings_table.php'))->up();
        $this->assertSame('varchar', Schema::getColumnType('settings', 'value'));

        (require database_path('migrations/2026_10_14_090000_change_settings_value_to_text.php'))->up();
        $this->assertSame('text', Schema::getColumnType('settings', 'value'));

        \App\Support\Turnstile::storeSecret('0x4AAAAAAA-contoh-secret-35-karakter');
        $stored = (string) \App\Models\Setting::where('key', 'turnstile_secret_key')->value('value');
        $this->assertGreaterThan(255, strlen($stored), 'nilai terenkripsi memang lebih panjang dari VARCHAR(255)');
        $this->assertSame('0x4AAAAAAA-contoh-secret-35-karakter', \App\Support\Turnstile::secretKey());
    }

    public function test_chat_personel_gets_own_thread_and_cannot_touch_other_threads(): void
    {
        $a = $this->makeUser(['name' => 'Pers A']);
        $b = $this->makeUser(['name' => 'Pers B']);
        $admin = $this->makeUser(['role' => 'admin', 'name' => 'Operator']);
        $tokA = $this->tokenFor($a);
        $tokB = $this->tokenFor($b);

        $uuid = $this->withToken($tokA)->getJson('/api/mobile/chat')->assertOk()
            ->assertJsonPath('is_operator', false)->json('thread.uuid');
        $this->assertNotEmpty($uuid);

        $this->withToken($tokA)->postJson("/api/mobile/chat/{$uuid}/send", ['message' => 'Izin lapor'])->assertOk()->assertJsonPath('success', true);

        $this->withToken($tokB)->getJson("/api/mobile/chat/{$uuid}/messages")->assertStatus(403);
        $this->withToken($tokB)->postJson("/api/mobile/chat/{$uuid}/send", ['message' => 'nyelonong'])->assertStatus(403);
        $this->withToken($tokB)->getJson("/api/mobile/chat/sync?uuid={$uuid}")->assertStatus(403);
        $this->withToken($tokB)->getJson('/api/mobile/chat/users?q=Pers')->assertStatus(403);
        $this->withToken($tokB)->postJson("/api/mobile/chat/{$uuid}/status")->assertStatus(403);

        $tokAdmin = $this->tokenFor($admin);
        $res = $this->withToken($tokAdmin)->getJson('/api/mobile/chat')->assertOk()->assertJsonPath('is_operator', true);
        $thread = collect($res->json('threads'))->firstWhere('uuid', $uuid);
        $this->assertSame(1, $thread['unread_admin']);
        $this->assertSame(1, $this->withToken($tokAdmin)->getJson('/api/mobile/chat/unread')->json('unread'));

        $this->withToken($tokAdmin)->postJson("/api/mobile/chat/{$uuid}/send", ['message' => 'Siap, diterima'])->assertOk();
        $this->assertSame(1, $this->withToken($tokA)->getJson('/api/mobile/chat/unread')->json('unread'));
        $msgs = $this->withToken($tokA)->getJson("/api/mobile/chat/{$uuid}/messages")->assertOk()->json('messages');
        $this->assertSame(['USER', 'ADMIN'], array_column($msgs, 'sender_type'));

        // Operator menutup utas lama lalu membuka utas baru: sync personel
        // mengikuti utas yang diminta (bukan utas pertama).
        $this->withToken($tokAdmin)->postJson("/api/mobile/chat/{$uuid}/status")->assertOk()->assertJsonPath('status', 'CLOSED');
        $newUuid = $this->withToken($tokAdmin)->postJson('/api/mobile/chat/start', ['user_id' => $a->id])->assertOk()->json('uuid');
        $this->assertNotSame($uuid, $newUuid);
        $this->withToken($tokAdmin)->postJson("/api/mobile/chat/{$newUuid}/send", ['message' => 'Utas baru'])->assertOk();
        $sync = $this->withToken($tokA)->getJson("/api/mobile/chat/sync?uuid={$newUuid}")->assertOk();
        $sync->assertJsonPath('thread.uuid', $newUuid);
        $this->assertSame(['Utas baru'], array_column($sync->json('messages'), 'message'));
        $this->withToken($tokA)->getJson('/api/mobile/chat')->assertOk()->assertJsonPath('thread.uuid', $newUuid);
    }

    public function test_video_call_signaling_reaches_operator_and_is_logged(): void
    {
        $a = $this->makeUser(['name' => 'Pemanggil']);
        $admin = $this->makeUser(['role' => 'admin', 'name' => 'Operator']);
        $tokA = $this->tokenFor($a);
        $tokAdmin = $this->tokenFor($admin);
        $uuid = $this->withToken($tokA)->getJson('/api/mobile/chat')->json('thread.uuid');

        $this->withToken($tokA)->postJson("/api/mobile/chat/{$uuid}/call/initiate", ['call_type' => 'video'])
            ->assertOk()->assertJsonPath('call.status', 'RINGING');

        $incoming = $this->withToken($tokAdmin)->getJson('/api/mobile/chat/incoming-call')->assertOk()->json('call');
        $this->assertSame($uuid, $incoming['thread_uuid']);
        $this->assertSame('video', $incoming['call_type']);

        $this->withToken($tokAdmin)->postJson("/api/mobile/chat/{$uuid}/call/signal", ['action' => 'accept'])
            ->assertOk()->assertJsonPath('call.status', 'ACCEPTED');
        $this->withToken($tokA)->getJson("/api/mobile/chat/{$uuid}/call/signal")->assertJsonPath('call.status', 'ACCEPTED');

        $this->withToken($tokA)->postJson("/api/mobile/chat/{$uuid}/call/end", ['reason' => 'ended', 'duration_seconds' => 65])->assertOk();
        $log = \App\Models\LiveChatMessage::where('sender_type', 'SYSTEM')->latest('id')->value('message');
        $this->assertStringContainsString('01:05', $log);

        $other = $this->makeUser();
        $this->withToken($this->tokenFor($other))->postJson("/api/mobile/chat/{$uuid}/call/initiate")->assertStatus(403);
    }

    public function test_signature_document_preview_only_for_approver_or_requester(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('signature_reqs/dok.pdf', "%PDF-1.4\n% dokumen uji\n");
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $komandan = $this->makeUser(['role' => 'komandan']);
        $sr = \App\Models\SignatureRequest::forceCreate(['user_id' => $owner->id, 'subject' => 'Laporan', 'file_path' => 'signature_reqs/dok.pdf', 'status' => 'pending']);

        $res = $this->withToken($this->tokenFor($owner))->get("/api/mobile/pdf/signature/{$sr->id}");
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->withToken($this->tokenFor($komandan))->get("/api/mobile/pdf/signature/{$sr->id}")->assertOk();
        $this->withToken($this->tokenFor($other))->getJson("/api/mobile/pdf/signature/{$sr->id}")->assertStatus(403);
    }

    public function test_sp_jaga_submission_notifies_pasops_who_can_sign_from_app(): void
    {
        Http::fake();
        $creator = $this->makeUser(['role' => 'admin']);
        $pasops = $this->makeUser(['role' => 'pasops', 'name' => 'Pasops Uji']);
        $personel = $this->makeUser(['role' => 'personel']);
        $sp = SpJaga::forceCreate(['bulan' => 10, 'tahun' => 2026, 'nomor_sprin' => 'Sprin/9/X/2026', 'status' => 'pending_signature', 'created_by' => $creator->id]);

        \App\Support\SpJagaNotifier::pendingSignature($sp, $creator);

        $tokP = $this->tokenFor($pasops);
        $tokU = $this->tokenFor($personel);
        $titles = collect($this->withToken($tokP)->getJson('/api/mobile/notifications')->json('notifications'))->pluck('title');
        $this->assertContains('Pengajuan TTE SP Jaga', $titles);
        $titlesU = collect($this->withToken($tokU)->getJson('/api/mobile/notifications')->json('notifications'))->pluck('title');
        $this->assertNotContains('Pengajuan TTE SP Jaga', $titlesU);

        $this->withToken($tokU)->getJson('/api/mobile/duty-roster/pending')->assertStatus(403);
        $this->withToken($tokU)->postJson("/api/mobile/duty-roster/{$sp->id}/approve")->assertStatus(403);
        $this->assertContains('duty_roster.sign', $this->withToken($tokP)->getJson('/api/mobile/me')->json('user.permissions'));

        $pending = $this->withToken($tokP)->getJson('/api/mobile/duty-roster/pending')->assertOk()->json('data');
        $this->assertSame($sp->id, $pending[0]['id']);

        $this->withToken($tokP)->postJson("/api/mobile/duty-roster/{$sp->id}/approve")->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame('published', $sp->fresh()->status);
        $this->withToken($tokP)->postJson("/api/mobile/duty-roster/{$sp->id}/approve")->assertStatus(422);

        $titlesU = collect($this->withToken($tokU)->getJson('/api/mobile/notifications')->json('notifications'))->pluck('title');
        $this->assertContains('Jadwal jaga terbit', $titlesU);
    }
}
