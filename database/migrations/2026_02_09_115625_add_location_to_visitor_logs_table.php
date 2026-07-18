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
        Schema::table('visitor_logs', function (Blueprint $table) {
            // Menambahkan kolom location setelah kolom ip_address
            $table->string('location')->nullable()->after('ip_address');
            
            // Menambahkan kolom device untuk menyimpan model HP/Perangkat
            $table->string('device')->nullable()->after('user_agent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visitor_logs', function (Blueprint $table) {
            $table->dropColumn(['location', 'device']);
        });
    }
};