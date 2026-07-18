<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('signature_requests', function (Blueprint $table) {
        // Menambahkan kolom koordinat setelah file_path
        $table->double('x')->nullable()->after('file_path');
        $table->double('y')->nullable()->after('x');
        $table->double('width')->nullable()->after('y');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signature_requests', function (Blueprint $table) {
            //
        });
    }
};
