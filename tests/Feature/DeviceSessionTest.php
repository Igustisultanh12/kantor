<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/** Perangkat & sesi: heartbeat Android, daftar sesi, dan mengeluarkan perangkat dari web. */
class DeviceSessionTest extends TestCase
{
    private User $me;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('nrp')->nullable();
            $t->string('pangkat')->nullable();
            $t->string('role')->default('personel');
            $t->string('password');
            $t->boolean('is_active')->default(true);
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
        Schema::create('device_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id');
            $t->string('token_hash', 64)->unique();
            $t->text('token');
            $t->string('platform', 20)->nullable();
            $t->string('app_version', 20)->nullable();
            $t->string('device_name', 100)->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
        (require database_path('migrations/2026_11_12_200000_create_user_devices_table.php'))->up();

        $this->withoutMiddleware(\App\Http\Middleware\LogVisitor::class);
        Http::fake(['*' => Http::response(['status' => 'success', 'city' => 'Surabaya', 'country' => 'Indonesia'], 200)]);

        $make = fn (string $n) => User::create([
            'name' => $n, 'email' => strtolower($n) . '@test.local', 'password' => Hash::make('rahasia123'), 'is_active' => true,
        ]);
        $this->me = $make('Saya');
        $this->other = $make('Lain');
    }

    private function bearer(string $plain): array
    {
        return ['Authorization' => 'Bearer ' . $plain, 'Accept' => 'application/json'];
    }

    public function test_android_heartbeat_listed_and_revoked_from_web(): void
    {
        $phoneA = $this->me->createToken('hp-a')->plainTextToken;
        $phoneB = $this->me->createToken('hp-b')->plainTextToken;
        DeviceToken::register($this->me->id, str_repeat('f', 40), '2.0.3', 'HP B');

        $this->postJson('/api/mobile/devices/heartbeat', [
            'device_id' => 'abc123', 'manufacturer' => 'samsung', 'model' => 'SM-A155F', 'os_version' => '14', 'sdk' => 34,
            'app_version' => '2.0.3', 'battery' => 76, 'charging' => true, 'network' => 'WiFi',
            'lat' => -7.25, 'lng' => 112.75, 'accuracy' => 12.4, 'push_token' => str_repeat('f', 40),
        ], $this->bearer($phoneB))->assertOk();

        // Nilai tidak valid dibuang, laporan tetap diterima.
        $this->postJson('/api/mobile/devices/heartbeat', ['battery' => 900, 'model' => 'Pixel 8'], $this->bearer($phoneA))->assertOk();
        $this->assertNull(UserDevice::where('model', 'Pixel 8')->first()->battery_level);

        $list = $this->actingAs($this->me)->getJson('/profile/devices')->assertOk()->json('devices');
        $b = collect($list)->firstWhere('name', 'Samsung SM-A155F');
        $this->assertNotNull($b);
        $this->assertSame(76, $b['battery']);
        $this->assertSame('abc123', $b['device_key']);
        $this->assertEqualsWithDelta(-7.25, $b['location']['lat'], 0.0001);

        // Orang lain tidak bisa mengeluarkan HP saya.
        $this->actingAs($this->other)->deleteJson("/profile/devices/android/{$b['id']}")->assertStatus(404);

        $this->actingAs($this->me)->deleteJson("/profile/devices/android/{$b['id']}")->assertOk();
        $this->assertNull(PersonalAccessToken::find($b['id']));
        $this->assertSame(0, DeviceToken::where('user_id', $this->me->id)->count(), 'Push ke HP yang dikeluarkan harus berhenti');
        // HP B langsung keluar; HP A tetap masuk.
        $this->getJson('/api/mobile/devices/heartbeat', $this->bearer($phoneB))->assertStatus(405);
        $this->postJson('/api/mobile/devices/heartbeat', [], $this->bearer($phoneB))->assertStatus(401);
        $this->postJson('/api/mobile/devices/heartbeat', [], $this->bearer($phoneA))->assertOk();
    }

    public function test_web_sessions_listed_and_logout_others(): void
    {
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            ['id' => 'sesi-kantor', 'user_id' => $this->me->id, 'ip_address' => '36.80.1.2', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0 Safari/537.36', 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'sesi-orang', 'user_id' => $this->other->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => now()->timestamp],
        ]);
        // Panggilan API aplikasi ikut membuat baris sesi (rute mobile ada di grup web): jangan tampil sebagai "web".
        DB::table('sessions')->insert(['id' => 'sesi-api-hp', 'user_id' => $this->me->id, 'ip_address' => '10.0.0.5', 'user_agent' => 'SISINDEN-Android/2.0.3', 'payload' => '', 'last_activity' => now()->timestamp]);
        $hp = $this->me->createToken('hp')->plainTextToken;
        $this->postJson('/api/mobile/devices/heartbeat', ['model' => 'A55'], $this->bearer($hp))->assertOk();
        $this->assertSame(0, UserDevice::where('kind', 'web')->count(), 'Request API tidak boleh tercatat sebagai sesi web');
        $oldRemember = $this->me->fresh()->remember_token;

        $list = collect($this->actingAs($this->me)->getJson('/profile/devices')->assertOk()->json('devices'));
        $this->assertTrue($list->contains(fn ($d) => $d['kind'] === 'web' && $d['id'] === 'sesi-kantor' && str_contains((string) $d['ip_location'], 'Surabaya')));
        $this->assertFalse($list->contains(fn ($d) => $d['id'] === 'sesi-orang'));
        $this->assertFalse($list->contains(fn ($d) => $d['id'] === 'sesi-api-hp'));
        $this->assertSame(1, $list->where('kind', 'android')->count());

        $this->actingAs($this->me)->postJson('/profile/devices/logout-others')->assertOk();
        $this->assertSame(0, DB::table('sessions')->where('id', 'sesi-kantor')->count());
        $this->assertSame(1, DB::table('sessions')->where('id', 'sesi-orang')->count());
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $this->me->id)->count());
        $this->assertNotSame($oldRemember, $this->me->fresh()->remember_token);
    }
}
