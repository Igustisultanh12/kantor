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
        Schema::table('cashes', function (Blueprint $table) {
            // TAMBAHAN: Menyuntikkan kolom receipt_path setelah kolom balance secara aman
            $table->string('receipt_path')->nullable()->after('balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashes', function (Blueprint $table) {
            // Drop kolom jika dilakukan rollback pangkalan data
            $table->dropColumn('receipt_path');
        });
    }
};