<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan akses seorang personel ke penyimpanan backup milik rekannya.
 */
class PcAccessRequest extends Model
{
    public const STATUS_VERIFYING = 'verifying'; // menunggu kode WhatsApp dari peminta
    public const STATUS_PENDING = 'pending';     // menunggu keputusan pemilik
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVOKED = 'revoked';     // dicabut pemilik setelah disetujui
    public const STATUS_CANCELLED = 'cancelled'; // dibatalkan peminta

    /** Status yang masih "hidup" (hanya boleh ada satu per PC per peminta). */
    public const OPEN_STATUSES = [self::STATUS_VERIFYING, self::STATUS_PENDING, self::STATUS_APPROVED];

    protected $table = 'pc_access_requests';

    protected $fillable = [
        'pc_id', 'requester_id', 'status', 'reason',
        'otp_hash', 'otp_expires_at', 'otp_sent_at', 'otp_attempts', 'verified_at',
        'decided_by', 'decided_at', 'decision_note',
    ];

    protected $hidden = ['otp_hash'];

    protected $casts = [
        'pc_id' => 'integer',
        'requester_id' => 'integer',
        'decided_by' => 'integer',
        'otp_attempts' => 'integer',
        'otp_expires_at' => 'datetime',
        'otp_sent_at' => 'datetime',
        'verified_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function pc(): BelongsTo
    {
        return $this->belongsTo(Pc::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    private static bool $schemaChecked = false;

    /**
     * Cadangan bila server belum menjalankan `php artisan migrate`
     * (pola yang sama dengan BackupShare::ensureSchema).
     */
    public static function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            if (Schema::hasTable('pc_access_requests')) {
                return;
            }
            Schema::create('pc_access_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pc_id')->constrained('pcs')->cascadeOnDelete();
                $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 20)->default('verifying')->index();
                $table->string('reason', 500)->nullable();
                $table->string('otp_hash')->nullable();
                $table->timestamp('otp_expires_at')->nullable();
                $table->timestamp('otp_sent_at')->nullable();
                $table->unsignedTinyInteger('otp_attempts')->default(0);
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->string('decision_note', 500)->nullable();
                $table->timestamps();
                $table->index(['pc_id', 'requester_id', 'status']);
            });
        } catch (\Throwable $e) {
            Log::error('[PC_ACCESS] Gagal menyiapkan tabel pc_access_requests: ' . $e->getMessage());
        }
    }
}
