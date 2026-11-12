<?php

namespace App\Support;

use App\Events\DataChanged;

/**
 * Realtime SISINDEN (Laravel Reverb).
 *
 * Setiap query tulis (INSERT/UPDATE/DELETE) ke tabel data menandai modulnya
 * "berubah" — termasuk perubahan massal lewat query builder. Di akhir request
 * (setelah respons dikirim) satu sinyal DataChanged disiarkan ke kanal privat
 * `sinden`; web & Android lalu memuat ulang data layar yang sedang dibuka.
 * Yang dikirim hanya nama modul, bukan isi data.
 *
 * Bila Reverb belum dipasang (BROADCAST_CONNECTION bukan "reverb"), tidak ada
 * yang dikirim dan tidak ada error.
 */
class Realtime
{
    /** Nama model => modul. Tabel lain yang tidak dikenal memicu modul "general". */
    public const MODULES = [
        'Letter' => 'letters', 'LetterLog' => 'letters', 'LetterCategory' => 'letters', 'LetterSubCategory' => 'letters',
        'Category' => 'letters', 'SubCategory' => 'letters',
        'Skhpp' => 'skhpp', 'SkhppMember' => 'skhpp',
        'SignatureRequest' => 'signature', 'StampLog' => 'signature',
        'Attendance' => 'attendance',
        'SpJaga' => 'duty', 'SpJagaAnggota' => 'duty', 'SpJagaPerwira' => 'duty',
        'LiveChatThread' => 'chat', 'LiveChatMessage' => 'chat', 'Message' => 'chat',
        'AppNotification' => 'notifications',
        'Backup' => 'backup', 'Pc' => 'backup', 'PcAccessRequest' => 'backup', 'BackupShare' => 'backup', 'BackupShareGuest' => 'backup',
        'Cash' => 'finance', 'TechnicalUnitCash' => 'finance', 'CommanderAccount' => 'finance', 'IbuBetiAccount' => 'finance',
        'Mitra' => 'finance', 'MitraPayment' => 'finance',
        'KoperasiAccount' => 'koperasi', 'KoperasiCashMutation' => 'koperasi', 'KoperasiInstallment' => 'koperasi',
        'KoperasiLoan' => 'koperasi', 'KoperasiSavingsTransaction' => 'koperasi',
        'ScSubmission' => 'sc', 'ScSubmissionLog' => 'sc', 'Applicant' => 'sc', 'Submission' => 'sc', 'RhType' => 'sc',
        'SoldierViolation' => 'violations',
        'OpenMeeting' => 'meetings', 'OpenMeetingAttendee' => 'meetings',
        'CommunityActivity' => 'activities',
        'PrintJob' => 'print',
        'User' => 'users', 'Personnel' => 'users', 'AccessRequest' => 'users',
        'Setting' => 'settings', 'UcapanConfig' => 'settings', 'OfficeNetwork' => 'settings',
    ];

    /** Tabel teknis yang berubah terus (sesi, cache, log, token): tidak memicu muat ulang. */
    public const IGNORED_TABLES = [
        'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'password_reset_tokens',
        'personal_access_tokens', 'visitor_logs', 'audit_logs', 'activity_logs', 'device_tokens', 'user_devices',
        'backup_share_access_logs', 'wa_sessions', 'telescope_entries', 'pulse_entries', 'pulse_values', 'pulse_aggregates',
    ];

    /** Kolom users yang diperbarui tiap login/aktivitas: tidak dianggap perubahan data. */
    private const USER_NOISE_COLUMNS = ['updated_at', 'remember_token', 'last_login_at', 'last_seen_at', 'last_activity'];

    /** @var array<string, true> */
    private static array $pending = [];
    /** @var array<string, string>|null */
    private static ?array $tableMap = null;
    private static ?bool $enabled = null;

    public static function enabled(): bool
    {
        return self::$enabled ??= config('broadcasting.default') === 'reverb'
            && filled(config('broadcasting.connections.reverb.key'));
    }

    /** Untuk tes: hitung ulang status aktif dan kosongkan antrean. */
    public static function reset(): void
    {
        self::$enabled = null;
        self::$pending = [];
    }

    /** Dipanggil untuk setiap query SQL yang dijalankan (lihat AppServiceProvider). */
    public static function inspectSql(string $sql): void
    {
        // Cepat: abaikan SELECT dan perintah lain tanpa regex.
        $head = strtolower(substr(ltrim($sql), 0, 6));
        if ($head !== 'insert' && $head !== 'update' && $head !== 'delete' && $head !== 'replac') {
            return;
        }
        if (!preg_match('/^\s*(?:insert(?:\s+or\s+\w+)?\s+into|replace\s+into|update|delete\s+from)\s+[`"\[]?([A-Za-z0-9_]+)[`"\]]?/i', $sql, $m)) {
            return;
        }
        $table = strtolower($m[1]);
        // Perangkat masuk/keluar/dikeluarkan: sinyal "devices" (bukan tiap heartbeat/pemakaian token).
        // Sesi web baru tercatat lewat insert user_devices, jadi insert ke sessions (termasuk tamu) diabaikan.
        if (($head !== 'update' && in_array($table, ['user_devices', 'personal_access_tokens'], true))
            // Pembersihan sesi kedaluwarsa (gc, "where last_activity <= ?") bukan aksi perangkat.
            || ($head === 'delete' && $table === 'sessions' && stripos($sql, 'last_activity') === false)) {
            self::touch('devices');
            return;
        }
        if (in_array($table, self::IGNORED_TABLES, true)) {
            return;
        }
        if ($table === 'users' && $head === 'update' && self::onlyNoiseColumns($sql)) {
            return;
        }
        self::touch(self::moduleForTable($table));
    }

    private static function onlyNoiseColumns(string $sql): bool
    {
        if (!preg_match('/\sset\s(.+?)\swhere\s/is', $sql, $m)) {
            return false;
        }
        preg_match_all('/[`"]?([A-Za-z0-9_]+)[`"]?\s*=/', $m[1], $cols);
        return $cols[1] !== [] && array_diff(array_map('strtolower', $cols[1]), self::USER_NOISE_COLUMNS) === [];
    }

    public static function moduleForTable(string $table): string
    {
        if (self::$tableMap === null) {
            self::$tableMap = [];
            foreach (self::MODULES as $model => $module) {
                $class = "App\\Models\\{$model}";
                if (class_exists($class)) {
                    try {
                        self::$tableMap[strtolower((new $class())->getTable())] = $module;
                    } catch (\Throwable) {
                    }
                }
            }
        }
        return self::$tableMap[$table] ?? 'general';
    }

    public static function touch(string ...$modules): void
    {
        if (!self::enabled()) {
            return;
        }
        // Disiarkan oleh FlushRealtime::terminate() (HTTP) atau setelah job/perintah selesai (lihat AppServiceProvider).
        foreach ($modules as $m) {
            self::$pending[$m] = true;
        }
    }

    /** @return list<string> modul yang menunggu disiarkan (untuk tes). */
    public static function pending(): array
    {
        return array_keys(self::$pending);
    }

    public static function flush(): void
    {
        $modules = array_keys(self::$pending);
        self::$pending = [];
        if ($modules === []) {
            return;
        }
        try {
            broadcast(new DataChanged($modules));
        } catch (\Throwable $e) {
            // Reverb mati / tidak terjangkau: aplikasi tetap jalan, hanya tanpa realtime.
            logger()->warning('[Realtime] Gagal menyiarkan perubahan: ' . $e->getMessage());
        }
    }

    /** Tandai sesi web / token aplikasi yang dikeluarkan pemilik akun, agar perangkat itu diberi tahu alasannya. */
    public static function markRevoked(string $kind, string $id): void
    {
        \Illuminate\Support\Facades\Cache::put(self::revokedKey($kind, $id), true, now()->addDays(7));
    }

    /** Apakah sesi/token ini dikeluarkan pemilik akun (bukan sekadar habis). Sekali baca lalu dihapus bila $consume. */
    public static function wasRevoked(string $kind, ?string $id, bool $consume = false): bool
    {
        if ($id === null || $id === '') {
            return false;
        }
        $key = self::revokedKey($kind, $id);
        return (bool) ($consume ? \Illuminate\Support\Facades\Cache::pull($key) : \Illuminate\Support\Facades\Cache::get($key));
    }

    private static function revokedKey(string $kind, string $id): string
    {
        // ID sesi tidak disimpan mentah.
        return 'revoked:' . $kind . ':' . hash('sha256', $id);
    }

    /** Konfigurasi publik untuk klien (aplikasi Android). Null bila realtime belum aktif. */
    public static function clientConfig(): ?array
    {
        if (!self::enabled()) {
            return null;
        }
        $pub = config('broadcasting.connections.reverb.public', []);
        return [
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => $pub['host'] ?? parse_url((string) config('app.url'), PHP_URL_HOST),
            'port' => (int) ($pub['port'] ?? 443),
            'scheme' => $pub['scheme'] ?? 'https',
            'auth_endpoint' => '/api/mobile/broadcasting/auth',
            'channel' => 'private-sinden',
            'event' => 'changed',
        ];
    }
}
