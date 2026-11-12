<?php

namespace App\Support;

use App\Models\User;

/**
 * Sumber kebenaran tunggal hak akses aplikasi mobile SISINDEN.
 *
 * Aturan di kelas ini meniru aturan otorisasi yang SUDAH berlaku di web
 * (controller + AuthenticatedLayout.vue). Daftar permission yang dihasilkan
 * dipakai untuk dua hal sekaligus:
 *   1. Dikirim ke aplikasi Android agar menu dirender sesuai hak akses.
 *   2. Ditegakkan oleh middleware `mobile.auth:<ability>` di setiap endpoint
 *      /api/mobile/* sehingga backend tetap menjadi otoritas akhir.
 *
 * Jika admin mengubah role atau flag can_access_* seorang personel di web,
 * perubahan otomatis berlaku pada permintaan API berikutnya (dihitung ulang
 * per-request, tidak di-cache di token).
 */
class MobileAccess
{
    /**
     * Hak akses TIDAK PERNAH ditentukan dari nama pengguna (nama bisa diganti di profil).
     * Pengecualian lama berbasis nama sudah dipindahkan ke flag database yang hanya
     * dapat diubah Admin: is_developer, can_access_cash, can_access_commander,
     * can_manage_sp_jaga, can_sign_sp_jaga (migrasi 2026_11_13_100000).
     */
    public const ACCESS_FLAGS = [
        'is_developer', 'can_access_cash', 'can_access_commander', 'can_manage_sp_jaga', 'can_sign_sp_jaga',
        'can_access_mitra', 'can_access_technical_cash', 'can_manage_koperasi', 'can_access_ibu_beti',
    ];

    /** Daftar role yang dikenal sistem (dari form Kelola Pengguna di web). */
    public const KNOWN_ROLES = [
        'admin', 'komandan', 'wadan', 'pasops', 'staf', 'personel',
        'anggotasintel', 'danunitteknis', 'kaurmintel', 'paurset', 'danunit1', 'user',
    ];

    public static function normalizedRole(?User $user): string
    {
        return strtolower(preg_replace('/[\s_-]+/', '', (string) ($user?->role ?? '')));
    }

    public static function isAdmin(User $user): bool
    {
        return $user->role === 'admin';
    }

    public static function isDeveloper(User $user): bool
    {
        return (bool) ($user->is_developer ?? false);
    }

    /** Admin atau pengembang sistem (pengganti pemeriksaan "role admin ATAU nama tertentu"). */
    public static function isSuperAdmin(?User $user): bool
    {
        return $user !== null && (self::isAdmin($user) || self::isDeveloper($user));
    }

    public static function canAccessCash(User $user): bool
    {
        return self::isSuperAdmin($user) || (bool) ($user->can_access_cash ?? false);
    }

    public static function canViewCommanderAccount(User $user): bool
    {
        return self::canManageCommanderAccount($user) || self::isKomandan($user);
    }

    public static function canManageCommanderAccount(User $user): bool
    {
        return self::isSuperAdmin($user) || (bool) ($user->can_access_commander ?? false);
    }

    public static function isSpJagaAdmin(User $user): bool
    {
        return self::isSuperAdmin($user) || (bool) ($user->can_manage_sp_jaga ?? false);
    }

    public static function canApproveAsCommander(User $user): bool
    {
        return self::isSuperAdmin($user) || self::isKomandan($user);
    }

    public static function isAnggotaSintel(User $user): bool
    {
        if (self::isAdmin($user)) {
            return false;
        }
        $jabatan = strtolower((string) ($user->jabatan ?? ''));
        return self::normalizedRole($user) === 'anggotasintel' || str_contains($jabatan, 'anggota sintel');
    }

    public static function isDanUnitTeknis(User $user): bool
    {
        return self::normalizedRole($user) === 'danunitteknis';
    }

    public static function isKomandan(User $user): bool
    {
        return self::normalizedRole($user) === 'komandan';
    }

    public static function isPasops(User $user): bool
    {
        return in_array(self::normalizedRole($user), ['pasops', 'admin', 'komandan'], true)
            || self::isDeveloper($user)
            || (bool) ($user->can_sign_sp_jaga ?? false);
    }

    /**
     * Hitung seluruh permission untuk satu personel.
     *
     * @return array<string, bool>
     */
    public static function permissions(User $user): array
    {
        $admin = self::isAdmin($user);
        $dev = self::isDeveloper($user);
        $sintel = self::isAnggotaSintel($user);
        $danUnitTeknis = self::isDanUnitTeknis($user);
        $komandan = self::isKomandan($user);
        $regular = !$sintel; // menu umum web disembunyikan untuk Anggota Sintel

        // CashController: admin, developer, atau flag can_access_cash.
        $cash = self::canAccessCash($user);
        // CommanderAccountController: komandan boleh melihat; ubah hanya admin/developer/flag.
        $commanderView = self::canViewCommanderAccount($user);
        $commanderManage = self::canManageCommanderAccount($user);
        // MitraPaymentController@index
        $mitra = $admin || $dev || (bool) $user->can_access_mitra;
        // TechnicalUnitCashController@index
        $technicalCash = $admin || $danUnitTeknis || (bool) $user->can_access_technical_cash || $dev;
        // SkhppController@approve / SignatureRequestController
        $commanderApproval = $admin || $komandan || $dev;
        // IbuBetiAccountController@checkAccess: admin, developer, atau diberi akses (can_access_ibu_beti).
        $ibuBeti = $admin || $dev || (bool) $user->can_access_ibu_beti;
        // SpJagaController@index
        $spJagaAdmin = self::isSpJagaAdmin($user);

        return [
            'dashboard.view' => true,
            'profile.view' => true,
            'notifications.view' => true,

            // LiveChatController: semua personel; operator (admin/developer) mengelola utas.
            'chat.view' => true,
            'chat.manage' => $admin || $dev,

            'attendance.view' => true,
            'attendance.create' => true,
            'attendance.view_all' => in_array(self::normalizedRole($user), ['admin', 'komandan', 'pasops'], true),

            'duty_roster.view' => true,
            'duty_roster.view_unpublished' => $spJagaAdmin || self::isPasops($user),
            'duty_roster.pdf' => true,
            // SpJagaController@index ($isPasops) — penandatangan TTE SP Jaga.
            'duty_roster.sign' => self::isPasops($user),

            'letter_logs.view' => $regular,
            'letter_logs.create' => $regular,
            'letter_logs.update' => $regular,
            'letter_logs.delete' => $regular,
            'letter_logs.pdf' => $regular,

            'letters.view' => $regular,
            'letters.create' => $regular,

            'skhpp.view' => $regular,
            'skhpp.create' => $regular,
            'skhpp.approve' => $commanderApproval,
            'skhpp.delete' => $admin,
            'skhpp.pdf' => $regular,

            'signature.view' => $regular,
            'signature.approve' => $commanderApproval,

            'cash.view' => $cash,
            'cash.manage' => $cash,
            'cash.pdf' => $cash,

            'commander_account.view' => $commanderView,
            'commander_account.manage' => $commanderManage,
            'commander_account.pdf' => $commanderView,

            'ibu_beti.view' => $ibuBeti,
            'ibu_beti.manage' => $ibuBeti,
            'ibu_beti.pdf' => $ibuBeti,

            'mitra.view' => $mitra,
            'mitra.manage' => $mitra,

            'technical_cash.view' => $technicalCash,
            'technical_cash.manage' => $technicalCash,

            'violations.view' => $regular,
            'violations.manage' => $regular,

            'activities.view' => $regular,
            'activities.manage' => $regular,

            'categories.view' => $regular,

            'users.view' => $admin,
            'users.manage' => $admin,

            'audit_logs.view' => $admin,
            // Semua personel punya menu Backup; isi tiap penyimpanan dijaga App\Support\BackupAccess
            // (pemilik, admin, atau rekan yang permintaan aksesnya disetujui pemilik).
            'backup.view' => true,
        ];
    }

    public static function can(User $user, string $ability): bool
    {
        return (bool) (self::permissions($user)[$ability] ?? false);
    }

    /** @return list<string> */
    public static function grantedAbilities(User $user): array
    {
        return array_keys(array_filter(self::permissions($user)));
    }

    /**
     * Profil pengguna yang aman dikirim ke aplikasi (tanpa token/rahasia).
     */
    public static function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email ?? '',
            'username' => $user->username ?? $user->nrp ?? '',
            'nrp' => $user->nrp ?? '',
            'role' => $user->role ?? 'user',
            'pangkat' => $user->pangkat ?? '',
            'korps' => $user->korps ?? '',
            'jabatan' => $user->jabatan ?? '',
            'phone' => $user->phone ?? '',
            'avatar_url' => !empty($user->avatar) ? asset('storage/' . $user->avatar) : null,
            'is_active' => (bool) ($user->is_active ?? false),
        ];
    }

    /**
     * Profil + permission + flag kompatibilitas lama (can_access_*) yang dibaca
     * aplikasi Android v1.0.x dan Flutter.
     */
    public static function sessionPayload(User $user): array
    {
        $p = self::permissions($user);

        return array_merge(self::profile($user), [
            'permissions' => self::grantedAbilities($user),
            // Kompatibilitas mundur untuk klien lama:
            'can_access_agenda' => $p['letter_logs.view'],
            'can_access_letters' => $p['letters.view'],
            'can_access_skhpp' => $p['skhpp.view'],
            'can_access_categories' => $p['categories.view'],
            'can_access_signature' => $p['signature.view'],
            'can_access_cash' => $p['cash.view'],
            'can_access_commander' => $p['commander_account.view'],
            'can_access_mitra' => $p['mitra.view'],
            'can_access_technical_cash' => $p['technical_cash.view'],
            'can_access_ibu_beti' => $p['ibu_beti.view'],
            'can_access_violations' => $p['violations.view'],
            'can_access_activities' => $p['activities.view'],
            'can_access_users' => $p['users.view'],
            'can_access_settings' => self::isAdmin($user),
        ]);
    }
}
