<?php

use App\Models\UserDevice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_devices')) {
            Schema::create('user_devices', fn ($t) => UserDevice::columns($t));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
