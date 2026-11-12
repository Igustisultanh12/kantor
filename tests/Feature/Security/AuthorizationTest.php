<?php

namespace Tests\Feature\Security;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Audit T01, T02, T03, T04, T08, S02, S03, S04, S11: otorisasi modul. */
class AuthorizationTest extends TestCase
{
    use SecuritySchema;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('cashes', function (Blueprint $t) {
            $t->id(); $t->date('date'); $t->string('description'); $t->decimal('debit', 15, 2)->default(0);
            $t->decimal('credit', 15, 2)->default(0); $t->decimal('balance', 15, 2)->default(0); $t->text('receipt_path')->nullable(); $t->timestamps();
        });
        Schema::create('app_notifications', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('role')->nullable(); $t->string('title')->nullable();
            $t->text('message')->nullable(); $t->string('type')->nullable(); $t->string('link')->nullable(); $t->boolean('is_read')->default(false); $t->timestamps();
        });
    }

    /** Rute berdampak tinggi yang wajib menolak pengguna biasa. */
    public static function forbiddenForRegularUser(): array
    {
        return [
            'hapus kas' => ['delete', '/cash/1'],
            'ekspor kas' => ['get', '/cash/pdf'],
            'toggle mitra' => ['post', '/mitras/toggle-payment'],
            'hapus kas teknis' => ['delete', '/technical-cash/1'],
            'sahkan SP Jaga' => ['post', '/sp-jaga/1/approve-tte'],
            'unggah TTD SP Jaga' => ['post', '/sp-jaga/1/upload-manual-signed'],
            'hapus SP Jaga' => ['delete', '/sp-jaga/1'],
            'stempel komandan' => ['patch', '/otoritas-stempel/eksekusi'],
            'unggah stempel manual' => ['post', '/otoritas-stempel/upload-manual'],
            'admin PESS' => ['get', '/applicants/dashboard'],
            'tanda tangan komandan PESS' => ['post', '/submissions/sign-komandan/1'],
            'ubah HP via ulang tahun' => ['put', '/admin/user-birthday/1'],
            'kirim WA uji' => ['post', '/admin/birthday-radar/test'],
            'hapus pelanggaran' => ['delete', '/soldier-violations/1'],
            'logout WA dinas' => ['post', '/whatsapp-web/sessions/dinas/logout'],
            'hapus sesi WA' => ['delete', '/whatsapp-web/sessions/dinas'],
            'QR sesi WA' => ['get', '/whatsapp-web/sessions/dinas/qr'],
            'buat sesi WA' => ['post', '/whatsapp-web/sessions'],
            'hapus thread chat' => ['delete', '/live-chat/00000000-0000-0000-0000-000000000000'],
            'cari pengguna chat' => ['get', '/live-chat/search-users?q=ab'],
        ];
    }

    /** @dataProvider forbiddenForRegularUser */
    public function test_regular_user_is_forbidden(string $method, string $url): void
    {
        $this->actingAs($this->makeUser(['role' => 'staf']))->json($method, $url)->assertForbidden();
    }

    public function test_cash_flag_holder_can_delete_cash(): void
    {
        $id = DB::table('cashes')->insertGetId(['date' => '2026-11-01', 'description' => 'x', 'debit' => 1, 'credit' => 0, 'balance' => 1]);
        $this->actingAs($this->makeUser(['can_access_cash' => true]))->delete("/cash/{$id}")->assertRedirect();
        $this->assertDatabaseMissing('cashes', ['id' => $id]);
    }

    public function test_notifications_are_scoped_to_owner(): void
    {
        $me = $this->makeUser();
        $other = $this->makeUser();
        DB::table('app_notifications')->insert([
            ['user_id' => $other->id, 'role' => null, 'title' => 'Pinjaman orang lain', 'is_read' => false],
            ['user_id' => $me->id, 'role' => null, 'title' => 'Milik saya', 'is_read' => false],
            ['user_id' => null, 'role' => null, 'title' => 'Siaran umum', 'is_read' => false],
        ]);
        $titles = collect($this->actingAs($me)->getJson('/api/notifications')->json('notifications'))->pluck('title');
        $this->assertTrue($titles->contains('Milik saya'));
        $this->assertTrue($titles->contains('Siaran umum'));
        $this->assertFalse($titles->contains('Pinjaman orang lain'));

        $otherId = DB::table('app_notifications')->where('user_id', $other->id)->value('id');
        $this->actingAs($me)->delete("/notifications/{$otherId}");
        $this->assertDatabaseHas('app_notifications', ['id' => $otherId]);
    }

    public function test_sintel_notification_only_to_registered_personnel(): void
    {
        $this->actingAs($this->makeUser())->post('/sc-submissions/notify-sintel', ['phone' => '089999999999', 'nama' => 'Siapa saja', 'catatan_tambahan' => 'teks bebas'])
            ->assertSessionHas('error');
        Http::assertNothingSent();
    }
}
