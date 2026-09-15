<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class BackupShareGuest extends Model
{
    use HasFactory;

    protected $table = 'backup_share_guests';

    protected $fillable = [
        'backup_share_id',
        'nrp',
        'nama',
        'satuan',
        'ip_address',
        'user_agent',
        'accessed_at',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
    ];

    /**
     * Relasi ke konfigurasi folder berbagi (BackupShare)
     */
    public function share(): BelongsTo
    {
        return $this->belongsTo(BackupShare::class, 'backup_share_id');
    }

    /**
     * Self-healing skema untuk tabel backup_share_guests
     */
    public static function ensureSchema(): void
    {
        try {
            if (!Schema::hasTable('backup_share_guests')) {
                Schema::create('backup_share_guests', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('backup_share_id')->constrained('backup_shares')->onDelete('cascade');
                    $table->string('nrp', 50)->nullable()->index();
                    $table->string('nama', 150)->index();
                    $table->string('satuan', 150)->index();
                    $table->string('ip_address', 45)->nullable();
                    $table->text('user_agent')->nullable();
                    $table->timestamp('accessed_at')->nullable()->index();
                    $table->timestamps();
                });
            }
        } catch (\Throwable $e) {
            // Abaikan jika sudah ada atau race condition
        }
    }
}
