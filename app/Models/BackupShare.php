<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class BackupShare extends Model
{
    use HasFactory;

    protected $table = 'backup_shares';

    protected $fillable = [
        'pc_id',
        'backup_id',
        'share_token',
        'pin',
        'is_active',
        'created_by',
        'share_name',
        'access_count',
        'last_accessed_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'access_count' => 'integer',
        'last_accessed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $appends = [
        'share_url',
    ];

    /**
     * URL tautan berbagi lengkap
     */
    public function getShareUrlAttribute(): string
    {
        return route('backup.shared.view', $this->share_token);
    }

    /**
     * Relasi ke Pangkalan PC
     */
    public function pc(): BelongsTo
    {
        return $this->belongsTo(Pc::class, 'pc_id');
    }

    /**
     * Relasi ke Folder Spesifik yang dibagikan (null jika seluruh pangkalan)
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Backup::class, 'backup_id');
    }

    /**
     * Relasi ke Admin / Personel pembuat tautan
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Validasi kecocokan PIN keamanan
     */
    public function verifyPin(?string $inputPin): bool
    {
        if (empty($this->pin) || empty($inputPin)) {
            return false;
        }

        return trim((string)$this->pin) === trim((string)$inputPin);
    }

    /**
     * Validasi keamanan mendalam:
     * Memastikan item (berkas/subfolder) berada di dalam hierarki folder yang dibagikan.
     */
    public function isWithinScope(Backup $item): bool
    {
        // 1. Wajib berasal dari PC yang sama
        if ((int)$item->pc_id !== (int)$this->pc_id) {
            return false;
        }

        // 2. Jika membagikan seluruh pangkalan (backup_id null), semua berkas di PC ini sah
        if (empty($this->backup_id)) {
            return true;
        }

        // 3. Jika item adalah folder akar yang dibagikan itu sendiri
        if ((int)$item->id === (int)$this->backup_id) {
            return true;
        }

        // 4. Periksa rantai parent ke atas hingga mencapai folder akar yang dibagikan
        $currentParentId = $item->parent_id;
        $maxDepth = 50; // Mencegah infinite loop jika ada siklus data
        $depth = 0;

        while ($currentParentId && $depth < $maxDepth) {
            if ((int)$currentParentId === (int)$this->backup_id) {
                return true;
            }

            $parent = Backup::select('id', 'parent_id')->find($currentParentId);
            if (!$parent) {
                break;
            }

            $currentParentId = $parent->parent_id;
            $depth++;
        }

        return false;
    }

    /**
     * Memastikan skema tabel backup_shares selalu siap (Self-Healing Schema)
     */
    public static function ensureSchema(): void
    {
        try {
            if (!Schema::hasTable('backup_shares')) {
                Schema::create('backup_shares', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('pc_id')->constrained('pcs')->onDelete('cascade');
                    $table->foreignId('backup_id')->nullable()->constrained('backups')->onDelete('cascade');
                    $table->string('share_token', 64)->unique()->index();
                    $table->string('pin', 255);
                    $table->boolean('is_active')->default(true)->index();
                    $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                    $table->string('share_name')->nullable();
                    $table->unsignedBigInteger('access_count')->default(0);
                    $table->timestamp('last_accessed_at')->nullable();
                    $table->timestamp('expires_at')->nullable();
                    $table->timestamps();
                });
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ensureSchema backup_shares warning: ' . $e->getMessage());
        }
    }
}
