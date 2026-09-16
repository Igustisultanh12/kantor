<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'mfa_enabled')) {
            // Set default mfa_enabled = true untuk role pimpinan yang sebelumnya menggunakan 2FA
            DB::table('users')
                ->whereIn('role', ['komandan', 'pasops'])
                ->update(['mfa_enabled' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
