<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        // Tambahkan birth_date jika belum ada
        if (!Schema::hasColumn('users', 'birth_date')) {
            $table->date('birth_date')->nullable()->after('email');
        }
        
        // Tambahkan phone HANYA jika belum ada
        if (!Schema::hasColumn('users', 'phone')) {
            $table->string('phone')->nullable()->after('birth_date');
        }
    });
}
};