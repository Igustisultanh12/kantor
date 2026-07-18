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
        $table->float('stamp_x')->nullable();
        $table->float('stamp_y')->nullable();
        $table->integer('stamp_page')->default(1);
        $table->boolean('is_stamped')->default(false);
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
