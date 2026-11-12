<?php

namespace Tests\Feature\Security;

use App\Support\BackupAccess;
use App\Support\MobileAccess;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Audit K01 / T09: hak akses tidak lagi ditentukan dari nama pengguna. */
class AccessFlagsTest extends TestCase
{
    use SecuritySchema; // setUpSecuritySchema() dipanggil otomatis oleh Laravel (setUpTraits)

    public function test_renaming_self_to_privileged_name_grants_nothing(): void
    {
        $staf = $this->makeUser();

        $this->actingAs($staf)->patch('/profile', ['name' => 'I Gusti Sultan H.A, A.Md.Kom', 'email' => $staf->email])
            ->assertSessionHasNoErrors();

        $fresh = $staf->fresh();
        $this->assertNotSame('I Gusti Sultan H.A, A.Md.Kom', $fresh->name, 'nama tidak boleh diubah sendiri');

        // Walau nama sama persis (mis. diubah admin), hak tetap dari flag.
        $fresh->forceFill(['name' => 'I Gusti Sultan H.A, A.Md.Kom'])->save();
        $this->assertFalse(MobileAccess::isSuperAdmin($fresh));
        $this->assertFalse(BackupAccess::isAdmin($fresh));
        $this->assertFalse(MobileAccess::can($fresh, 'signature.approve'));
        $this->assertFalse(MobileAccess::can($fresh, 'duty_roster.sign'));

        $fresh->forceFill(['name' => 'Suma Nurhasanah'])->save();
        $this->actingAs($fresh)->get('/cash')->assertForbidden();
    }

    public function test_flags_grant_the_expected_access(): void
    {
        $dev = $this->makeUser(['is_developer' => true]);
        $this->assertTrue(MobileAccess::isSuperAdmin($dev));
        $this->assertTrue(BackupAccess::isAdmin($dev));

        $bendahara = $this->makeUser(['can_access_cash' => true]);
        $this->assertTrue(MobileAccess::can($bendahara, 'cash.manage'));

        $penandatangan = $this->makeUser(['can_sign_sp_jaga' => true]);
        $this->assertTrue(MobileAccess::can($penandatangan, 'duty_roster.sign'));
    }

    public function test_default_user_role_no_longer_manages_commander_account(): void
    {
        $user = $this->makeUser(['role' => 'user']);
        $this->assertFalse(MobileAccess::can($user, 'commander_account.view'));
        $this->assertFalse(MobileAccess::can($user, 'commander_account.manage'));

        $komandan = $this->makeUser(['role' => 'komandan']);
        $this->assertTrue(MobileAccess::can($komandan, 'commander_account.view'));
        $this->assertFalse(MobileAccess::can($komandan, 'commander_account.manage'));

        $this->assertTrue(MobileAccess::can($this->makeUser(['can_access_commander' => true]), 'commander_account.manage'));
    }

    public function test_only_admin_can_toggle_access_flags(): void
    {
        $target = $this->makeUser();
        $this->actingAs($this->makeUser())->post("/users/{$target->id}/toggle-access-flag", ['flag' => 'can_access_cash'])->assertForbidden();
        $this->assertFalse((bool) $target->fresh()->can_access_cash);

        $this->actingAs($this->makeUser(['role' => 'admin']))->post("/users/{$target->id}/toggle-access-flag", ['flag' => 'can_access_cash'])->assertRedirect();
        $this->assertTrue((bool) $target->fresh()->can_access_cash);

        $this->actingAs($this->makeUser(['role' => 'admin']))->post("/users/{$target->id}/toggle-access-flag", ['flag' => 'role'])->assertSessionHasErrors('flag');
    }

    public function test_nrp_must_be_unique_and_only_admin_changes_it(): void
    {
        $a = $this->makeUser(['nrp' => 'NRP-A']);
        $b = $this->makeUser(['nrp' => 'NRP-B']);
        $this->actingAs($b)->patch('/profile', ['email' => $b->email, 'nrp' => 'NRP-A'])->assertSessionHasErrors('nrp');
        $this->actingAs($b)->patch('/profile', ['email' => $b->email, 'nrp' => 'NRP-BARU'])->assertSessionHasNoErrors();
        $this->assertSame('NRP-B', DB::table('users')->where('id', $b->id)->value('nrp'));
    }
}
