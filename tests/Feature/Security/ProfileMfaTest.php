<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/** Profil: pengguna mengaktifkan / menonaktifkan OTP WhatsApp miliknya sendiri. */
class ProfileMfaTest extends TestCase
{
    use SecuritySchema;

    public function test_enable_requires_correct_password_and_phone(): void
    {
        $u = $this->makeUser(['phone' => null]);
        $this->actingAs($u)->post('/profile/mfa', ['enabled' => true, 'password' => 'salah'])->assertSessionHasErrors('password');
        $this->actingAs($u)->post('/profile/mfa', ['enabled' => true, 'password' => 'rahasia-123'])->assertSessionHasErrors('mfa');
        $this->assertFalse((bool) $u->fresh()->mfa_enabled);
    }

    public function test_user_can_enable_and_stays_logged_in(): void
    {
        $u = $this->makeUser(['phone' => '081234567890']);
        $this->actingAs($u)->post('/profile/mfa', ['enabled' => true, 'password' => 'rahasia-123'])->assertSessionHasNoErrors();
        $this->assertTrue((bool) $u->fresh()->mfa_enabled);
        // Sesi saat ini tidak dikeluarkan CheckMFA.
        $this->get('/profile')->assertOk();
        $this->assertAuthenticated();
    }

    public function test_user_can_disable_with_password_and_gets_whatsapp_notice(): void
    {
        $u = $this->makeUser(['phone' => '081234567890', 'mfa_enabled' => true]);
        $this->actingAs($u)->withSession(['mfa_verified' => true])
            ->post('/profile/mfa', ['enabled' => false, 'password' => 'rahasia-123'])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $u->fresh()->mfa_enabled);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains($r->url(), '/send')
            && str_contains(urldecode($r->url()), 'DINONAKTIFKAN') && str_contains($r->url(), '6281234567890'));
    }

    public function test_admin_toggle_also_notifies_personnel(): void
    {
        $target = $this->makeUser(['phone' => '081200000001']);
        $this->actingAs($this->makeUser(['role' => 'admin']))->post("/users/{$target->id}/toggle-mfa")->assertRedirect();
        $this->assertTrue((bool) $target->fresh()->mfa_enabled);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'DIAKTIFKAN') && str_contains($r->url(), '6281200000001'));
    }

    public function test_mobile_login_rate_limit_is_per_account(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/mobile/login', ['username' => $a->email, 'password' => 'salah']);
        }
        $this->postJson('/api/mobile/login', ['username' => $a->email, 'password' => 'salah'])->assertStatus(429)->assertJsonPath('code', 'rate_limited');
        // Akun lain dari IP yang sama (mis. Wi-Fi kantor) tetap bisa masuk.
        $this->postJson('/api/mobile/login', ['username' => $b->email, 'password' => 'rahasia-123'])->assertOk();
    }
}
