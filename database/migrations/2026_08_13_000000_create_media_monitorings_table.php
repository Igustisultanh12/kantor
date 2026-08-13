<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_monitorings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('source_name'); // e.g. Detik Jatim, Antara, X/Twitter, Radar Surabaya
            $table->enum('category', ['ideologi', 'politik', 'ekonomi', 'sosbud', 'hankam'])->default('hankam');
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->default('neutral');
            $table->text('summary');
            $table->string('url')->nullable();
            $table->string('location')->default('Surabaya');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_monitorings');
    }
};
