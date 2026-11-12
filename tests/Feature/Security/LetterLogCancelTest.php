<?php

namespace Tests\Feature\Security;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Agenda Surat: pembatalan booking nomor. */
class LetterLogCancelTest extends TestCase
{
    use SecuritySchema;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('letter_logs', function (Blueprint $t) {
            $t->id(); $t->string('full_number')->nullable(); $t->string('sequence')->nullable(); $t->string('priority')->nullable();
            $t->unsignedBigInteger('category_id')->nullable(); $t->unsignedBigInteger('sub_category_id')->nullable();
            $t->string('subject')->nullable(); $t->string('recipient')->nullable(); $t->date('date')->nullable();
            $t->boolean('is_archived')->default(false); $t->string('number')->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->timestamps();
        });
    }

    private function log(array $attrs): int
    {
        return DB::table('letter_logs')->insertGetId(array_merge(['full_number' => 'B / 9 / SKHPP / XI / 2026', 'sequence' => '9', 'subject' => 'Uji', 'date' => '2026-11-13'], $attrs));
    }

    public function test_owner_can_cancel_unarchived_booking(): void
    {
        $owner = $this->makeUser();
        $id = $this->log(['user_id' => $owner->id]);
        $this->actingAs($owner)->delete("/letter-logs/{$id}")->assertSessionHas('success');
        $this->assertDatabaseMissing('letter_logs', ['id' => $id]);
    }

    public function test_other_user_cannot_cancel(): void
    {
        $id = $this->log(['user_id' => $this->makeUser()->id]);
        $this->actingAs($this->makeUser())->delete("/letter-logs/{$id}")->assertForbidden();
        $this->assertDatabaseHas('letter_logs', ['id' => $id]);
    }

    public function test_archived_booking_cannot_be_cancelled_even_by_admin(): void
    {
        $id = $this->log(['is_archived' => true]);
        $this->actingAs($this->makeUser(['role' => 'admin']))->delete("/letter-logs/{$id}")->assertSessionHas('error');
        $this->assertDatabaseHas('letter_logs', ['id' => $id]);
    }

    public function test_admin_can_cancel_legacy_booking_without_owner(): void
    {
        $id = $this->log(['user_id' => null]);
        $this->actingAs($this->makeUser())->delete("/letter-logs/{$id}")->assertForbidden();
        $this->actingAs($this->makeUser(['role' => 'admin']))->delete("/letter-logs/{$id}")->assertSessionHas('success');
        $this->assertDatabaseMissing('letter_logs', ['id' => $id]);
    }
}
