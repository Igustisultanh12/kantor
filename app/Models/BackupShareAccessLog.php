<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Carbon\Carbon;

class BackupShareAccessLog extends Model
{
    use HasFactory;

    protected $table = 'backup_share_access_logs';

    protected $fillable = [
        'backup_share_id',
        'user_id',
        'access_type',
        'pangkat',
        'nama',
        'nrp',
        'satuan',
        'whatsapp',
        'ip_address',
        'user_agent',
        'access_count',
        'first_accessed_at',
        'last_accessed_at',
        'expires_at',
    ];

    protected $casts = [
        'access_count' => 'integer',
        'first_accessed_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function share(): BelongsTo
    {
        return $this->belongsTo(BackupShare::class, 'backup_share_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Self-healing skema untuk tabel backup_share_access_logs
     */
    public static function ensureSchema(): void
    {
        try {
            if (!Schema::hasTable('backup_share_access_logs')) {
                Schema::create('backup_share_access_logs', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('backup_share_id')->constrained('backup_shares')->onDelete('cascade');
                    $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
                    $table->string('access_type', 20)->default('tamu')->index();
                    $table->string('pangkat', 100)->nullable();
                    $table->string('nama', 150)->index();
                    $table->string('nrp', 50)->nullable()->index();
                    $table->string('satuan', 150)->nullable();
                    $table->string('whatsapp', 30)->nullable();
                    $table->string('ip_address', 45)->nullable()->index();
                    $table->text('user_agent')->nullable();
                    $table->unsignedInteger('access_count')->default(1);
                    $table->timestamp('first_accessed_at')->nullable();
                    $table->timestamp('last_accessed_at')->nullable()->index();
                    $table->timestamp('expires_at')->nullable()->index();
                    $table->timestamps();
                });
            }
        } catch (\Throwable $e) {
            // Abaikan jika sudah ada
        }
    }

    /**
     * Catat atau perbarui log akses folder berbagi
     */
    public static function recordAccess(
        int $shareId,
        string $accessType,
        string $nama,
        ?string $nrp = null,
        ?string $pangkat = null,
        ?string $satuan = null,
        ?string $whatsapp = null,
        ?int $userId = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?Carbon $expiresAt = null
    ): self {
        self::ensureSchema();

        $query = self::where('backup_share_id', $shareId);

        if ($accessType === 'personel' && $userId) {
            $query->where('user_id', $userId);
        } else {
            // Untuk tamu: cari berdasarkan whatsapp atau nrp atau nama+ip
            $query->where(function ($q) use ($whatsapp, $nrp, $nama, $ip) {
                $hasCondition = false;
                if (!empty($whatsapp)) {
                    $q->where('whatsapp', $whatsapp);
                    $hasCondition = true;
                }
                if (!empty($nrp) && $nrp !== '-') {
                    if ($hasCondition) {
                        $q->orWhere('nrp', $nrp);
                    } else {
                        $q->where('nrp', $nrp);
                        $hasCondition = true;
                    }
                }
                if (!$hasCondition) {
                    $q->where('nama', $nama);
                    if (!empty($ip)) {
                        $q->where('ip_address', $ip);
                    }
                }
            });
        }

        $log = $query->latest('last_accessed_at')->first();

        if ($log) {
            $log->access_count = ((int)$log->access_count) + 1;
            $log->last_accessed_at = now();
            if (!empty($pangkat)) $log->pangkat = $pangkat;
            if (!empty($nama)) $log->nama = $nama;
            if (!empty($satuan)) $log->satuan = $satuan;
            if (!empty($nrp) && $nrp !== '-') $log->nrp = $nrp;
            if (!empty($whatsapp)) $log->whatsapp = $whatsapp;
            if (!empty($ip)) $log->ip_address = $ip;
            if (!empty($userAgent)) $log->user_agent = substr($userAgent, 0, 500);
            if ($expiresAt) $log->expires_at = $expiresAt;
            $log->save();

            return $log;
        }

        return self::create([
            'backup_share_id' => $shareId,
            'user_id' => $userId,
            'access_type' => $accessType,
            'pangkat' => $pangkat,
            'nama' => $nama,
            'nrp' => $nrp ?: '-',
            'satuan' => $satuan ?: ($accessType === 'personel' ? 'Personel SINDEN' : '-'),
            'whatsapp' => $whatsapp,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
            'access_count' => 1,
            'first_accessed_at' => now(),
            'last_accessed_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }
}
