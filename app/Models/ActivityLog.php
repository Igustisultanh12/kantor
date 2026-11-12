<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak aksi pengguna terhadap data (tambah/ubah/hapus/kirim/setujui, ...)
 * dari web maupun aplikasi Android, untuk pertanggungjawaban: siapa, apa,
 * kapan, dari perangkat apa, IP mana, dan di mana (lokasi IP & GPS terakhir).
 *
 * Berbeda dengan Log Pengunjung (login/kunjungan halaman) dan AuditLog lama
 * (catatan manual tindakan admin).
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * Modul yang direkam (permintaan pengguna 2026-11-12): hanya arsip &
     * persuratan, dokumen, dan keuangan. Aksi lain seperti ubah profil/data
     * pribadi, absensi, chat, notifikasi, dan pengaturan TIDAK dicatat.
     */
    public const IMPORTANT_MODULES = [
        // Arsip & persuratan / dokumen
        'Surat', 'Agenda Surat', 'Kategori Surat', 'SKHPP', 'Tanda Tangan Elektronik', 'Stempel',
        'SP Jaga', 'Security Clearance', 'Arsip Backup',
        // Keuangan
        'Kas', 'Kas Unit Teknis', 'Rekening Komandan', 'Rekening Ibu Beti', 'Koperasi', 'Mitra',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'success' => 'boolean',
        'subject' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'location_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    private static bool $schemaChecked = false;

    public static function ensureSchema(): bool
    {
        if (self::$schemaChecked) {
            return true;
        }
        try {
            if (!Schema::hasTable('activity_logs')) {
                Schema::create('activity_logs', fn (Blueprint $t) => self::columns($t));
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
        $t->foreignId('user_id')->nullable()->index();
        $t->string('user_name', 150)->nullable();
        $t->string('pangkat', 80)->nullable();
        $t->string('nrp', 40)->nullable();
        $t->string('role', 40)->nullable();
        $t->string('channel', 10)->index();          // web | android
        $t->string('method', 10);
        $t->string('route', 150)->nullable();
        $t->string('path', 255);
        $t->string('module', 40)->nullable()->index();
        $t->string('action', 200);                   // kalimat yang mudah dibaca
        $t->json('subject')->nullable();             // parameter rute (id data yang disentuh)
        $t->json('payload')->nullable();             // isi form (sensitif disamarkan)
        $t->unsignedSmallInteger('status');
        $t->boolean('success')->index();
        $t->string('ip', 45)->nullable()->index();
        $t->string('ip_location', 160)->nullable();
        $t->string('device', 160)->nullable();
        $t->text('user_agent')->nullable();
        $t->decimal('latitude', 10, 7)->nullable();
        $t->decimal('longitude', 10, 7)->nullable();
        $t->unsignedInteger('location_accuracy')->nullable();
        $t->timestamp('location_at')->nullable();
        $t->unsignedInteger('duration_ms')->nullable();
        $t->timestamp('created_at')->nullable()->index();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
