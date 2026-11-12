<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sesi login per perangkat: daftar sesi web (tabel sessions) dan token
 * aplikasi Android (personal_access_tokens), diperkaya info perangkat dari
 * UserDevice, serta mengeluarkan (logout) perangkat dari jarak jauh.
 */
class DeviceSessionService
{
    /** Catat sesi web yang sedang dipakai (dibatasi tiap 60 detik). */
    public function touchWeb(Request $request): void
    {
        $user = $request->user();
        // Request API aplikasi (Bearer) juga lewat grup web: itu bukan sesi peramban.
        if (!$user || !$request->hasSession() || $request->bearerToken() || $request->is('api/*') || !UserDevice::ensureSchema()) {
            return;
        }
        $sid = $request->session()->getId();
        $key = "dev-touch:{$sid}";
        if (Cache::has($key)) {
            return;
        }
        Cache::put($key, 1, 60);

        $agent = new Agent();
        $agent->setUserAgent((string) $request->userAgent());
        $platform = $agent->platform() ?: null;

        UserDevice::updateOrCreate(
            ['kind' => 'web', 'session_id' => $sid],
            [
                'user_id' => $user->id,
                'os' => $platform,
                'os_version' => $platform ? ($agent->version($platform) ?: null) : null,
                'browser' => trim(($agent->browser() ?: '') . ' ' . ($agent->browser() ? ($agent->version($agent->browser()) ?: '') : '')) ?: null,
                'model' => $agent->isDesktop() ? 'Komputer' : ($agent->device() ?: ($agent->isTablet() ? 'Tablet' : 'Ponsel')),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'ip' => $request->ip(),
                'last_seen_at' => now(),
            ]
        );
    }

    /** Data tambahan dari peramban (baterai, layar, lokasi bila izin sudah diberikan). */
    public function reportWeb(Request $request, array $data): void
    {
        $this->touchWeb($request);
        $device = UserDevice::where('kind', 'web')->where('session_id', $request->session()->getId())->first();
        if ($device) {
            $device->fill($this->telemetry($data) + ['ip' => $request->ip(), 'last_seen_at' => now()])->save();
        }
    }

    /** Laporan berkala aplikasi Android (dipanggil saat aplikasi dibuka & tiap beberapa menit). */
    public function heartbeatAndroid(Request $request, array $data): void
    {
        $token = $request->user()?->currentAccessToken();
        if (!$token instanceof PersonalAccessToken || !UserDevice::ensureSchema()) {
            return;
        }
        $fields = $this->telemetry($data) + [
            'user_id' => $request->user()->id,
            'device_key' => $data['device_id'] ?? null,
            'manufacturer' => $data['manufacturer'] ?? null,
            'model' => $data['model'] ?? null,
            'os' => 'Android',
            'os_version' => isset($data['os_version']) ? trim($data['os_version'] . (isset($data['sdk']) ? " (API {$data['sdk']})" : '')) : null,
            'app_version' => $data['app_version'] ?? null,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'ip' => $request->ip(),
            'last_seen_at' => now(),
        ];
        if (!empty($data['push_token'])) {
            $fields['push_token_hash'] = DeviceToken::hashOf($data['push_token']);
        }
        UserDevice::updateOrCreate(['kind' => 'android', 'token_id' => $token->id], array_filter($fields, fn ($v) => $v !== null));
    }

    private function telemetry(array $d): array
    {
        $out = [];
        if (isset($d['battery'])) $out['battery_level'] = max(0, min(100, (int) $d['battery']));
        if (array_key_exists('charging', $d) && $d['charging'] !== null) $out['charging'] = (bool) $d['charging'];
        if (!empty($d['network'])) $out['network'] = Str::limit((string) $d['network'], 30, '');
        if (!empty($d['screen'])) $out['screen'] = Str::limit((string) $d['screen'], 30, '');
        if (isset($d['lat'], $d['lng']) && is_numeric($d['lat']) && is_numeric($d['lng'])
            && abs((float) $d['lat']) <= 90 && abs((float) $d['lng']) <= 180) {
            $out['latitude'] = (float) $d['lat'];
            $out['longitude'] = (float) $d['lng'];
            $out['location_accuracy'] = isset($d['accuracy']) ? (int) round((float) $d['accuracy']) : null;
            $out['location_at'] = isset($d['location_at']) && is_numeric($d['location_at'])
                ? \Illuminate\Support\Carbon::createFromTimestampMs((int) $d['location_at'])
                : now();
        }
        return $out;
    }

    public function webSessionsSupported(): bool
    {
        return config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'));
    }

    /**
     * Semua sesi aktif milik user. $currentSession / $currentToken menandai perangkat yang sedang dipakai.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(User $user, ?string $currentSession, ?int $currentToken): array
    {
        UserDevice::ensureSchema();
        $devices = UserDevice::where('user_id', $user->id)->get();
        $items = [];

        if ($this->webSessionsSupported()) {
            $lifetime = (int) config('session.lifetime', 120) * 60;
            $sessions = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('last_activity', '>=', now()->timestamp - max($lifetime, 3600))
                ->get(['id', 'ip_address', 'user_agent', 'last_activity']);
            foreach ($sessions as $s) {
                $d = $devices->first(fn ($x) => $x->kind === 'web' && $x->session_id === $s->id);
                // Rute API aplikasi berada di grup web sehingga tiap panggilan aplikasi ikut membuat baris
                // sesi. Itu bukan login peramban: tampilkan hanya sesi yang tercatat dari peramban.
                $ua = (string) $s->user_agent;
                if (!$d && (str_starts_with($ua, 'SISINDEN-Android') || !str_contains($ua, 'Mozilla/'))) {
                    continue;
                }
                $items[] = $this->payload('web', $s->id, $d, [
                    'ip' => $d?->ip ?: $s->ip_address,
                    'user_agent' => $s->user_agent,
                    'last_seen' => max((int) $s->last_activity, (int) ($d?->last_seen_at?->timestamp ?? 0)),
                    'current' => $s->id === $currentSession,
                ]);
            }
        }

        $tokens = PersonalAccessToken::where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->get(['id', 'name', 'last_used_at', 'created_at']);
        foreach ($tokens as $t) {
            $d = $devices->first(fn ($x) => $x->kind === 'android' && (int) $x->token_id === (int) $t->id);
            $items[] = $this->payload('android', (string) $t->id, $d, [
                'ip' => $d?->ip,
                'user_agent' => $d?->user_agent,
                'last_seen' => max((int) ($t->last_used_at?->timestamp ?? $t->created_at?->timestamp ?? 0), (int) ($d?->last_seen_at?->timestamp ?? 0)),
                'current' => (int) $t->id === (int) $currentToken,
                'signed_in_at' => $t->created_at?->toIso8601String(),
                'token_name' => $t->name,
            ]);
        }

        // Rekaman perangkat yang sesinya sudah hilang tidak perlu disimpan lagi.
        $liveWeb = collect($items)->where('kind', 'web')->pluck('id')->all();
        $liveTok = collect($items)->where('kind', 'android')->pluck('id')->map(fn ($v) => (int) $v)->all();
        $devices->each(function (UserDevice $d) use ($liveWeb, $liveTok) {
            $gone = $d->kind === 'web' ? !in_array($d->session_id, $liveWeb, true) : !in_array((int) $d->token_id, $liveTok, true);
            if ($gone && $d->updated_at && $d->updated_at->lt(now()->subMinutes(10))) {
                $d->delete();
            }
        });

        usort($items, fn ($a, $b) => [$b['current'], $b['last_seen']] <=> [$a['current'], $a['last_seen']]);
        return $items;
    }

    private function payload(string $kind, string $id, ?UserDevice $d, array $base): array
    {
        $ua = (string) ($base['user_agent'] ?? '');
        $os = $d?->os;
        $browser = $d?->browser;
        if ($kind === 'web' && (!$os || !$browser) && $ua !== '') {
            $agent = new Agent();
            $agent->setUserAgent($ua);
            $os = $os ?: ($agent->platform() ?: null);
            $browser = $browser ?: ($agent->browser() ?: null);
        }
        $ip = $base['ip'] ?? null;

        return [
            'kind' => $kind,
            'id' => $id,
            'current' => (bool) $base['current'],
            'name' => $kind === 'android'
                ? trim(($d?->manufacturer ? Str::title($d->manufacturer) . ' ' : '') . ($d?->model ?: 'Aplikasi Android'))
                : trim(($browser ?: 'Peramban') . ' di ' . ($os ?: 'perangkat tidak dikenal')),
            'model' => $d?->model,
            'os' => $os,
            'os_version' => $d?->os_version,
            'browser' => $browser,
            'app_version' => $d?->app_version,
            'device_key' => $d?->device_key,
            'ip' => $ip,
            'ip_location' => $ip ? $this->ipLocation($ip, $d) : null,
            'battery' => $d?->battery_level,
            'charging' => $d?->charging,
            'network' => $d?->network,
            'screen' => $d?->screen,
            'location' => $d && $d->latitude !== null ? [
                'lat' => $d->latitude,
                'lng' => $d->longitude,
                'accuracy' => $d->location_accuracy,
                'at' => $d->location_at?->toIso8601String(),
            ] : null,
            'user_agent' => $ua ?: null,
            'last_seen' => $base['last_seen'] ? \Illuminate\Support\Carbon::createFromTimestamp($base['last_seen'])->toIso8601String() : null,
            'signed_in_at' => $base['signed_in_at'] ?? $d?->created_at?->toIso8601String(),
        ];
    }

    /** Perkiraan lokasi dari IP (disimpan 1 hari per IP). */
    private function ipLocation(string $ip, ?UserDevice $d): ?string
    {
        if ($d && $d->ip_location_for === $ip && $d->ip_location) {
            return $d->ip_location;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'Jaringan lokal';
        }
        $label = Cache::remember("ip-loc:{$ip}", 86400, function () use ($ip) {
            try {
                $r = Http::timeout(2)->get("http://ip-api.com/json/{$ip}?fields=status,city,regionName,country,isp");
                if ($r->successful() && $r->json('status') === 'success') {
                    return collect([$r->json('city'), $r->json('regionName'), $r->json('country')])->filter()->unique()->implode(', ')
                        . ($r->json('isp') ? ' · ' . $r->json('isp') : '');
                }
            } catch (\Throwable) {
            }
            return null;
        });
        if ($d && $label) {
            $d->forceFill(['ip_location' => Str::limit($label, 160, ''), 'ip_location_for' => $ip])->saveQuietly();
        }
        return $label;
    }

    /** Keluarkan satu perangkat. Mengembalikan false bila sesi tidak ditemukan / milik orang lain. */
    public function revoke(User $user, string $kind, string $id): bool
    {
        UserDevice::ensureSchema();
        if ($kind === 'web') {
            if (!$this->webSessionsSupported()) {
                return false;
            }
            $deleted = DB::table(config('session.table', 'sessions'))->where('id', $id)->where('user_id', $user->id)->delete();
            UserDevice::where('user_id', $user->id)->where('kind', 'web')->where('session_id', $id)->delete();
            if ($deleted) {
                // Cookie "Ingat saya" di perangkat itu tidak boleh memasukkan sesi baru.
                $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
            }
            return $deleted > 0;
        }
        if ($kind === 'android') {
            $token = PersonalAccessToken::where('id', (int) $id)
                ->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id)->first();
            if (!$token) {
                return false;
            }
            $devices = UserDevice::where('user_id', $user->id)->where('kind', 'android')->where('token_id', $token->id)->get();
            // Hentikan juga notifikasi push ke HP tersebut.
            $hashes = $devices->pluck('push_token_hash')->filter()->all();
            if ($hashes && Schema::hasTable('device_tokens')) {
                DeviceToken::where('user_id', $user->id)->whereIn('token_hash', $hashes)->delete();
            }
            $devices->each->delete();
            $token->delete();
            return true;
        }
        return false;
    }

    /** Keluarkan semua perangkat kecuali yang sedang dipakai. */
    public function revokeOthers(User $user, ?string $currentSession, ?int $currentToken): int
    {
        $n = 0;
        foreach ($this->list($user, $currentSession, $currentToken) as $item) {
            if (!$item['current'] && $this->revoke($user, $item['kind'], $item['id'])) {
                $n++;
            }
        }
        return $n;
    }
}
