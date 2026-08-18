<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Tambahan untuk logging error WA

class UserController extends Controller
{
    /**
     * Menampilkan daftar personel utama
     * PERBAIKAN: Mengurutkan berdasarkan 'created_at desc' agar data baru langsung terlihat di halaman 1
     */
    public function index()
    {
        return Inertia::render('Users/Index', [
            'users' => User::where('id', '!=', auth()->id()) 
                ->orderBy('created_at', 'desc') // Data terbaru di atas agar langsung terlihat
                ->orderBy('is_active', 'asc') 
                ->paginate(10),

            'allUsers' => User::where('id', '!=', auth()->id())
                ->orderBy('role', 'asc')
                ->get()
        ]);
    }

    /**
     * FITUR: TAMBAH PERSONEL + GENERATE TOKEN AKTIVASI + NOTIFIKASI WA
     * PERBAIKAN: Memisahkan Commit Database dari pengiriman WA agar data tidak hilang (Rollback)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'pangkat' => 'required|string',
            'nrp' => 'required|string|unique:users,nrp',
            'phone' => 'required|string|unique:users,phone',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|string|in:admin,komandan,wadan,pasops,danunit1,danunit2,danunitteknis,kaurmintel,paurset,staf,personel'
        ]);

        try {
            DB::beginTransaction();
            
            // Generate Token Aktivasi Sesuai Instruksi (Contoh: SINDEN-A1B2C3)
            $activationToken = 'SINDEN-' . strtoupper(Str::random(6));
            
            // Sapaan Waktu
            $hour = now()->format('H');
            $sapaan = ($hour >= 5 && $hour < 11) ? "Selamat Pagi" : 
                     (($hour >= 11 && $hour < 15) ? "Selamat Siang" : 
                     (($hour >= 15 && $hour < 18) ? "Selamat Sore" : "Selamat Malam"));

            $roleMapping = [
                'admin'          => 'ADMINISTRATOR SISTEM',
                'komandan'       => 'KOMANDAN (APPROVER)',
                'wadan'          => 'WAKIL KOMANDAN',
                'pasops'         => 'PASOPS',
                'danunit1'       => 'DAN UNIT I / LID',
                'danunit2'       => 'DAN UNIT II / PAMGAL',
                'danunitteknis'  => 'DAN UNIT TEKNIS',
                'kaurmintel'     => 'KAUR MINTEL',
                'paurset'        => 'PAUR SET',
                'staf'           => 'STAF ADMINISTRASI',
                'personel'       => 'PERSONEL SATUAN'
            ];
            $roleLabel = $roleMapping[$request->role] ?? 'PERSONEL';

            // PROSES SIMPAN: Pastikan seluruh field yang diminta model User.php terisi
            $user = User::create([
                'name' => strtoupper($request->name),
                'pangkat' => $request->pangkat,
                'nrp' => $request->nrp,
                'phone' => $request->phone,
                'email' => $request->email,
                'role' => $request->role,
                'password' => Hash::make(Str::random(32)), // Password acak aman
                'activation_token' => $activationToken,
                'is_active' => false, // Default non-aktif sampai aktivasi
                'must_change_password' => true,
            ]);

            AuditLog::create([
                'user_id'          => auth()->id(),
                'admin_name'       => auth()->user()->name,
                'action'           => 'TAMBAH PERSONEL',
                'target_personnel' => $user->name,
                'description'      => "Mendaftarkan {$user->name} sebagai {$roleLabel}. Token Aktivasi telah dikirim via WA.",
                'ip_address'       => $request->ip(),
            ]);

            // KUNCI DATA KE DATABASE TERLEBIH DAHULU (PENTING!)
            DB::commit();

            // PROSES KIRIM WA (Diluar Transaction agar jika gagal, data di database TIDAK hilang/rollback)
            try {
                // PENYESUAIAN FORMAT NOMOR WA (Ubah 08 menjadi 62)
                $targetPhone = $user->phone;
                if (str_starts_with($targetPhone, '0')) {
                    $targetPhone = '62' . substr($targetPhone, 1);
                }

                $pesanWA = " *AKTIVASI AKSES SI SINDEN*\n\n" .
                           "{$sapaan}, *{$user->pangkat} {$user->name}*.\n" .
                           "Mohon izin, akun SINDEN Anda telah dibuat.\n\n" .
                           " *Detail Aktivasi:*\n" .
                           "• Jabatan: *{$roleLabel}*\n" .
                           "• NRP: *{$user->nrp}*\n" .
                           "• Token: *{$activationToken}*\n\n" .
                           "Silakan aktivasi akun dan buat password Anda di:\n" .
                           "https://sisinden.my.id/aktivasi\n\n" .
                           "_Harap segera lakukan aktivasi demi keamanan data._";

                // Kirim menggunakan service yang sudah terhubung ke Port 3000
                WhatsappService::sendMessage($targetPhone, $pesanWA);
                
                return redirect()->back()->with('message', 'Personel berhasil ditambahkan dan Token WA terkirim.');
            } catch (\Exception $waError) {
                // Log error secara internal agar tidak muncul di layar user
                Log::error('Gagal kirim WA ke: ' . $user->phone . ' | Error: ' . $waError->getMessage());
                
                // Jika WA Gagal, data di database tetap aman karena sudah di-commit di atas
                return redirect()->back()->with('message', 'Personel tersimpan, namun WA gagal terkirim (Gateway Offline). Token: ' . $activationToken);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal Simpan Personel: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal sistem: ' . $e->getMessage());
        }
    }

    /**
     * FITUR BARU: PROSES AKTIVASI OLEH PERSONEL
     */
    public function activate(Request $request)
    {
        $request->validate([
            'nrp' => 'required|string',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('nrp', $request->nrp)
                    ->where('activation_token', $request->token)
                    ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'token' => 'NRP atau Token Aktivasi tidak valid.',
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'is_active' => true,
            'activation_token' => null, // Hapus token setelah dipakai
            'must_change_password' => false,
        ]);

        return redirect()->route('login')->with('message', 'Akun berhasil diaktifkan. Silakan login dengan password baru Anda.');
    }

    /**
     * FITUR: CETAK REKAP PERSONEL
     */
    public function printPdf()
    {
        $users = User::where('id', '!=', auth()->id())->orderBy('role', 'asc')->get();
        $agencyName = Setting::where('key', 'agency_name')->first()->value ?? 'DENINTEL KODAERAL V';
        
        return Inertia::render('Users/Index-pdf', [
            'users' => $users,
            'title' => 'REKAPITULASI OTORITAS AKSES PERSONEL',
            'unit' => $agencyName,
            'date' => now()->translatedFormat('d F Y')
        ]);
    }

    /**
     * Update Data Personel
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'pangkat' => 'required|string',
            'nrp' => 'required|string|unique:users,nrp,' . $user->id,
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|unique:users,phone,' . $user->id,
        ]);

        $user->update([
            'name' => strtoupper($request->name),
            'pangkat' => $request->pangkat,
            'nrp' => $request->nrp,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => 'EDIT DATA',
            'target_personnel' => $user->name,
            'description'      => "Admin memperbarui data identitas: {$user->name}",
            'ip_address'       => $request->ip(),
        ]);

        return redirect()->back()->with('message', 'Data Personel berhasil diperbarui.');
    }

    /**
     * Generate Reset Token
     */
    public function generateResetToken(Request $request, User $user)
    {
        $token = (string) rand(100000, 999999);
        $user->update([
            'reset_token' => $token,
            'token_expires_at' => now()->addMinutes(10)
        ]);

        session()->flash('flash.token', $token);
        return redirect()->back()->with('message', 'Token berhasil dibuat.');
    }

    /**
     * Toggle Status Aktif/Non-aktif
     */
    public function toggleStatus(Request $request, User $user)
    {
        $request->validate(['password' => 'required']);

        if (!Hash::check($request->password, auth()->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'Otoritas Gagal: Password Admin tidak valid.',
            ]);
        }

        $statusNew = $user->is_active ? 'NON-AKTIF (SUSPEND)' : 'AKTIF (VERIFIKASI)';
        $user->update(['is_active' => !$user->is_active]);

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name, 
            'action'           => 'TOGGLE STATUS', 
            'target_personnel' => $user->name,
            'description'      => "Admin mengubah status akses menjadi {$statusNew}",
            'ip_address'       => $request->ip(),
        ]);

        return redirect()->back()->with('message', "Akses berhasil diubah menjadi {$statusNew}.");
    }

    /**
     * Hapus Akun
     */
    public function destroy(Request $request, User $user)
    {
        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => 'HAPUS AKUN',
            'target_personnel' => $user->name,
            'description'      => "Admin menghapus permanen akses akun: {$user->name}",
            'ip_address'       => $request->ip(),
        ]);

        $user->delete();
        return redirect()->back()->with('message', 'Data akun telah dihapus dari sistem.');
    }
}