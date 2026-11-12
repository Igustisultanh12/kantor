<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Audit K04, R01, R02, S08: autentikasi & MFA. */
class AuthHardeningTest extends TestCase
{
    use SecuritySchema;

    private function lastOtp(): string
    {
        $otp = null;
        foreach (Http::recorded() as [$req]) {
            if (str_contains($req->url(), '/send') && preg_match('/\*(\d{6})\*/', urldecode($req->url()), $m)) {
                $otp = $m[1];
            }
        }
        $this->assertNotNull($otp, 'OTP terkirim lewat gateway WA');
        return $otp;
    }

    public function test_json_login_no_longer_issues_token_or_session(): void
    {
        $u = $this->makeUser(['mfa_enabled' => true, 'phone' => '081234567890']);
        $this->postJson('/login', ['email' => $u->email, 'password' => 'rahasia-123'])->assertStatus(410)->assertJsonMissingPath('token');
        $this->assertGuest();
    }

    public function test_web_login_with_mfa_requires_otp(): void
    {
        $u = $this->makeUser(['mfa_enabled' => true, 'phone' => '081234567890']);
        $this->post('/login', ['email' => $u->email, 'password' => 'rahasia-123'])->assertRedirect(route('mfa.verify'));
        $this->assertGuest();
    }

    public function test_mfa_without_phone_is_rejected_not_skipped(): void
    {
        $u = $this->makeUser(['mfa_enabled' => true, 'phone' => null]);
        $this->post('/login', ['email' => $u->email, 'password' => 'rahasia-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_session_of_mfa_user_without_verification_is_logged_out(): void
    {
        $u = $this->makeUser(['mfa_enabled' => true, 'phone' => '0812']);
        $this->actingAs($u)->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_mobile_login_with_mfa_requires_valid_otp(): void
    {
        $u = $this->makeUser(['mfa_enabled' => true, 'phone' => '081234567890']);

        $this->postJson('/api/mobile/login', ['username' => $u->email, 'password' => 'rahasia-123'])
            ->assertStatus(202)->assertJsonPath('code', 'mfa_required')->assertJsonMissingPath('token');
        $otp = $this->lastOtp();

        $this->postJson('/api/mobile/login', ['username' => $u->email, 'password' => 'rahasia-123', 'otp' => '000000' === $otp ? '111111' : '000000'])
            ->assertStatus(422)->assertJsonPath('code', 'mfa_invalid');

        $res = $this->postJson('/api/mobile/login', ['username' => $u->email, 'password' => 'rahasia-123', 'otp' => $otp])->assertOk();
        $this->assertNotEmpty($res->json('token'));

        // OTP sekali pakai
        $this->postJson('/api/mobile/login', ['username' => $u->email, 'password' => 'rahasia-123', 'otp' => $otp])
            ->assertStatus(422)->assertJsonPath('code', 'mfa_expired');
    }

    public function test_retired_flutter_client_is_rejected(): void
    {
        $u = $this->makeUser();
        $this->withHeader('User-Agent', 'SINDEN-App/1.0 (Kedinasan REST API; SINDEN Client)')
            ->postJson('/api/mobile/login', ['username' => $u->email, 'password' => 'rahasia-123'])
            ->assertStatus(426)->assertJsonPath('code', 'client_retired');
    }

    public function test_mobile_login_without_mfa_still_works(): void
    {
        $u = $this->makeUser();
        $this->assertNotEmpty($this->postJson('/api/mobile/login', ['username' => $u->email, 'password' => 'rahasia-123'])->assertOk()->json('token'));
    }

    public function test_spoofed_forwarded_for_from_untrusted_client_is_ignored(): void
    {
        $u = $this->makeUser();
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8']);
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', ['email' => $u->email, 'password' => 'salah'], ['X-Forwarded-For' => "10.0.0.{$i}"]);
        }
        $this->post('/login', ['email' => $u->email, 'password' => 'rahasia-123'], ['X-Forwarded-For' => '10.9.9.9'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_activation_endpoint_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/aktivasi', ['nrp' => 'X', 'token' => "T{$i}", 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh']);
        }
        $this->post('/aktivasi', ['nrp' => 'X', 'token' => 'T9', 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'])->assertStatus(429);
    }

    public function test_safe_redirect_rejects_external_targets(): void
    {
        $req = Request::create('https://sisinden.my.id/login');
        foreach (['https://evil.example/x', '//evil.example', '/\\evil.example', "/\tx", 'javascript:alert(1)'] as $bad) {
            $this->assertFalse(GoogleAuthController::isSafeRedirect($bad, $req), $bad);
        }
        foreach (['/dashboard', '/shared-folder/abc', 'https://sisinden.my.id/x'] as $ok) {
            $this->assertTrue(GoogleAuthController::isSafeRedirect($ok, $req), $ok);
        }
    }
}
