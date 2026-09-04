<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'avatar',
        'password',
        'pangkat',
        'nrp',
        'phone',
        'role',
        'is_active',
        'can_access_mitra',
        'can_access_technical_cash',
        'can_manage_koperasi',
        'activation_token',
        'must_change_password', // TAMBAHKAN INI: Agar sistem tahu user perlu ganti password
        'reset_token',
        'token_expires_at',
        'birth_date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'activation_token', // Sembunyikan token dari respon JSON demi keamanan
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'can_access_mitra' => 'boolean',
            'can_access_technical_cash' => 'boolean',
            'can_manage_koperasi' => 'boolean',
            'must_change_password' => 'boolean', // Tambahkan cast agar logika Vue Bapak akurat
            'token_expires_at' => 'datetime',
        ];
    }

    /**
     * Relasi Rekening Koperasi Anggota
     */
    public function koperasiAccount()
    {
        return $this->hasOne(KoperasiAccount::class, 'user_id');
    }

    /**
     * Relasi Pinjaman Koperasi
     */
    public function koperasiLoans()
    {
        return $this->hasMany(KoperasiLoan::class, 'user_id')->latest();
    }

    /**
     * Cek apakah user adalah pengurus koperasi atau admin
     */
    public function isPengurusKoperasi(): bool
    {
        return $this->role === 'admin' || (bool)$this->can_manage_koperasi || $this->name === 'I Gusti Sultan H.A, A.Md.Kom';
    }
}
