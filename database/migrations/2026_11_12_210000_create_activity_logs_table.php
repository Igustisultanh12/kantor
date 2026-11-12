<?php

use App\Models\ActivityLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', fn ($t) => ActivityLog::columns($t));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
