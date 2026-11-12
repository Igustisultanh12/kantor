<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Info perangkat untuk satu sesi login: sesi web (session_id) atau token
 * aplikasi Android (token_id). Dipakai halaman "Perangkat & sesi" agar
 * pemilik akun bisa melihat dan mengeluarkan perangkat lain.
 */
class UserDevice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'charging' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'location_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    private static bool $schemaChecked = false;

    /** Buat tabel bila server belum menjalankan migrasi (pola yang sama dengan PcAccessRequest). */
    public static function ensureSchema(): bool
    {
        if (self::$schemaChecked) {
            return true;
        }
        try {
            if (!Schema::hasTable('user_devices')) {
                Schema::create('user_devices', fn (Blueprint $t) => self::columns($t));
            }
            self::$schemaChecked = true;
            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    public static function columns(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('user_id')->index();
        $t->string('kind', 10);                       // web | android
        $t->string('session_id', 255)->nullable()->index();
        $t->unsignedBigInteger('token_id')->nullable()->index();
        $t->string('device_key', 64)->nullable();    // Android ID (IMEI tidak bisa dibaca aplikasi biasa sejak Android 10)
        $t->string('manufacturer', 60)->nullable();
        $t->string('model', 80)->nullable();
        $t->string('os', 40)->nullable();
        $t->string('os_version', 40)->nullable();
        $t->string('browser', 60)->nullable();
        $t->string('app_version', 20)->nullable();
        $t->text('user_agent')->nullable();
        $t->string('ip', 45)->nullable();
        $t->string('ip_location', 160)->nullable();
        $t->string('ip_location_for', 45)->nullable();
        $t->unsignedTinyInteger('battery_level')->nullable();
        $t->boolean('charging')->nullable();
        $t->string('network', 30)->nullable();
        $t->string('screen', 30)->nullable();
        $t->decimal('latitude', 10, 7)->nullable();
        $t->decimal('longitude', 10, 7)->nullable();
        $t->unsignedInteger('location_accuracy')->nullable();
        $t->timestamp('location_at')->nullable();
        $t->string('push_token_hash', 64)->nullable();
        $t->timestamp('last_seen_at')->nullable();
        $t->timestamps();
    }
}
