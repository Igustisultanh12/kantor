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
        Schema::table('backups', function (Blueprint $table) {
    $table->foreignId('parent_id')->nullable()->constrained('backups')->onDelete('cascade');
    $table->boolean('is_folder')->default(false);
    $table->string('file_type')->nullable()->change(); // Sesuaikan agar bisa null untuk folder
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
