<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SignatureRequest;
use Illuminate\Support\Facades\Storage;
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
     * Menampilkan daftar personel utama + Antrean Personel dari Dokumen SP Jaga
     */
    public function index()
    {
        $rawPdfPersonnel = [
            ['name' => 'Bambang', 'pangkat' => 'Peltu Saa', 'nrp' => '82068', 'role' => 'anggotasintel'],
            ['name' => 'Rizal Nurdin W.', 'pangkat' => 'Kapten Laut (P)', 'nrp' => '20873/P', 'role' => 'anggotasintel'],
            ['name' => 'Alex Hamid R.T.', 'pangkat' => 'Kapten Laut (P)', 'nrp' => '22029/P', 'role' => 'anggotasintel'],
            ['name' => 'Indra Gunawan', 'pangkat' => 'Kapten Laut (P)', 'nrp' => '19739/P', 'role' => 'danunit1'],
            ['name' => 'Erwan Junaidi', 'pangkat' => 'Peltu Ttg', 'nrp' => '84025', 'role' => 'anggotasintel'],
            ['name' => "Agus Sub'chan", 'pangkat' => 'Lettu Laut (T)', 'nrp' => '25724/P', 'role' => 'anggotasintel'],
            ['name' => 'Agus Musonif', 'pangkat' => 'Lettu Laut (P)', 'nrp' => '26327/P', 'role' => 'anggotasintel'],
            ['name' => 'HASAN BASRI', 'pangkat' => 'PELDA MAR', 'nrp' => '106737', 'role' => 'anggotasintel'],
            ['name' => 'ADITYA H.', 'pangkat' => 'SERMA KOM', 'nrp' => '115980', 'role' => 'anggotasintel'],
            ['name' => 'ACHMAD H.', 'pangkat' => 'KOPKA LIS', 'nrp' => '89853', 'role' => 'anggotasintel'],
            ['name' => 'SUNARKO, S.H', 'pangkat' => 'PENATA III/C', 'nrp' => '197308261994011001', 'role' => 'anggotasintel'],
            ['name' => 'ANDIS Y.', 'pangkat' => 'SERKA EKO', 'nrp' => '114153', 'role' => 'anggotasintel'],
            ['name' => 'PUJIANTO', 'pangkat' => 'SERKA TKU', 'nrp' => '117387', 'role' => 'anggotasintel'],
            ['name' => 'RINO Y', 'pangkat' => 'KOPTU TLG', 'nrp' => '113623', 'role' => 'anggotasintel'],
            ['name' => 'HADIS S.', 'pangkat' => 'PENDA TK I III/B', 'nrp' => '197101311994031002', 'role' => 'anggotasintel'],
            ['name' => 'HARTANTO', 'pangkat' => 'PELTU NAV', 'nrp' => '98486', 'role' => 'anggotasintel'],
            ['name' => 'DWI PURNOMO', 'pangkat' => 'SERTU TTU', 'nrp' => '105224', 'role' => 'anggotasintel'],
            ['name' => 'RUMADI', 'pangkat' => 'SERTU TTU', 'nrp' => '88386', 'role' => 'anggotasintel'],
            ['name' => 'EKO YANUAR', 'pangkat' => 'PENG TK I II/D', 'nrp' => '197201141998031003', 'role' => 'anggotasintel'],
            ['name' => 'TRI WINDARTO', 'pangkat' => 'SERMA PDK', 'nrp' => '114222', 'role' => 'anggotasintel'],
            ['name' => 'KARIYADI', 'pangkat' => 'SERKA JAS', 'nrp' => '85822', 'role' => 'anggotasintel'],
            ['name' => 'IFAN SUSANTO', 'pangkat' => 'KOPKA MES', 'nrp' => '99018', 'role' => 'anggotasintel'],
            ['name' => 'SAGUS N', 'pangkat' => 'PENATA TK I III/D', 'nrp' => '19701221994021001', 'role' => 'anggotasintel'],
            ['name' => 'RIBUT JOHAN P', 'pangkat' => 'SERMA KEU', 'nrp' => '112631', 'role' => 'anggotasintel'],
            ['name' => 'HENDRA S', 'pangkat' => 'SERMA KOM', 'nrp' => '114931', 'role' => 'anggotasintel'],
            ['name' => 'HERI WARSITO', 'pangkat' => 'PENDA TK I III/B', 'nrp' => '197502022002122006', 'role' => 'anggotasintel'],
            ['name' => 'GUNAWAN', 'pangkat' => 'PENDA III/A', 'nrp' => '197810062005011005', 'role' => 'anggotasintel'],
        ];

        $allUsers = User::all();
        $existingNrps = $allUsers->pluck('nrp')->filter()->map(fn($n) => strtolower(trim(preg_replace('/[^A-Za-z0-9]/', '', $n))))->toArray();
        $existingNames = $allUsers->pluck('name')->filter()->map(fn($n) => strtolower(trim($n)))->toArray();

        $pendingSpPersonnel = [];
        foreach ($rawPdfPersonnel as $p) {
            $cleanNrp = strtolower(trim(preg_replace('/[^A-Za-z0-9]/', '', $p['nrp'])));
            $cleanName = strtolower(trim($p['name']));

            $exists = in_array($cleanNrp, $existingNrps);
            if (!$exists) {
                foreach ($existingNames as $exName) {
                    if (str_contains($exName, $cleanName) || str_contains($cleanName, $exName)) {
                        $exists = true;
                        break;
                    }
                }
            }

            if (!$exists) {
                $pendingSpPersonnel[] = [
                    'name' => $p['name'],
                    'pangkat' => $p['pangkat'],
                    'nrp' => $p['nrp'],
                    'email' => strtolower($cleanNrp) . '@sinden.my.id',
                    'phone' => '',
                    'role' => $p['role'] ?? 'anggotasintel',
                ];
            }
        }

        return Inertia::render('Users/Index', [
            'users' => User::where('id', '!=', auth()->id()) 
                ->orderBy('created_at', 'desc')
                ->orderBy('is_active', 'asc') 
                ->paginate(10),

            'allUsers' => User::where('id', '!=', auth()->id())
                ->orderBy('role', 'asc')
                ->get(),

            'pendingSpPersonnel' => $pendingSpPersonnel,
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
            'role' => 'required|string|in:admin,komandan,wadan,pasops,danunit1,danunit2,danunitteknis,kaurmintel,paurset,staf,personel,anggotasintel'
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
                'personel'       => 'PERSONEL SATUAN',
                'anggotasintel'  => 'ANGGOTA SINTEL',
            ];
            $roleLabel = $roleMapping[$request->role] ?? 'PERSONEL';

            // PROSES SIMPAN
            $user = User::create([
                'name' => trim($request->name),
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

            // PROSES KIRIM WA
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
     * FITUR: TAMBAH BANYAK PERSONEL (BULK) + CETAK TOKEN AKTIVASI PDF
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
                'personel'       => 'PERSONEL SATUAN',
                'anggotasintel'  => 'ANGGOTA SINTEL',
            ];

            foreach ($request->users as $item) {
                $activationToken = 'SINDEN-' . strtoupper(Str::random(6));
                $rawNrp = preg_replace('/[^A-Za-z0-9]/', '', $item['nrp']);
                $email = !empty($item['email']) ? $item['email'] : (strtolower($rawNrp) . '@sinden.my.id');
                $role = !empty($item['role']) ? $item['role'] : 'anggotasintel';
                $phone = !empty($item['phone']) ? $item['phone'] : null;

                $user = User::create([
                    'name' => trim($item['name']),
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

                // Jika nomor telepon diisi, kirim notifikasi WA
                if (!empty($user->phone)) {
                    try {
                        $targetPhone = $user->phone;
                        if (str_starts_with($targetPhone, '0')) {
                            $targetPhone = '62' . substr($targetPhone, 1);
                        }
                        $pesanWA = "*AKTIVASI AKSES SI SINDEN*\n\n" .
                                   "Mohon izin, *{$user->pangkat} {$user->name}*.\n" .
                                   "Akun SINDEN Anda telah dibuat.\n\n" .
                                   "*Detail Aktivasi:*\n" .
                                   "- Jabatan: *" . ($roleMapping[$user->role] ?? 'ANGGOTA SINTEL') . "*\n" .
                                   "- NRP: *{$user->nrp}*\n" .
                                   "- Email: *{$user->email}*\n" .
                                   "- Token: *{$activationToken}*\n\n" .
                                   "Silakan aktivasi akun dan buat password Anda di:\n" .
                                   "https://sisinden.my.id/aktivasi\n\n" .
                                   "_Harap segera lakukan aktivasi demi kelancaran kedinasan._";

                        WhatsappService::sendMessage($targetPhone, $pesanWA);
                    } catch (\Exception $waErr) {
                        Log::warning('Gagal kirim WA bulk: ' . $waErr->getMessage());
                    }
                }
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
            Log::error('Gagal Bulk Personel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * FITUR CETAK KODE VERIFIKASI (TOKEN AKTIVASI) PDF
     */
    public function printTokenPdf(Request $request)
    {
        $idsParam = $request->query('ids');
        $query = User::where('is_active', false)->whereNotNull('activation_token');

        if ($idsParam) {
            $ids = explode(',', $idsParam);
            $query->whereIn('id', $ids);
        }

        $users = $query->orderBy('name', 'asc')->get();

        if ($users->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada data token personel belum aktif yang dapat dicetak.');
        }

        $pdf = Pdf::loadView('pdf.kodeverifikasi', compact('users'));
        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('Kode_Verifikasi_Personel_SINDEN.pdf');
    }

    /**
     * GENERATE TOKEN MANUAL DARI ADMIN
     */
    public function generateToken(User $user)
    {
        $token = strtoupper(Str::random(6));
        $expiresAt = now()->addMinutes(5);

        $user->update([
            'reset_token' => $token,
            'token_expires_at' => $expiresAt
        ]);

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => 'REQUEST TOKEN',
            'target_personnel' => $user->name,
            'description'      => "Men-generate token reset password untuk {$user->name}",
            'ip_address'       => request()->ip(),
        ]);

        return back()->with('flash', [
            'token' => $token,
            'message' => "Token untuk {$user->name} berhasil dibuat: {$token}"
        ]);
    }

    /**
     * TOGGLE STATUS AKTIF / NONAKTIF PERSONEL
     */
    public function toggle(Request $request, User $user)
    {
        $request->validate(['password' => 'required']);

        if (!Hash::check($request->password, auth()->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password Konfirmasi Salah!']);
        }

        $oldStatus = $user->is_active;
        $user->update(['is_active' => !$oldStatus]);

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => $user->is_active ? 'AKTIVASI USER' : 'SUSPEND USER',
            'target_personnel' => $user->name,
            'description'      => "Merubah status akses {$user->name} menjadi " . ($user->is_active ? 'Aktif' : 'Nonaktif'),
            'ip_address'       => $request->ip(),
        ]);

        return back()->with('message', 'Status akses berhasil diperbarui.');
    }

    /**
     * UPDATE DATA PERSONEL
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'pangkat' => 'required|string',
            'nrp'     => 'required|string|unique:users,nrp,' . $user->id,
            'phone'   => 'nullable|string',
            'email'   => 'required|email|unique:users,email,' . $user->id,
            'role'    => 'nullable|string|in:admin,komandan,wadan,pasops,danunit1,danunit2,danunitteknis,kaurmintel,paurset,staf,personel,anggotasintel',
        ]);

        $updateData = [
            'name'    => trim($request->name),
            'pangkat' => $request->pangkat,
            'nrp'     => $request->nrp,
            'phone'   => $request->phone,
            'email'   => $request->email,
        ];

        if ($request->filled('role')) {
            $updateData['role'] = $request->role;
        }

        $user->update($updateData);

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => 'UPDATE PERSONEL',
            'target_personnel' => $user->name,
            'description'      => "Memperbarui data profil {$user->name}",
            'ip_address'       => $request->ip(),
        ]);

        return back()->with('message', 'Data personel berhasil diperbarui.');
    }

    /**
     * TOGGLE HAK AKSES MITRA
     */
    public function toggleMitraAccess(User $user)
    {
        $user->update(['can_access_mitra' => !$user->can_access_mitra]);

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => 'UPDATE HAK AKSES MITRA',
            'target_personnel' => $user->name,
            'description'      => "Mengubah hak akses mitra untuk {$user->name} menjadi " . ($user->can_access_mitra ? 'Diizinkan' : 'Dilarang'),
            'ip_address'       => request()->ip(),
        ]);

        return back()->with('message', 'Hak akses mitra berhasil diperbarui.');
    }

    /**
     * TOGGLE HAK AKSES BUKU KAS TEKNIS
     */
    public function toggleTechnicalCashAccess(User $user)
    {
        $user->update(['can_access_technical_cash' => !$user->can_access_technical_cash]);

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => 'UPDATE HAK AKSES KAS TEKNIS',
            'target_personnel' => $user->name,
            'description'      => "Mengubah hak akses kas teknis untuk {$user->name} menjadi " . ($user->can_access_technical_cash ? 'Diizinkan' : 'Dilarang'),
            'ip_address'       => request()->ip(),
        ]);

        return back()->with('message', 'Hak akses kas teknis berhasil diperbarui.');
    }

    /**
     * HAPUS PERSONEL
     */
    public function destroy(User $user)
    {
        $name = $user->name;
        $user->delete();

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => 'HAPUS PERSONEL',
            'target_personnel' => $name,
            'description'      => "Menghapus akun {$name} dari sistem",
            'ip_address'       => request()->ip(),
        ]);

        return back()->with('message', 'Personel berhasil dihapus.');
    }
}