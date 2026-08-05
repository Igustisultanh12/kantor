<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'pangkat',
        'nrp',
        'phone',
        'role',
        'is_active',
        'can_access_mitra',
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
            'must_change_password' => 'boolean', // Tambahkan cast agar logika Vue Bapak akurat
            'token_expires_at' => 'datetime',
        ];
    }
}