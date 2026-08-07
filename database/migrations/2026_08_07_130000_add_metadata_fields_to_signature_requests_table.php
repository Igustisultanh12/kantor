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
        Schema::table('signature_requests', function (Blueprint $table) {
            $table->string('document_title')->nullable()->after('subject');
            $table->string('person_name')->nullable()->after('document_title');
            $table->string('pangkat_nrp')->nullable()->after('person_name');
            $table->string('jabatan')->nullable()->after('pangkat_nrp');
            $table->text('peruntukan')->nullable()->after('jabatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signature_requests', function (Blueprint $table) {
            $table->dropColumn(['document_title', 'person_name', 'pangkat_nrp', 'jabatan', 'peruntukan']);
        });
    }
};
