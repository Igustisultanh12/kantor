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
        // Default 1 karena minimal dokumen punya 1 halaman
        $table->integer('target_page')->default(1)->after('width');
    });
}

public function down()
{
    Schema::table('signature_requests', function (Blueprint $table) {
        $table->dropColumn('target_page');
    });
}
};
