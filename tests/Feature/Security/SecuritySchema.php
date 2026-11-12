<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Skema minimal untuk test regresi keamanan (audit 2026-11).
 * Migrasi repo belum bisa dijalankan di SQLite (tabel dobel), jadi skema dibangun eksplisit.
 * Semua HTTP keluar dipalsukan; IP printer diarahkan ke port tertutup di loopback
 * (socket mentah ke printer tidak tertangkap Http::fake).
 */
trait SecuritySchema
{
    protected function setUpSecuritySchema(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['ok' => true, 'status' => 'CONNECTED'], 200)]);
        $this->withoutVite();
        $this->withoutMiddleware([
            \App\Http\Middleware\LogVisitor::class,
            \App\Http\Middleware\TrackDeviceSession::class,
            \App\Http\Middleware\RecordActivity::class,
            \App\Http\Middleware\FlushRealtime::class,
        ]);
        config(['services.turnstile.secret_key' => null]);

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('nrp')->nullable();
            $t->string('nip')->nullable();
            $t->string('pangkat')->nullable();
            $t->string('korps')->nullable();
            $t->string('jabatan')->nullable();
            $t->string('phone')->nullable();
            $t->string('avatar')->nullable();
            $t->string('role')->default('staf');
            $t->string('password');
            $t->boolean('is_active')->default(true);
            foreach (['can_access_mitra', 'can_access_technical_cash', 'can_manage_koperasi', 'can_access_ibu_beti', 'mfa_enabled',
                      'is_developer', 'can_access_cash', 'can_access_commander', 'can_manage_sp_jaga', 'can_sign_sp_jaga', 'must_change_password'] as $f) {
                $t->boolean($f)->default(false);
            }
            $t->string('activation_token')->nullable();
            $t->string('reset_token')->nullable();
            $t->timestamp('token_expires_at')->nullable();
            $t->string('google_id')->nullable();
            $t->string('google_email')->nullable();
            $t->string('google_avatar')->nullable();
            $t->date('birth_date')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id(); $t->string('key')->unique(); $t->text('value')->nullable(); $t->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id(); $t->morphs('tokenable'); $t->string('name'); $t->string('token', 64)->unique();
            $t->text('abilities')->nullable(); $t->timestamp('last_used_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('admin_name')->nullable(); $t->string('action')->nullable();
            $t->string('target_personnel')->nullable(); $t->text('description')->nullable(); $t->string('ip_address')->nullable(); $t->timestamps();
        });

        DB::table('settings')->insert([
            ['key' => 'printer_brother_ip', 'value' => '127.0.0.1'], ['key' => 'printer_brother_port', 'value' => '1'],
            ['key' => 'printer_canon_ip', 'value' => '127.0.0.1'], ['key' => 'printer_canon_port', 'value' => '1'],
        ]);
    }

    protected function makeUser(array $attrs = []): User
    {
        static $n = 0;
        $n++;
        $flags = array_intersect_key($attrs, array_flip(\App\Support\MobileAccess::ACCESS_FLAGS));
        $user = User::create(array_merge([
            'name' => "Uji {$n}", 'email' => "uji{$n}@example.test", 'nrp' => "NRP{$n}",
            'password' => Hash::make('rahasia-123'), 'role' => 'staf', 'is_active' => true,
        ], array_diff_key($attrs, $flags)));
        if ($flags) {
            $user->forceFill($flags)->save();
        }
        return $user->fresh();
    }
}
