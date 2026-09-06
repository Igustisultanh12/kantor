<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ibu_beti_accounts', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('keterangan');
            $table->enum('jenis', ['MASUK', 'KELUAR']);
            $table->decimal('jumlah', 15, 2);
            $table->string('bukti')->nullable(); // Foto / dokumen bukti transaksi
            $table->string('petugas_input')->nullable(); // Pencatat transaksi
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ibu_beti_accounts');
    }
};
