<?php

namespace Tests\Feature;

use App\Models\Cash;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Unggah struk transaksi kas dari aplikasi (sama dengan fitur unggah struk di web). */
class CashReceiptsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\LogVisitor::class);
        Storage::fake('public');

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('nrp')->nullable();
            $t->string('pangkat')->nullable();
            $t->string('jabatan')->nullable();
            $t->string('phone')->nullable();
            $t->string('role')->default('personel');
            $t->string('password');
            $t->boolean('is_active')->default(true);
            $t->boolean('can_access_mitra')->default(false);
            $t->boolean('can_access_technical_cash')->default(false);
            $t->boolean('can_manage_koperasi')->default(false);
            $t->boolean('can_access_ibu_beti')->default(false);
            $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('cashes', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->string('description');
            $t->decimal('debit', 15, 2)->default(0);
            $t->decimal('credit', 15, 2)->default(0);
            $t->decimal('balance', 15, 2)->default(0);
            $t->text('receipt_path')->nullable();
            $t->timestamps();
        });
    }

    private function headers(string $role): array
    {
        $user = User::create([
            'name' => 'User ' . $role, 'email' => $role . '@test.local', 'role' => $role,
            'password' => Hash::make('rahasia123'), 'is_active' => true,
        ]);
        return ['Authorization' => 'Bearer ' . $user->createToken('t')->plainTextToken, 'Accept' => 'application/json'];
    }

    public function test_upload_keep_and_remove_receipts(): void
    {
        $h = $this->headers('admin');
        $id = $this->postJson('/api/mobile/cash', ['date' => '2026-11-09', 'description' => 'Beli ATK', 'type' => 'keluar', 'amount' => 50000], $h)
            ->assertOk()->json('data.id');

        // Unggah 2 struk (foto + PDF).
        $res = $this->post("/api/mobile/cash/{$id}/receipts", [
            'receipt_files' => [
                UploadedFile::fake()->image('struk.jpg', 600, 800),
                UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
            ],
        ], $h)->assertOk();
        $receipts = $res->json('receipts');
        $this->assertCount(2, $receipts);
        $this->assertTrue($receipts[1]['is_pdf']);
        Storage::disk('public')->assertExists($receipts[0]['path']);

        // Daftar kas menampilkan struk.
        $this->getJson('/api/mobile/cash', $h)->assertOk()
            ->assertJsonCount(2, 'data.0.receipts')
            ->assertJsonCount(2, 'data.0.receipt_urls');

        // Pertahankan PDF saja + tambah 1 foto baru; foto lama terhapus dari disk.
        $res2 = $this->post("/api/mobile/cash/{$id}/receipts", [
            'keep' => [$receipts[1]['path']],
            'receipt_files' => [UploadedFile::fake()->image('struk2.png')],
        ], $h)->assertOk();
        $this->assertCount(2, $res2->json('receipts'));
        $this->assertSame($receipts[1]['path'], $res2->json('receipts.0.path'));
        Storage::disk('public')->assertMissing($receipts[0]['path']);

        // Path milik transaksi lain tidak bisa "dipertahankan" (tidak bisa mengambil berkas orang lain).
        $this->post("/api/mobile/cash/{$id}/receipts", ['keep' => ['receipts/milik-lain.jpg']], $h)
            ->assertOk()->assertJsonCount(0, 'receipts');

        // Jenis berkas selain JPG/PNG/PDF ditolak.
        $this->post("/api/mobile/cash/{$id}/receipts", ['receipt_files' => [UploadedFile::fake()->create('virus.exe', 10)]], $h)
            ->assertStatus(422);

        // Hapus transaksi -> berkas struk ikut terhapus.
        $last = $this->post("/api/mobile/cash/{$id}/receipts", ['receipt_files' => [UploadedFile::fake()->image('a.jpg')]], $h)->json('receipts.0.path');
        $this->deleteJson("/api/mobile/cash/{$id}", [], $h)->assertOk();
        Storage::disk('public')->assertMissing($last);
        $this->assertNull(Cash::find($id));
    }

    public function test_receipts_require_manage_permission(): void
    {
        $cash = Cash::create(['date' => '2026-11-09', 'description' => 'X', 'debit' => 1, 'credit' => 0, 'balance' => 1]);
        $this->post("/api/mobile/cash/{$cash->id}/receipts", ['receipt_files' => [UploadedFile::fake()->image('s.jpg')]], $this->headers('personel'))
            ->assertStatus(403);
    }
}
