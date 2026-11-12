<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\UserDevice;
use App\Services\DeviceSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencatat setiap aksi tulis (POST/PUT/PATCH/DELETE) pengguna yang login, dari web
 * maupun aplikasi Android, ke tabel activity_logs: siapa, aksi apa, data mana,
 * perangkat, IP, lokasi IP, GPS terakhir perangkat, dan berhasil/gagal.
 *
 * Login/logout & alur autentikasi TIDAK dicatat di sini (sudah ada di Log Pengunjung).
 * Pencatatan dijalankan setelah respons terkirim dan tidak pernah menggagalkan request.
 */
class RecordActivity
{
    /** Alur autentikasi dan sinyal teknis yang bukan aksi terhadap data. */
    private const SKIP_PATHS = [
        'login', 'login/*', 'logout', 'register', 'forgot-password', 'reset-password', 'reset-password/*',
        'verify-email/*', 'email/verification-notification', 'confirm-password', 'mfa', 'mfa/*', 'two-factor*',
        'auth/*', 'activation', 'activation/*', 'aktivasi*',
        'api/login', 'api/mobile/login', 'api/mobile/logout', 'api/mobile/activate*', 'api/mobile/password*', 'api/mobile/mfa*',
        'broadcasting/auth', 'api/mobile/broadcasting/auth',
        'profile/devices/report', 'api/mobile/devices/heartbeat', 'api/mobile/push/*',
        '*typing*', 'live-chat/*/call/signal', '*/call/signal', 'api/mobile/chat/*/call/signal',
    ];

    private const SENSITIVE = '/(pass|token|secret|otp|captcha|turnstile|pin\b|^pin|api_key|apikey|private|credential|_token)/i';

    private const MODULE_LABELS = [
        'letters' => 'Surat', 'letter-logs' => 'Agenda Surat', 'agenda' => 'Agenda Surat', 'categories' => 'Kategori Surat',
        'sub-categories' => 'Kategori Surat', 'skhpp' => 'SKHPP', 'signature' => 'Tanda Tangan Elektronik', 'signatures' => 'Tanda Tangan Elektronik',
        'tte' => 'Tanda Tangan Elektronik', 'stamp' => 'Stempel', 'stamps' => 'Stempel', 'sp-jaga' => 'SP Jaga', 'duty-roster' => 'SP Jaga',
        'attendance' => 'Absensi', 'attendances' => 'Absensi', 'absensi' => 'Absensi', 'live-chat' => 'Chat Dinas', 'chat' => 'Chat Dinas',
        'pc-backup' => 'Backup', 'backup' => 'Backup', 'cash' => 'Kas', 'technical-unit-cash' => 'Kas Unit Teknis',
        'commander-account' => 'Rekening Komandan', 'commander' => 'Rekening Komandan', 'rekening-ibu-beti' => 'Rekening Ibu Beti',
        'ibu-beti' => 'Rekening Ibu Beti', 'mitra' => 'Mitra', 'simpan-pinjam' => 'Koperasi', 'koperasi' => 'Koperasi',
        'sc-submission' => 'Security Clearance', 'sc' => 'Security Clearance', 'tracking-sc' => 'Security Clearance', 'pess' => 'Security Clearance',
        'soldier-violations' => 'Pelanggaran', 'violations' => 'Pelanggaran', 'rapat-terbuka' => 'Rapat Terbuka', 'open-meetings' => 'Rapat Terbuka',
        'activities' => 'Kegiatan', 'print-service' => 'Layanan Cetak', 'print' => 'Layanan Cetak', 'users' => 'Pengguna',
        'personnel' => 'Personel', 'settings' => 'Pengaturan', 'profile' => 'Profil', 'notifications' => 'Notifikasi',
        'whatsapp-web' => 'WhatsApp', 'whatsapp' => 'WhatsApp', 'visitor-logs' => 'Log Pengunjung', 'admin' => 'Administrasi',
    ];

    private const VERBS = [
        'store' => 'Menambah', 'create' => 'Menambah', 'add' => 'Menambah', 'update' => 'Mengubah', 'edit' => 'Mengubah',
        'destroy' => 'Menghapus', 'delete' => 'Menghapus', 'remove' => 'Menghapus', 'clear' => 'Mengosongkan', 'approve' => 'Menyetujui',
        'reject' => 'Menolak', 'sign' => 'Menandatangani', 'verify' => 'Memverifikasi', 'upload' => 'Mengunggah', 'send' => 'Mengirim',
        'publish' => 'Menerbitkan', 'print' => 'Mencetak', 'export' => 'Mengekspor', 'import' => 'Mengimpor', 'restore' => 'Memulihkan',
        'toggle' => 'Mengubah status', 'status' => 'Mengubah status', 'move' => 'Memindahkan', 'copy' => 'Menyalin', 'rename' => 'Mengganti nama',
        'extract' => 'Mengekstrak', 'revoke' => 'Mencabut', 'reset' => 'Mengatur ulang', 'accept' => 'Menerima', 'cancel' => 'Membatalkan',
        'logout-others' => 'Mengeluarkan perangkat lain', 'read' => 'Menandai dibaca', 'mark' => 'Menandai', 'stamp' => 'Membubuhkan stempel',
        'initiate' => 'Memulai', 'access' => 'Meminta akses', 'withdraw' => 'Menarik', 'resend' => 'Mengirim ulang',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $actor = $request->user();
        $response = $next($request);

        try {
            if ($this->shouldRecord($request)) {
                $user = $request->user() ?? $actor;
                if ($user) {
                    // Disimpan di terminate() setelah respons terkirim (tanpa menambah waktu tunggu).
                    $request->attributes->set('activity_log_entry', $this->build($request, $response, $user, $started));
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $entry = $request->attributes->get('activity_log_entry');
        if (!$entry) {
            return;
        }
        $request->attributes->remove('activity_log_entry');
        try {
            if (!ActivityLog::ensureSchema()) {
                return;
            }
            $entry['ip_location'] = Str::limit((string) DeviceSessionService::lookupIp($entry['ip']), 160, '') ?: null;
            ActivityLog::create($entry);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function shouldRecord(Request $request): bool
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }
        return !$request->is(...self::SKIP_PATHS);
    }

    private function build(Request $request, Response $response, $user, float $started): array
    {
        $route = $request->route();
        $routeName = $route?->getName();
        $isApp = $request->is('api/mobile/*') || (bool) $request->bearerToken();
        $status = $response->getStatusCode();

        // Redirect "kembali dengan error validasi/pesan gagal" juga dihitung gagal.
        $session = $request->hasSession() ? $request->session() : null;
        $failedFlash = $session && ($session->has('errors') || $session->has('error'));
        $success = $status < 400 && !$failedFlash;

        $device = $this->deviceInfo($request, $isApp);
        $params = collect($route?->parameters() ?? [])
            ->map(fn ($v) => is_object($v) && method_exists($v, 'getKey') ? $v->getKey() : (is_scalar($v) ? $v : null))
            ->filter(fn ($v) => $v !== null)
            ->all();

        return [
            'user_id' => $user->id,
            'user_name' => Str::limit((string) $user->name, 150, ''),
            'pangkat' => $user->pangkat ?? null,
            'nrp' => $user->nrp ?? null,
            'role' => $user->role ?? null,
            'channel' => $isApp ? 'android' : 'web',
            'method' => $request->method(),
            'route' => $routeName,
            'path' => Str::limit('/' . ltrim($request->path(), '/'), 255, ''),
            'module' => Str::limit($this->moduleLabel($request, $routeName), 40, ''),
            'action' => Str::limit($this->describe($request, $routeName), 200, ''),
            'subject' => $params ?: null,
            'payload' => $this->sanitize($request->except(['_token', '_method', 'password', 'password_confirmation', 'current_password'])),
            'status' => $status,
            'success' => $success,
            'ip' => $request->ip(),
            'device' => $device['label'],
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'latitude' => $device['lat'],
            'longitude' => $device['lng'],
            'location_accuracy' => $device['accuracy'],
            'location_at' => $device['location_at'],
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'created_at' => now(),
        ];
    }

    private function segments(Request $request, ?string $routeName): array
    {
        $path = trim($request->path(), '/');
        $path = preg_replace('#^(api/mobile|api/v1|api|admin)/#', '', $path);
        return [explode('/', (string) $path), $routeName ? explode('.', $routeName) : []];
    }

    private function moduleLabel(Request $request, ?string $routeName): string
    {
        [$segs, $names] = $this->segments($request, $routeName);
        foreach (array_merge($names, $segs) as $s) {
            if (isset(self::MODULE_LABELS[$s])) {
                return self::MODULE_LABELS[$s];
            }
        }
        return Str::headline($names[0] ?? $segs[0] ?? 'Umum');
    }

    private function describe(Request $request, ?string $routeName): string
    {
        [$segs, $names] = $this->segments($request, $routeName);
        $verb = null;
        foreach (array_reverse(array_merge($segs, $names)) as $s) {
            foreach (preg_split('/[-_]/', strtolower($s)) as $w) {
                if (isset(self::VERBS[$w])) {
                    $verb = self::VERBS[$w];
                    break 2;
                }
            }
        }
        $verb ??= match ($request->method()) {
            'DELETE' => 'Menghapus',
            'PUT', 'PATCH' => 'Mengubah',
            default => 'Memproses',
        };
        $module = $this->moduleLabel($request, $routeName);
        $detail = $routeName ? Str::headline(str_replace(['.', '-'], ' ', last($names))) : Str::headline(last($segs) ?: '');
        return trim("{$verb} {$module}" . ($detail && !str_contains(strtolower($verb), strtolower($detail)) ? " ({$detail})" : ''));
    }

    /** Isi form untuk jejak: nilai sensitif disamarkan, teks panjang & gambar base64 dipotong. */
    private function sanitize(array $input, int $depth = 0): ?array
    {
        if ($input === []) {
            return null;
        }
        $out = [];
        $n = 0;
        foreach ($input as $key => $value) {
            if (++$n > 40) {
                $out['…'] = 'dipotong';
                break;
            }
            if (is_string($key) && preg_match(self::SENSITIVE, $key)) {
                $out[$key] = '[disamarkan]';
                continue;
            }
            if ($value instanceof UploadedFile) {
                $out[$key] = '[berkas: ' . Str::limit($value->getClientOriginalName(), 80) . ', ' . round($value->getSize() / 1024) . ' KB]';
            } elseif (is_array($value)) {
                $out[$key] = $depth >= 2 ? '[' . count($value) . ' item]' : $this->sanitize($value, $depth + 1);
            } elseif (is_string($value)) {
                $out[$key] = str_starts_with($value, 'data:') ? '[gambar/berkas base64]' : Str::limit($value, 200);
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /** @return array{label: ?string, lat: ?float, lng: ?float, accuracy: ?int, location_at: mixed} */
    private function deviceInfo(Request $request, bool $isApp): array
    {
        $d = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('user_devices')) {
                if ($isApp) {
                    $token = $request->user()?->currentAccessToken();
                    if ($token instanceof PersonalAccessToken) {
                        $d = UserDevice::where('kind', 'android')->where('token_id', $token->id)->first();
                    }
                } elseif ($request->hasSession()) {
                    $d = UserDevice::where('kind', 'web')->where('session_id', $request->session()->getId())->first();
                }
            }
        } catch (\Throwable) {
        }

        if ($isApp) {
            $label = $d
                ? trim(Str::title((string) $d->manufacturer) . ' ' . $d->model) . ($d->os_version ? ' · Android ' . $d->os_version : '') . ($d->app_version ? ' · Aplikasi ' . $d->app_version : '')
                : 'Aplikasi Android (' . Str::limit((string) $request->userAgent(), 40, '') . ')';
        } else {
            $agent = new Agent();
            $agent->setUserAgent((string) $request->userAgent());
            $browser = $agent->browser() ?: 'Peramban';
            $platform = $agent->platform() ?: 'perangkat tidak dikenal';
            $kind = $agent->isDesktop() ? 'Komputer' : ($agent->isTablet() ? 'Tablet' : 'Ponsel');
            $label = "{$browser} " . ($agent->version($browser) ?: '') . " · {$platform} " . ($agent->version($platform) ?: '') . " · {$kind}";
            $label = preg_replace('/\s+/', ' ', $label);
        }

        return [
            'label' => Str::limit(trim($label), 160, ''),
            'lat' => $d?->latitude,
            'lng' => $d?->longitude,
            'accuracy' => $d?->location_accuracy,
            'location_at' => $d?->location_at,
        ];
    }
}
