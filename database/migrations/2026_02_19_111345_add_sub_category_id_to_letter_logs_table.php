<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_logs', function (Blueprint $table) {
            // Kita buat kolomnya dulu tanpa constraint foreign key yang ketat agar tidak gagal
            $table->unsignedBigInteger('sub_category_id')->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('letter_logs', function (Blueprint $table) {
            $table->dropColumn('sub_category_id');
        });
    }
};