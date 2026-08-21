<?php

namespace App\Http\Controllers;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Menampilkan daftar personel utama
     */
    public function index()
    {
        return Inertia::render('Users/Index', [
            'users' => User::where('id', '!=', auth()->id()) 
                ->orderBy('created_at', 'desc')
                ->orderBy('is_active', 'asc') 
                ->paginate(10),

            'allUsers' => User::where('id', '!=', auth()->id())
                ->orderBy('role', 'asc')
                ->get()
        ]);
    }

    /**
     * FITUR: TAMBAH PERSONEL + GENERATE TOKEN AKTIVASI + NOTIFIKASI WA
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
            
            // Generate Token Aktivasi (Contoh: SINDEN-A1B2C3)
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

            // PROSES SIMPAN
            $user = User::create([
                'name' => strtoupper($request->name),
                'pangkat' => $request->pangkat,
                'nrp' => $request->nrp,
                'phone' => $request->phone,
                'email' => $request->email,
                'role' => $request->role,
                'password' => Hash::make(Str::random(32)),
                'activation_token' => $activationToken,
                'is_active' => false,
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

            DB::commit();

            // PROSES KIRIM WA (Pesan Bersih tanpa Karakter Aneh)
            try {
                $targetPhone = $user->phone;
                if (str_starts_with($targetPhone, '0')) {
                    $targetPhone = '62' . substr($targetPhone, 1);
                }

                $pesanWA = "*AKTIVASI AKSES SI SINDEN*\n\n" .
                           "{$sapaan}, *{$user->pangkat} {$user->name}*.\n" .
                           "Mohon izin, akun SINDEN Anda telah dibuat.\n\n" .
                           "*Detail Aktivasi:*\n" .
                           "- Jabatan: *{$roleLabel}*\n" .
                           "- NRP: *{$user->nrp}*\n" .
                           "- Email: *{$user->email}*\n" .
                           "- Token: *{$activationToken}*\n\n" .
                           "Silakan aktivasi akun dan buat password Anda di:\n" .
                           "https://sisinden.my.id/aktivasi\n\n" .
                           "_Harap segera lakukan aktivasi demi keamanan data._";

                WhatsappService::sendMessage($targetPhone, $pesanWA);
                
                return redirect()->back()->with('message', 'Personel berhasil ditambahkan dan Token WA terkirim.');
            } catch (\Exception $waError) {
                Log::error('Gagal kirim WA ke: ' . $user->phone . ' | Error: ' . $waError->getMessage());
                return redirect()->back()->with('message', 'Personel tersimpan, namun WA gagal terkirim (Gateway Offline). Token: ' . $activationToken);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal Simpan Personel: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal sistem: ' . $e->getMessage());
        }
    }

    /**
     * FITUR: PROSES AKTIVASI OLEH PERSONEL
     */
    public function activate(Request $request)
    {
        $request->validate([
            'nrp' => 'required|string',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $identifier = trim($request->nrp);
        $token = trim($request->token);

        $user = User::where('activation_token', $token)
                    ->where(function($q) use ($identifier) {
                        $q->where('nrp', $identifier)
                          ->orWhere('email', $identifier);
                    })
                    ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'token' => 'NRP / Email atau Token Aktivasi tidak valid.',
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'is_active' => true,
            'activation_token' => null,
            'must_change_password' => false,
        ]);

        // Kirim Notifikasi WA bahwa Aktivasi Telah Berhasil
        try {
            if (!empty($user->phone)) {
                $targetPhone = $user->phone;
                if (str_starts_with($targetPhone, '0')) {
                    $targetPhone = '62' . substr($targetPhone, 1);
                }

                $pesanWA = "*AKTIVASI AKUN SINDEN BERHASIL*\n\n" .
                           "Selamat, *{$user->pangkat} {$user->name}*!\n" .
                           "Akun SINDEN Anda telah diaktifkan.\n\n" .
                           "*Detail Otoritas:*\n" .
                           "- NRP: *{$user->nrp}*\n" .
                           "- Email: *{$user->email}*\n\n" .
                           "Silakan login ke sistem menggunakan Email (*{$user->email}*) dan Password yang telah Anda buat di:\n" .
                           "https://sisinden.my.id/login\n\n" .
                           "_Tetap jaga kerahasiaan kredensial akun Anda._";

                WhatsappService::sendMessage($targetPhone, $pesanWA);
            }
        } catch (\Exception $waError) {
            Log::error('Gagal kirim notif WA aktivasi ke: ' . $user->phone . ' | Error: ' . $waError->getMessage());
        }

        return redirect()->route('login')->with('message', 'Aktivasi Akun Berhasil! Akun Anda telah aktif. Silakan login menggunakan Email (' . $user->email . ') dan Password Anda.');
    }

    /**
     * FITUR: TAMBAH BANYAK PERSONEL (BULK) + CETAK TOKEN AKTIVASI PDF (TANPA WA)
     */
    public function storeBulk(Request $request)
    {
        $request->validate([
            'users' => 'required|array|min:1',
            'users.*.name' => 'required|string|max:255',
            'users.*.pangkat' => 'required|string',
            'users.*.nrp' => 'required|string|distinct|unique:users,nrp',
            'users.*.email' => 'nullable|email|distinct|unique:users,email',
            'users.*.phone' => 'nullable|string',
            'users.*.role' => 'nullable|string',
        ]);

        $createdIds = [];

        try {
            DB::beginTransaction();

            foreach ($request->users as $item) {
                $activationToken = 'SINDEN-' . strtoupper(Str::random(6));
                $rawNrp = preg_replace('/[^A-Za-z0-9]/', '', $item['nrp']);
                $email = !empty($item['email']) ? $item['email'] : (strtolower($rawNrp) . '@sinden.my.id');
                $role = !empty($item['role']) ? $item['role'] : 'personel';
                $phone = !empty($item['phone']) ? $item['phone'] : null;

                $user = User::create([
                    'name' => strtoupper($item['name']),
                    'pangkat' => $item['pangkat'],
                    'nrp' => $item['nrp'],
                    'phone' => $phone,
                    'email' => $email,
                    'role' => $role,
                    'password' => Hash::make(Str::random(32)),
                    'activation_token' => $activationToken,
                    'is_active' => false,
                    'must_change_password' => true,
                ]);

                $createdIds[] = $user->id;

                AuditLog::create([
                    'user_id'          => auth()->id(),
                    'admin_name'       => auth()->user()->name,
                    'action'           => 'TAMBAH PERSONEL BULK',
                    'target_personnel' => $user->name,
                    'description'      => "Mendaftarkan masal {$user->name} ({$user->pangkat}/{$user->nrp}). Kode verifikasi via PDF.",
                    'ip_address'       => $request->ip(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($createdIds) . ' Akun personel berhasil didaftarkan.',
                'ids' => $createdIds,
                'pdf_url' => route('users.print-token-pdf', ['ids' => implode(',', $createdIds)])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal tambah personel bulk: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal memproses pendaftaran masal: ' . $e->getMessage()], 422);
        }
    }

    /**
     * FITUR: CETAK KODE VERIFIKASI & TOKEN AKTIVASI PDF (FORMAT RESMI DETASEMEN INTELIJEN)
     */
    public function printTokenPdf(Request $request)
    {
        $idsParam = $request->query('ids');
        if ($idsParam) {
            $ids = explode(',', $idsParam);
            $personels = User::whereIn('id', $ids)->get();
        } else {
            $personels = User::where('is_active', false)->whereNotNull('activation_token')->get();
        }

        $user = auth()->user();
        $signerJabatan = ($user->role === 'admin' || $user->name === 'I Gusti Sultan H.A, A.Md.Kom') ? 'Administrator SINDEN' : 'Administrator Sistem';
        $signerName = $user->name;
        $signerPangkat = $user->pangkat ?: 'MAYOR LAUT (P)';
        $signerNrp = $user->nrp ?: '12000018012200216';

        $qrCodeBase64 = null;
        try {
            $qrData = "SINDEN-VERIFY-TOKEN|" . date('YmdHis') . "|" . $user->nrp;
            $qrCodeBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents('https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($qrData)));
        } catch (\Exception $e) {}

        $data = [
            'personels' => $personels,
            'nomorSurat' => 'SINDEN/VERIF/' . date('Ymd/His'),
            'generatedAt' => now()->translatedFormat('d F Y'),
            'signerJabatan' => $signerJabatan,
            'signerName' => $signerName,
            'signerPangkat' => $signerPangkat,
            'signerNrp' => $signerNrp,
            'qrCodeBase64' => $qrCodeBase64
        ];

        $pdf = Pdf::loadView('pdf.kodeverifikasi', $data)->setPaper('a4', 'landscape');
        return $pdf->stream('Laporan_Kode_Verifikasi_' . date('Ymd_His') . '.pdf');
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