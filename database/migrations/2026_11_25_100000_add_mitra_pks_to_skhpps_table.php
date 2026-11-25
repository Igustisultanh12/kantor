<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SKHPP-P untuk Mitra / PKS: tiap pengikut punya jenis usaha (mis. Bengkel, Laundry) dan lokasi usahanya,
     * yang tampil sebagai kolom USAHA dan LOKASI USAHA di tabel lampiran.
     */
    public function up(): void
    {
        Schema::table('skhpps', function (Blueprint $table) {
            $table->boolean('is_mitra_pks')->default(false)->after('is_pernikahan');
        });
        Schema::table('skhpp_members', function (Blueprint $table) {
            $table->string('usaha', 150)->nullable()->after('jabatan');
            $table->string('lokasi_usaha')->nullable()->after('usaha');
        });
    }

    public function down(): void
    {
        Schema::table('skhpp_members', function (Blueprint $table) {
            $table->dropColumn(['usaha', 'lokasi_usaha']);
        });
        Schema::table('skhpps', function (Blueprint $table) {
            $table->dropColumn('is_mitra_pks');
        });
    }
};
