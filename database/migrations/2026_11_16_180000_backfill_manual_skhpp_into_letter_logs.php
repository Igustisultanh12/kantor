<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SKHPP yang sudah diajukan TTD manual sebelum sinkronisasi Agenda Surat ada:
     * masukkan nomornya ke Agenda Surat (letter_logs) dengan status belum ada PDF.
     * Aman dijalankan ulang — nomor yang sudah tercatat dilewati.
     */
    public function up(): void
    {
        if (!Schema::hasTable('skhpps') || !Schema::hasTable('letter_logs') || !Schema::hasColumn('skhpps', 'ttd_mode')) {
            return;
        }

        $rows = DB::table('skhpps')->where('ttd_mode', 'manual')->where('status', '!=', 'approved')->whereNotNull('nomor_skhpp')->get();

        foreach ($rows as $s) {
            if (DB::table('letter_logs')->where('full_number', $s->nomor_skhpp)->exists()) {
                continue;
            }

            $perusahaan = $s->kategori_personel === 'perusahaan';
            $code = $perusahaan ? 'SKHPP-P' : 'SKHPP-D';
            $categoryId = DB::table('categories')->where('code', $code)->value('id')
                ?? DB::table('categories')->insertGetId([
                    'code' => $code,
                    'name' => $perusahaan ? 'SKHPP Mitra Kerja / Perusahaan' : 'SKHPP Dinas Militer & PNS',
                    'start_number' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

            $subId = ($s->letter_sub_category_id ?? null)
                ? DB::table('sub_categories')->where('id', $s->letter_sub_category_id)->where('category_id', $categoryId)->value('id')
                : null;
            $berhuruf = ($s->nomor_manual ?? null) && (int) $s->nomor_manual === (int) $s->nomor_urut;

            DB::table('letter_logs')->insert([
                'full_number' => $s->nomor_skhpp,
                'sequence' => $berhuruf ? $s->nomor_manual : (string) $s->nomor_urut,
                'priority' => trim(explode('/', $s->nomor_skhpp)[0]) ?: 'R',
                'category_id' => $categoryId,
                'sub_category_id' => $subId,
                'subject' => "SKHPP - {$s->nama} (" . strtoupper($s->kategori_personel) . ") - " . $s->peruntukan,
                'recipient' => 'Yth. Asintel Dankodaeral V',
                'date' => now()->toDateString(),
                'is_archived' => false,
                'user_id' => $s->submitted_by,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data agenda tidak dihapus saat rollback.
    }
};
