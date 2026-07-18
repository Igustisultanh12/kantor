<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('personnels', function (Blueprint $table) {
        $table->id();
        $table->string('name'); 
        $table->string('phone'); 
        $table->date('birth_date'); 
        $table->timestamps();
    });
}

    public function down(): void
    {
        Schema::table('letter_logs', function (Blueprint $table) {
            // Mengembalikan ke Integer jika diperlukan (rollback)
            $table->integer('sequence')->change();
        });
    }
};