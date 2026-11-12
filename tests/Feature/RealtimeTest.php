<?php

namespace Tests\Feature;

use App\Events\DataChanged;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Sinyal realtime: query tulis -> modul, tabel teknis diabaikan, dan otorisasi kanal untuk aplikasi. */
class RealtimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'kunci-uji',
            'broadcasting.connections.reverb.secret' => 'rahasia-uji',
            'broadcasting.connections.reverb.app_id' => '1001',
            'broadcasting.connections.reverb.public' => ['host' => 'sisinden.my.id', 'port' => 443, 'scheme' => 'https'],
        ]);
        Realtime::reset();
    }

    protected function tearDown(): void
    {
        Realtime::reset();
        parent::tearDown();
    }

    public function test_write_queries_map_to_modules_and_noise_is_ignored(): void
    {
        Realtime::inspectSql('select * from "letters" where id = 1');
        Realtime::inspectSql('update `sessions` set `last_activity` = 1');
        Realtime::inspectSql('insert into "visitor_logs" ("x") values (?)');
        Realtime::inspectSql('update `users` set `remember_token` = ?, `updated_at` = ? where `id` = ?');
        $this->assertSame([], Realtime::pending());

        Realtime::inspectSql('insert into `skhpps` (`a`) values (?)');
        Realtime::inspectSql('update "backups" set "parent_id" = ? where "id" in (1, 2)');
        Realtime::inspectSql('delete from `attendances` where `id` = ?');
        Realtime::inspectSql('update `users` set `name` = ? where `id` = ?');
        Realtime::inspectSql('insert into `tabel_baru_lain` (`a`) values (?)');
        $this->assertEqualsCanonicalizing(['skhpp', 'backup', 'attendance', 'users', 'general'], Realtime::pending());

        Event::fake([DataChanged::class]);
        Realtime::flush();
        Event::assertDispatched(DataChanged::class, fn (DataChanged $e) => in_array('skhpp', $e->modules, true)
            && $e->broadcastOn()[0]->name === 'private-sinden' && $e->broadcastAs() === 'changed');
        $this->assertSame([], Realtime::pending());
    }

    public function test_device_changes_signal_devices_but_not_heartbeats_or_gc(): void
    {
        Realtime::inspectSql('update `user_devices` set `battery_level` = ? where `id` = ?');
        Realtime::inspectSql('update `personal_access_tokens` set `last_used_at` = ? where `id` = ?');
        Realtime::inspectSql('insert into `sessions` (`id`, `payload`) values (?, ?)');
        Realtime::inspectSql('delete from `sessions` where `last_activity` <= ?');
        $this->assertSame([], Realtime::pending());

        Realtime::inspectSql('insert into `user_devices` (`kind`) values (?)');
        $this->assertSame(['devices'], Realtime::pending());
        Realtime::reset();
        Realtime::inspectSql('delete from `sessions` where `id` = ?');
        $this->assertSame(['devices'], Realtime::pending());
        Realtime::reset();
        Realtime::inspectSql('delete from "personal_access_tokens" where "id" = ?');
        $this->assertSame(['devices'], Realtime::pending());
    }

    public function test_disabled_when_reverb_not_configured(): void
    {
        config(['broadcasting.default' => 'log']);
        Realtime::reset();
        Realtime::inspectSql('insert into `skhpps` (`a`) values (?)');
        $this->assertSame([], Realtime::pending());
        $this->assertNull(Realtime::clientConfig());
    }

    public function test_mobile_app_can_authorize_private_channel_and_gets_config(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('role')->default('personel');
            $t->string('password');
            $t->boolean('is_active')->default(true);
            $t->rememberToken();
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
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        $this->withoutMiddleware(\App\Http\Middleware\LogVisitor::class);
        // Kanal didaftarkan ke driver default saat boot; tes mengganti driver ke Reverb sesudahnya.
        app(\Illuminate\Broadcasting\BroadcastManager::class)->forgetDrivers();
        require base_path('routes/channels.php');
        $user = User::create(['name' => 'Uji', 'email' => 'uji@test.local', 'password' => Hash::make('x'), 'is_active' => true]);
        $token = $user->createToken('hp')->plainTextToken;

        $this->postJson('/api/mobile/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-sinden'])
            ->assertStatus(401);
        $res = $this->withToken($token)->postJson('/api/mobile/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-sinden']);
        $res->assertOk();
        $this->assertStringStartsWith('kunci-uji:', (string) $res->json('auth'));

        $cfg = Realtime::clientConfig();
        $this->assertSame('sisinden.my.id', $cfg['host']);
        $this->assertSame('private-sinden', $cfg['channel']);
    }
}
