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

    public function test_user_can_disable_with_password(): void
    {
        $u = $this->makeUser(['phone' => '081234567890', 'mfa_enabled' => true]);
        $this->actingAs($u)->withSession(['mfa_verified' => true])
            ->post('/profile/mfa', ['enabled' => false, 'password' => 'rahasia-123'])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $u->fresh()->mfa_enabled);
    }
}
