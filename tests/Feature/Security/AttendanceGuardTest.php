<?php

namespace Tests\Feature\Security;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Audit S06: presensi diverifikasi di server. */
class AttendanceGuardTest extends TestCase
{
    use SecuritySchema;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Schema::create('attendances', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id'); $t->date('attendance_date'); $t->time('time_in')->nullable(); $t->string('status')->nullable();
            $t->string('ip_address')->nullable(); $t->string('device')->nullable(); $t->string('user_agent')->nullable();
            $t->decimal('latitude', 10, 7)->nullable(); $t->decimal('longitude', 10, 7)->nullable(); $t->string('location_name')->nullable();
            $t->text('notes')->nullable(); $t->string('photo')->nullable(); $t->timestamps();
        });
        config(['services.attendance.office_lat' => -7.2575, 'services.attendance.office_lng' => 112.7521, 'services.attendance.radius_m' => 500]);
        $this->token = $this->makeUser()->createToken('t')->plainTextToken;
    }

    private function photo(): string
    {
        $img = imagecreatetruecolor(4, 4);
        ob_start(); imagejpeg($img); $jpg = ob_get_clean();
        return 'data:image/jpeg;base64,' . base64_encode($jpg);
    }

    private function absen(array $extra)
    {
        return $this->withToken($this->token)->postJson('/api/mobile/attendances', array_merge([
            'status' => 'hadir', 'latitude' => -7.2576, 'longitude' => 112.7522, 'photo' => $this->photo(),
        ], $extra));
    }

    public function test_mock_location_is_rejected(): void
    {
        $this->absen(['is_mock' => true])->assertStatus(422)->assertJsonPath('code', 'attendance_rejected');
    }

    public function test_outside_geofence_is_rejected_but_dinas_luar_allowed(): void
    {
        $this->absen(['latitude' => -6.2, 'longitude' => 106.8])->assertStatus(422);
        $this->absen(['status' => 'dinas_luar', 'latitude' => -6.2, 'longitude' => 106.8])->assertOk();
    }

    public function test_poor_accuracy_is_rejected(): void
    {
        $this->absen(['accuracy' => 900])->assertStatus(422);
    }

    public function test_first_attendance_cannot_be_overwritten(): void
    {
        $this->absen([])->assertOk();
        $this->absen(['status' => 'piket'])->assertStatus(409)->assertJsonPath('code', 'attendance_exists');
        $this->assertDatabaseCount('attendances', 1);
    }
}
