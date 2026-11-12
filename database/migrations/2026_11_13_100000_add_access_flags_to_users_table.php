<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit keamanan K01/T09: hak akses tidak boleh lagi ditentukan dari NAMA pengguna
 * (nama bisa diganti sendiri di profil). Pengecualian berbasis nama dipindahkan
 * ke flag database yang hanya bisa diubah Admin.
 *
 * Migrasi data memberikan flag kepada pemegang nama yang selama ini memakai
 * pengecualian tersebut, sehingga akses yang sah tidak hilang.
 */
return new class extends Migration
{
    private const FLAGS = ['is_developer', 'can_access_cash', 'can_access_commander', 'can_manage_sp_jaga', 'can_sign_sp_jaga'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (self::FLAGS as $flag) {
                if (! Schema::hasColumn('users', $flag)) {
                    $table->boolean($flag)->default(false);
                }
            }
        });

        $dev = 'I Gusti Sultan H.A, A.Md.Kom';
        DB::table('users')->where('name', $dev)->update(['is_developer' => true]);
        DB::table('users')->whereIn('name', ['Suma Nurhasanah', 'HARI BAGIO WIJAYANTO, M.TR.OPSLA'])->update(['can_access_cash' => true]);
        DB::table('users')->where('name', 'Suma Nurhasanah')->update(['can_access_commander' => true, 'can_manage_sp_jaga' => true]);
        DB::table('users')->whereRaw('LOWER(name) LIKE ?', ['%roni sumantri%'])->update(['can_sign_sp_jaga' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (self::FLAGS as $flag) {
                if (Schema::hasColumn('users', $flag)) {
                    $table->dropColumn($flag);
                }
            }
        });
    }
};
