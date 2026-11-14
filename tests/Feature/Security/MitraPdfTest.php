<?php

namespace Tests\Feature\Security;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** PDF pembayaran mitra: format dinas & centang bulan lunas tercetak. */
class MitraPdfTest extends TestCase
{
    use SecuritySchema;

    public function test_pdf_marks_paid_months_and_requires_access(): void
    {
        Schema::create('mitras', function (Blueprint $t) {
            $t->id(); $t->string('nama'); $t->string('pt')->nullable(); $t->string('no_tlp')->nullable(); $t->text('keterangan')->nullable(); $t->integer('sort_order')->default(0); $t->timestamps();
        });
        Schema::create('mitra_payments', function (Blueprint $t) {
            $t->id(); $t->foreignId('mitra_id'); $t->integer('tahun'); $t->integer('bulan'); $t->boolean('is_paid')->default(false); $t->string('updated_by')->nullable(); $t->timestamps();
        });
        $id = DB::table('mitras')->insertGetId(['nama' => 'Pak Jerry', 'pt' => 'PT. LOMY', 'no_tlp' => '0811', 'sort_order' => 1]);
        DB::table('mitra_payments')->insert([
            ['mitra_id' => $id, 'tahun' => 2026, 'bulan' => 1, 'is_paid' => true],
            ['mitra_id' => $id, 'tahun' => 2026, 'bulan' => 2, 'is_paid' => true],
            ['mitra_id' => $id, 'tahun' => 2026, 'bulan' => 3, 'is_paid' => false],
        ]);

        $this->actingAs($this->makeUser())->get('/mitras/pdf?tahun=2026')->assertForbidden();

        $html = view('pdf.mitra_payment_report', [
            'mitras' => collect([(object) ['nama' => 'Pak Jerry', 'pt' => 'PT. LOMY', 'no_tlp' => '0811', 'payment_matrix' => [1 => true, 2 => true, 3 => false]]]),
            'tahun' => 2026, 'commander' => ['name' => 'Komandan', 'pangkat_nrp' => 'Kolonel NRP 1'],
            'creatorName' => 'Petugas', 'creatorPangkatNrp' => '', 'creatorJabatan' => 'Staf',
        ])->render();
        $this->assertSame(3, substr_count($html, '&#10003;'), '2 bulan lunas + 1 di keterangan');
        $this->assertStringContainsString('KOMANDO DAERAH TNI ANGKATAN LAUT V', $html);

        $res = $this->actingAs($this->makeUser(['can_access_mitra' => true]))->get('/mitras/pdf?tahun=2026');
        $res->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
        file_put_contents(storage_path('framework/testing/mitra-uji.pdf'), $res->getContent());
    }
}
