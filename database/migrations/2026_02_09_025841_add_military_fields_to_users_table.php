<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        // Cek dulu, kalau belum ada baru ditambah
        if (!Schema::hasColumn('users', 'nrp')) {
            $table->string('nrp')->unique()->after('email');
        }
        if (!Schema::hasColumn('users', 'pangkat')) {
            $table->string('pangkat')->after('nrp');
        }
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
