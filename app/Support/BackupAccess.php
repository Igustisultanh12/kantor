<?php

namespace App\Support;

use App\Models\Backup;
use App\Models\Pc;
use App\Models\PcAccessRequest;
use App\Models\User;

/**
 * Aturan akses penyimpanan backup (web & aplikasi Android memakai kelas yang sama).
 *
 *  - Pemilik penyimpanan: lihat + kelola (unggah, ubah nama, pindah, hapus, bagikan).
 *  - Admin: lihat + kelola semua penyimpanan (pengelola sistem).
 *  - Rekan yang permintaan aksesnya disetujui pemilik: hanya lihat & unduh.
 *  - Selain itu: ditolak, harus meminta akses ke pemilik.
 */
class BackupAccess
{
    public static function isAdmin(User $user): bool
    {
        return $user->role === 'admin' || in_array($user->name, MobileAccess::DEVELOPER_NAMES, true);
    }

    public static function isOwner(User $user, Pc $pc): bool
    {
        return (int) $pc->user_id === (int) $user->id;
    }

    public static function canManage(User $user, Pc $pc): bool
    {
        return self::isOwner($user, $pc) || self::isAdmin($user);
    }

    public static function hasGrant(User $user, Pc $pc): bool
    {
        PcAccessRequest::ensureSchema();

        return PcAccessRequest::where('pc_id', $pc->id)
            ->where('requester_id', $user->id)
            ->where('status', PcAccessRequest::STATUS_APPROVED)
            ->exists();
    }

    public static function canView(User $user, Pc $pc): bool
    {
        return self::canManage($user, $pc) || self::hasGrant($user, $pc);
    }

    public static function authorizeView(User $user, Pc $pc): void
    {
        if (!self::canView($user, $pc)) {
            abort(403, 'Anda belum punya akses ke penyimpanan ini. Minta akses ke pemiliknya terlebih dahulu.');
        }
    }

    public static function authorizeManage(User $user, Pc $pc): void
    {
        if (!self::canManage($user, $pc)) {
            abort(403, 'Hanya pemilik penyimpanan yang bisa mengubah isinya.');
        }
    }

    public static function pcOf(Backup $backup): Pc
    {
        return Pc::findOrFail($backup->pc_id);
    }

    /**
     * Status akses user terhadap tiap PC, untuk daftar penyimpanan rekan.
     *
     * @param  iterable<Pc>  $pcs
     * @return array<int, array{state: string, request_id: int|null}>
     *   state: owner | admin | granted | pending | verifying | rejected | none
     */
    public static function statesFor(User $user, iterable $pcs): array
    {
        PcAccessRequest::ensureSchema();

        $ids = [];
        foreach ($pcs as $pc) {
            $ids[] = $pc->id;
        }

        // Permintaan terbaru per PC (diurutkan terbaru dulu, ambil yang pertama).
        $latest = [];
        if ($ids) {
            $rows = PcAccessRequest::whereIn('pc_id', $ids)
                ->where('requester_id', $user->id)
                ->orderByDesc('id')
                ->get(['id', 'pc_id', 'status']);
            foreach ($rows as $row) {
                $latest[$row->pc_id] ??= $row;
            }
        }

        $isAdmin = self::isAdmin($user);
        $states = [];
        foreach ($pcs as $pc) {
            $req = $latest[$pc->id] ?? null;
            $state = match (true) {
                self::isOwner($user, $pc) => 'owner',
                $isAdmin => 'admin',
                $req === null => 'none',
                $req->status === PcAccessRequest::STATUS_APPROVED => 'granted',
                $req->status === PcAccessRequest::STATUS_PENDING => 'pending',
                $req->status === PcAccessRequest::STATUS_VERIFYING => 'verifying',
                $req->status === PcAccessRequest::STATUS_REJECTED => 'rejected',
                default => 'none',
            };
            $states[$pc->id] = ['state' => $state, 'request_id' => $req?->id];
        }

        return $states;
    }
}
