<?php

namespace App\Console\Commands;

use App\Models\SpJaga;
use App\Models\SpJagaPerwira;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pulihkan daftar perwira (lampiran 1) SP Jaga ASLI yang tertimpa edit langsung sebelum fitur revisi ada,
 * sesuai dokumen yang ditandatangani. Default hanya pratinjau; perubahan baru disimpan dengan --simpan.
 *
 *   php artisan sp-jaga:pulihkan-perwira database/data/sp_jaga/sprin-55-xi-2026-asli.json
 *   php artisan sp-jaga:pulihkan-perwira database/data/sp_jaga/sprin-55-xi-2026-asli.json --simpan --hapus-revisi-otomatis
 */
class PulihkanPerwiraSpJaga extends Command
{
    protected $signature = 'sp-jaga:pulihkan-perwira
        {berkas : Berkas JSON berisi nomor_sprin dan daftar perwira sesuai dokumen asli}
        {--simpan : Simpan perubahan (tanpa ini hanya pratinjau)}
        {--hapus-revisi-otomatis : Hapus "Revisi ke-N" yang dibuat otomatis dari edit lama (isinya ikut keliru)}
        {--paksa : Lanjutkan walau isi sekarang tidak sesuai data_sekarang_harus_berisi}';

    protected $description = 'Pulihkan daftar perwira SP Jaga asli yang tertimpa edit langsung (sebelum fitur revisi).';

    private const ALASAN_OTOMATIS = 'Perubahan setelah TTE sebelum fitur revisi%';

    public function handle(): int
    {
        $path = $this->argument('berkas');
        $path = is_file($path) ? $path : base_path($path);
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!is_array($data) || empty($data['nomor_sprin']) || empty($data['perwiras'])) {
            $this->error("Berkas tidak ditemukan atau tidak valid: {$this->argument('berkas')}");
            return self::FAILURE;
        }

        $kandidat = SpJaga::with('perwiras')->whereNull('revisi_dari_id')->where('nomor_sprin', $data['nomor_sprin'])->get();
        if ($kandidat->count() !== 1) {
            $this->error("Ditemukan {$kandidat->count()} SP asli dengan nomor {$data['nomor_sprin']}; harus tepat 1.");
            return self::FAILURE;
        }
        $sp = $kandidat->first();
        $this->info("SP asli: id {$sp->id}, {$sp->nomor_sprin}, status {$sp->status}");

        $this->line("\nDaftar perwira SEKARANG:");
        $this->tabel($sp->perwiras->map(fn ($p) => [$p->nama, $p->nrp, collect(range(1, 7))->map(fn ($k) => $p->{"tgl_{$k}"} ?: '-')->implode(' ')])->all());
        $this->line("\nDaftar perwira DIPULIHKAN (dokumen asli):");
        $this->tabel(collect($data['perwiras'])->map(fn ($p) => [$p['nama'], $p['nrp'] ?? '', implode(' ', $p['tgl'])])->all());

        $harus = collect($data['data_sekarang_harus_berisi'] ?? []);
        if ($harus->isNotEmpty()) {
            $norm = fn ($s) => mb_strtolower(trim((string) $s));
            $sekarang = $sp->perwiras->pluck('nama')->map($norm)->sort()->values()->all();
            if ($sekarang !== $harus->map($norm)->sort()->values()->all() && !$this->option('paksa')) {
                $this->error("\nIsi sekarang tidak sesuai 'data_sekarang_harus_berisi'. Mungkin sudah dipulihkan atau berbeda dari dugaan; tidak ada yang diubah. Gunakan --paksa bila yakin.");
                return self::FAILURE;
            }
        }

        $revOtomatis = SpJaga::where('revisi_dari_id', $sp->id)->where('revisi_alasan', 'like', self::ALASAN_OTOMATIS)->get();
        $revLain = SpJaga::where('revisi_dari_id', $sp->id)->where(fn ($q) => $q->whereNull('revisi_alasan')->orWhere('revisi_alasan', 'not like', self::ALASAN_OTOMATIS))->count();
        if ($revOtomatis->isNotEmpty()) {
            $this->warn("\nAda {$revOtomatis->count()} revisi otomatis dari edit lama (isinya sama dengan data keliru): id " . $revOtomatis->pluck('id')->implode(', ')
                . ($this->option('hapus-revisi-otomatis') ? ' — akan dihapus.' : ' — tambahkan --hapus-revisi-otomatis untuk menghapusnya.'));
        }
        if ($revLain) {
            $this->warn("Ada {$revLain} revisi lain (dibuat lewat fitur revisi) — tidak disentuh.");
        }

        if (!$this->option('simpan')) {
            $this->comment("\nPratinjau saja. Jalankan ulang dengan --simpan untuk menyimpan.");
            return self::SUCCESS;
        }

        DB::transaction(function () use ($sp, $data, $revOtomatis) {
            $lama = $sp->perwiras;
            // Cap waktu sebelum TTE, agar baris yang dipulihkan tidak terbaca sebagai "diedit setelah TTE".
            $waktu = $sp->approved_at ? $sp->approved_at->copy()->subSecond() : ($sp->created_at ?? now());
            SpJagaPerwira::where('sp_jaga_id', $sp->id)->delete();

            foreach (array_values($data['perwiras']) as $i => $p) {
                $nrp = (string) ($p['nrp'] ?? '');
                $userId = $lama->first(fn ($x) => $nrp !== '' && $x->nrp === $nrp)?->user_id
                    ?? $lama->first(fn ($x) => mb_strtolower($x->nama) === mb_strtolower($p['nama']))?->user_id
                    ?? ($nrp !== '' ? User::where('nrp', $nrp)->value('id') : null);

                $baris = new SpJagaPerwira([
                    'sp_jaga_id' => $sp->id,
                    'user_id' => $userId,
                    'no_urut' => $i + 1,
                    'nama' => $p['nama'],
                    'pangkat_korps' => $p['pangkat_korps'] ?? '',
                    'nrp' => $nrp,
                    'tgl_list' => [],
                ]);
                foreach (range(1, 7) as $k) {
                    $baris->{"tgl_{$k}"} = $p['tgl'][$k - 1] ?? '-';
                }
                $baris->created_at = $waktu;
                $baris->updated_at = $waktu;
                $baris->timestamps = false;
                $baris->save();
            }

            // Jumlah personel menyesuaikan perwira yang dipulihkan.
            $selisih = count($data['perwiras']) - $lama->count();
            if ($selisih !== 0 && $sp->total_personel_count) {
                $total = (int) $sp->total_personel_count + $selisih;
                SpJaga::whereKey($sp->id)->update(['total_personel_count' => $total, 'total_personel_terbilang' => $this->terbilang($total)]);
            }

            if ($this->option('hapus-revisi-otomatis')) {
                foreach ($revOtomatis as $r) {
                    DB::table('sp_jaga_perwiras')->where('sp_jaga_id', $r->id)->delete();
                    DB::table('sp_jaga_anggotas')->where('sp_jaga_id', $r->id)->delete();
                    $r->delete();
                }
            }
        });

        $this->info("\nDaftar perwira SP asli {$sp->nomor_sprin} dipulihkan sesuai dokumen yang ditandatangani.");
        return self::SUCCESS;
    }

    private function tabel(array $rows): void
    {
        $this->table(['Nama', 'NRP', 'Tanggal (kolom 1-7)'], $rows);
    }

    private function terbilang(int $n): string
    {
        $b = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
        if ($n < 12) return $b[$n];
        if ($n < 20) return $this->terbilang($n - 10) . ' Belas';
        if ($n < 100) return trim($this->terbilang(intdiv($n, 10)) . ' Puluh ' . ($n % 10 ? $b[$n % 10] : ''));
        return (string) $n;
    }
}
