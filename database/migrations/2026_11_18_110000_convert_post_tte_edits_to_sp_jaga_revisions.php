<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sebelum fitur revisi, SP Jaga yang sudah ber-TTE masih bisa diedit langsung. Edit selalu menghapus
     * dan membuat ulang daftar perwira/anggota, jadi SP yang daftar jaganya dibuat SETELAH waktu TTE
     * berarti pernah diedit setelah disahkan. Isi hasil edit itu dijadikan "Revisi ke-1" (terbit,
     * TTE = waktu edit terakhir) di bawah SP aslinya. Isi asli sebelum edit tidak tersimpan di mana pun,
     * jadi SP asli tetap memuat isi hasil edit; QR-nya kini menunjukkan "sudah direvisi".
     * Aman dijalankan ulang: SP yang sudah punya revisi dilewati.
     */
    public function up(): void
    {
        foreach (['sp_jagas', 'sp_jaga_perwiras', 'sp_jaga_anggotas'] as $t) {
            if (!Schema::hasTable($t)) {
                return;
            }
        }
        if (!Schema::hasColumn('sp_jagas', 'revisi_dari_id')) {
            return;
        }

        $kandidat = DB::table('sp_jagas')
            ->whereNull('revisi_dari_id')
            ->where('status', 'published')
            ->where('ttd_type', 'tte')
            ->whereNotNull('approved_at')
            ->get();

        foreach ($kandidat as $sp) {
            if (DB::table('sp_jagas')->where('revisi_dari_id', $sp->id)->exists()) {
                continue;
            }

            $editTerakhir = collect([
                DB::table('sp_jaga_perwiras')->where('sp_jaga_id', $sp->id)->max('created_at'),
                DB::table('sp_jaga_anggotas')->where('sp_jaga_id', $sp->id)->max('created_at'),
            ])->filter()->max();

            if (!$editTerakhir || strtotime($editTerakhir) <= strtotime($sp->approved_at)) {
                continue; // tidak diedit setelah TTE
            }

            DB::transaction(function () use ($sp, $editTerakhir) {
                $baris = (array) $sp;
                unset($baris['id']);
                $revisiId = DB::table('sp_jagas')->insertGetId(array_merge($baris, [
                    'revisi_dari_id' => $sp->id,
                    'revisi_ke' => 1,
                    'revisi_alasan' => 'Perubahan setelah TTE sebelum fitur revisi tersedia (dipindahkan otomatis).',
                    'tanggal_surat' => substr($editTerakhir, 0, 10),
                    'approved_at' => $editTerakhir,
                    'verification_code' => 'TTE-SPJAGA-' . $sp->tahun . str_pad((string) $sp->bulan, 2, '0', STR_PAD_LEFT)
                        . '-R1-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 6)),
                    'created_at' => $editTerakhir,
                    'updated_at' => now(),
                ]));

                foreach (['sp_jaga_perwiras', 'sp_jaga_anggotas'] as $tabel) {
                    foreach (DB::table($tabel)->where('sp_jaga_id', $sp->id)->get() as $anak) {
                        $data = (array) $anak;
                        unset($data['id']);
                        DB::table($tabel)->insert(array_merge($data, ['sp_jaga_id' => $revisiId]));
                    }
                }

                Log::info("[SP_JAGA] {$sp->nomor_sprin} (id {$sp->id}) diedit setelah TTE; dijadikan revisi ke-1 (id {$revisiId}).");
            });
        }
    }

    public function down(): void
    {
        DB::table('sp_jagas')->where('revisi_alasan', 'like', 'Perubahan setelah TTE sebelum fitur revisi%')->get()
            ->each(function ($r) {
                DB::table('sp_jaga_perwiras')->where('sp_jaga_id', $r->id)->delete();
                DB::table('sp_jaga_anggotas')->where('sp_jaga_id', $r->id)->delete();
                DB::table('sp_jagas')->where('id', $r->id)->delete();
            });
    }
};
