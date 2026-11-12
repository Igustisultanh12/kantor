<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Log Aktivitas: aksi tulis tercatat lengkap, login & GET tidak, dan halaman/PDF admin. */
class ActivityLogTest extends TestCase
{
    private User $admin;
    private User $anggota;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('nrp')->nullable();
            $t->string('pangkat')->nullable();
            $t->string('jabatan')->nullable();
            $t->string('role')->default('personel');
            $t->string('password');
            $t->boolean('is_active')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        (require database_path('migrations/2026_11_12_210000_create_activity_logs_table.php'))->up();
        $this->withoutMiddleware([\App\Http\Middleware\LogVisitor::class]);
        Http::fake(['*' => Http::response(['status' => 'success', 'city' => 'Surabaya', 'country' => 'Indonesia'], 200)]);

        $this->admin = User::create(['name' => 'Admin Uji', 'email' => 'a@t.l', 'nrp' => '111', 'pangkat' => 'Mayor', 'role' => 'admin', 'password' => Hash::make('x')]);
        $this->anggota = User::create(['name' => 'Budi Santoso', 'email' => 'b@t.l', 'nrp' => '22345/P', 'pangkat' => 'Serda', 'role' => 'personel', 'password' => Hash::make('x')]);

        Route::middleware(['web', 'auth'])->group(function () {
            Route::post('/letters/{letter}/approve', fn () => back()->with('success', 'ok'))->name('letters.approve');
            Route::post('/letters', fn () => back()->withErrors(['perihal' => 'wajib']))->name('letters.store');
            Route::get('/letters', fn () => 'daftar')->name('letters.index');
        });
    }

    public function test_write_action_is_recorded_with_who_what_device_ip(): void
    {
        $this->actingAs($this->anggota)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'])
            ->withServerVariables(['REMOTE_ADDR' => '36.80.1.2'])
            ->post('/letters/12/approve', [
                'catatan' => 'Disetujui untuk diteruskan',
                'password' => 'rahasia',
                'otp_code' => '123456',
                'lampiran' => UploadedFile::fake()->create('nota.pdf', 20),
            ])->assertRedirect();

        $log = ActivityLog::sole();
        $this->assertSame($this->anggota->id, $log->user_id);
        $this->assertSame('Budi Santoso', $log->user_name);
        $this->assertSame('22345/P', $log->nrp);
        $this->assertSame('web', $log->channel);
        $this->assertStringContainsString('Menyetujui Surat', $log->action);
        $this->assertSame(['letter' => '12'], $log->subject);
        $this->assertSame('Disetujui untuk diteruskan', $log->payload['catatan']);
        $this->assertArrayNotHasKey('password', $log->payload);
        $this->assertSame('[disamarkan]', $log->payload['otp_code']);
        $this->assertStringStartsWith('[berkas: nota.pdf', $log->payload['lampiran']);
        $this->assertSame('36.80.1.2', $log->ip);
        $this->assertStringContainsString('Surabaya', (string) $log->ip_location);
        $this->assertStringContainsString('Chrome', (string) $log->device);
        $this->assertTrue($log->success);
    }

    public function test_failed_validation_is_marked_failed_and_reads_logins_are_not_logged(): void
    {
        $this->actingAs($this->anggota)->post('/letters', ['perihal' => ''])->assertRedirect();
        $this->assertFalse(ActivityLog::sole()->success);

        $this->actingAs($this->anggota)->get('/letters')->assertOk();
        $this->post('/logout');
        $this->post('/login', ['login' => 'x', 'password' => 'y']);
        $this->assertSame(1, ActivityLog::count(), 'GET, login, dan logout tidak boleh masuk Log Aktivitas');
    }

    public function test_only_archive_letters_and_finance_are_recorded(): void
    {
        Route::middleware(['web', 'auth'])->group(function () {
            Route::patch('/profile', fn () => back())->name('profile.update');
            Route::post('/attendances', fn () => back())->name('attendances.store');
            Route::post('/technical-cash', fn () => back())->name('technical-cash.store');
            Route::delete('/pc-backup/destroy/{id}', fn () => back())->name('backup.destroy');
            Route::post('/pc-backup/upload-chunk', fn () => back())->name('backup.upload-chunk');
        });

        $u = $this->actingAs($this->anggota);
        $u->patch('/profile', ['name' => 'Budi', 'phone' => '0812'])->assertRedirect();
        $u->post('/attendances', ['status' => 'hadir'])->assertRedirect();
        $u->post('/pc-backup/upload-chunk')->assertRedirect();
        $this->assertSame(0, ActivityLog::count(), 'Data pribadi, absensi, dan potongan unggahan tidak dicatat');

        $u->post('/technical-cash', ['amount' => 50000])->assertRedirect();
        $u->delete('/pc-backup/destroy/9')->assertRedirect();
        $this->assertSame(['Kas Unit Teknis', 'Arsip Backup'], ActivityLog::orderBy('id')->pluck('module')->all());
    }

    public function test_admin_can_view_and_print_pdf_but_member_cannot(): void
    {
        $this->actingAs($this->anggota)->post('/letters/7/approve')->assertRedirect();

        $this->actingAs($this->anggota)->get('/activity-logs')->assertStatus(403);
        $this->actingAs($this->admin)->get('/activity-logs?user_id=' . $this->anggota->id, ['X-Inertia' => 'true', 'X-Inertia-Version' => \Inertia\Inertia::getVersion()])
            ->assertOk()
            ->assertJsonPath('component', 'ActivityLogs/Index')
            ->assertJsonPath('props.logs.data.0.user.name', 'Budi Santoso');

        $pdf = $this->actingAs($this->admin)->get('/activity-logs/pdf?from=' . now()->toDateString());
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
    }
}
