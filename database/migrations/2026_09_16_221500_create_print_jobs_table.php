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
        if (!Schema::hasTable('print_jobs')) {
            Schema::create('print_jobs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('document_title');
                $table->string('original_filename');
                $table->string('file_type', 10); // pdf, docx, doc
                $table->string('original_file_path')->nullable();
                $table->string('preview_pdf_path')->nullable();
                $table->string('printable_pdf_path')->nullable();
                $table->integer('total_pages')->default(1);
                $table->integer('separator_pages')->default(1);
                $table->integer('total_sheets')->default(2);
                $table->integer('printed_sheets')->default(0);
                $table->integer('copies')->default(1);
                $table->string('color_mode', 20)->default('monochrome'); // monochrome, color
                $table->string('paper_size', 20)->default('A4'); // A4, F4, Letter, Legal
                $table->string('printer_brand', 30)->default('brother'); // brother, canon
                $table->string('print_quality', 30)->default('normal'); // normal, very_high, high, photo
                $table->string('print_density', 20)->default('normal'); // normal, light (terang), dark (pekat)
                $table->string('printer_ip')->nullable();
                $table->enum('status', ['draft', 'queued', 'printing', 'completed', 'failed', 'cancelled'])->default('draft');
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
