<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Personnel;
use App\Models\UcapanConfig; // Menggunakan model baru untuk menghindari bentrok
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // Digunakan untuk memancarkan sinyal ke WA Gateway
use Illuminate\Support\Facades\Log; // Import untuk pencatatan log audit
use Inertia\Inertia;

class PersonnelController extends Controller
{
    public function index()
    {
        // Mengambil data narasi dari pangkalan UcapanConfig
        $config = UcapanConfig::where('slug', 'birthday_message')->first();

        return Inertia::render('Admin/BirthdayRadar', [
            // Memastikan kolom 'pangkat' ikut diambil dari tabel users
            'users' => User::select('id', 'pangkat', 'name', 'phone', 'birth_date')->get(),
            // Mengambil data personel non-akun terbaru
            'personnels' => Personnel::latest()->get(),
            // Mengirim data narasi ke Vue, jika kosong kirim string hampa
            'birthday_message' => $config ? $config->narasi : ""
        ]);
    }

    /**
     * OPERASI KHUSUS: UJI COBA RADIOGRAM (TEST GREETING)
     * Memungkinkan pengiriman tes secara manual ke target yang dipilih
     */
    public function testGreeting(Request $request)
    {
        $request->validate([
            'phone' => 'required',
            'name' => 'required',
            'pangkat' => 'nullable',
            'message_template' => 'required'
        ]);

        // Menggabungkan Pangkat + Nama untuk pengujian
        $identitas = ($request->pangkat ? $request->pangkat . ' ' : '') . $request->name;
        
        // Injeksi dinamis variabel {name} sesuai template yang sedang diketik
        $finalMessage = str_replace('{name}', $identitas, $request->message_template);

        // LOG: Upaya pengiriman radiogram tes
        Log::info("🛰️ Memulai Tes Ucapan ke: $identitas ($request->phone)");

        try {
            // Memancarkan pesan ke WA Gateway Port 3000
            // Pastikan menggunakan $request->phone untuk nomor target
            $response = Http::timeout(10)->get("http://localhost:3000/send", [
                'number' => $request->phone, 
                'msg' => $finalMessage
            ]);

            if ($response->successful()) {
                Log::info("Berhasil Terkirim ke $identitas. Respon: " . $response->body());
                return back()->with('success', 'Radiogram tes berhasil dipancarkan ke ' . $identitas);
            }
            
            Log::warning("⚠️ Gateway merespons Gagal untuk $identitas. Respon: " . $response->body());
            return back()->with('error', 'Gateway merespons dengan kegagalan.');

        } catch (\Exception $e) {
            Log::error("❌ Gagal menghubungi WA Gateway. Pesan: " . $e->getMessage());
            return back()->with('error', 'Radar gagal menghubungi WA Gateway. Pastikan PM2 Aktif!');
        }
    }

    // Simpan Personel Non-Akun
    public function storePersonnel(Request $request)
    {
        $request->validate([
            'pangkat' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'phone' => 'required|string',
            'birth_date' => 'required|date',
        ]);

        $personnel = Personnel::create($request->all());

        // LOG: Penambahan personel eksternal baru
        Log::info("👤 Personel Non-Akun baru ditambahkan: $personnel->pangkat $personnel->name oleh " . auth()->user()->name);

        return back()->with('success', 'Personel Non-Akun berhasil ditambahkan.');
    }

    // Update TTL User Berakun
    public function updateUserBirthDate(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $user->update([
            'pangkat' => $request->pangkat,
            'birth_date' => $request->birth_date,
            'phone' => $request->phone 
        ]);

        // LOG: Pembaruan data personel internal
        Log::info("📝 Data Personel Akun Diperbarui: $user->pangkat $user->name oleh " . auth()->user()->name);

        return back()->with('success', 'Data personel telah diperbarui.');
    }

    // Hapus Personel Non-Akun
    public function destroyPersonnel($id)
    {
        $personnel = Personnel::findOrFail($id);
        $namaPersonel = $personnel->name;
        $personnel->delete();

        // LOG: Penghapusan personel dari radar
        Log::warning("🗑️ Personel Non-Akun Dihapus: $namaPersonel oleh " . auth()->user()->name);

        return back()->with('success', 'Personel telah dihapus dari sistem.');
    }
    
    /**
     * Update Pengaturan Ucapan menggunakan model UcapanConfig
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'birthday_message' => 'required|string'
        ]);

        UcapanConfig::updateOrCreate(
            ['slug' => 'birthday_message'],
            ['narasi' => $request->birthday_message]
        );

        // LOG: Perubahan konfigurasi template
        Log::info("⚙️ Template ucapan dikalibrasi ulang oleh " . auth()->user()->name);

        return back()->with('success', 'Template ucapan ulang tahun telah diperbarui.');
    }
}