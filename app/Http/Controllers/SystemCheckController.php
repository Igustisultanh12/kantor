<?php

namespace App\Http\Controllers;

use App\Services\ChromeBrowserService;
use App\Services\SystemCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SystemCheckController extends Controller
{
    /**
     * Pastikan hanya Admin / Otoritas Utama yang dapat mengakses modul pemeriksaan sistem
     */
    protected function authorizeAdmin()
    {
        $user = Auth::user();
        if (!$user || ($user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom')) {
            abort(403, 'Akses Ditolak: Modul Cek Sistem & Infrastruktur hanya diizinkan untuk Admin.');
        }
    }

    /**
     * Halaman Utama Dasbor Cek Sistem
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        try {
            $initialMetrics = SystemCheckService::getAllMetrics();
        } catch (\Throwable $e) {
            $initialMetrics = [];
        }

        return Inertia::render('Admin/SystemCheck/Index', [
            'initialMetrics' => $initialMetrics,
        ]);
    }

    /**
     * Endpoint API JSON untuk pembaruan metrik berkala (Real-time polling)
     */
    public function getMetrics(Request $request)
    {
        $this->authorizeAdmin();

        $metrics = SystemCheckService::getAllMetrics();

        return response()->json($metrics);
    }

    /**
     * Endpoint API POST untuk memicu pengujian kecepatan internet Ookla on-demand
     */
    public function runSpeedtest(Request $request)
    {
        $this->authorizeAdmin();

        // Tingkatkan batas waktu eksekusi skrip untuk speedtest jika diperlukan
        @set_time_limit(90);

        $result = SystemCheckService::runOoklaSpeedtest();

        return response()->json($result);
    }

    /**
     * Proxy Jelajah Web untuk Chrome Browser Terintegrasi
     */
    public function chromeBrowse(Request $request)
    {
        $this->authorizeAdmin();

        $url = $request->query('url', 'https://www.google.com');

        return ChromeBrowserService::browse($url);
    }

    /**
     * Endpoint Pengunduh Berkas (Download ke Laptop/HP atau ke Server Storage)
     */
    public function chromeDownload(Request $request)
    {
        $this->authorizeAdmin();

        $url = $request->input('url') ?: $request->query('url');
        if (empty($url)) {
            return response()->json(['message' => 'URL berkas tidak boleh kosong.'], 422);
        }

        $destination = $request->input('destination', 'client');

        if ($destination === 'server') {
            try {
                @set_time_limit(900); // 15 menit
                $result = ChromeBrowserService::downloadToServer($url);
                return response()->json([
                    'success' => true,
                    'message' => "Berkas {$result['file_name']} ({$result['size_human']}) berhasil diunduh ke server.",
                    'data' => $result,
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengunduh berkas ke server: ' . $e->getMessage(),
                ], 500);
            }
        }

        // Unduh ke laptop/HP pengguna (Streaming)
        return ChromeBrowserService::streamDownloadToClient($url);
    }

    /**
     * Dapatkan daftar berkas yang tersimpan di folder unduhan server
     */
    public function chromeServerDownloads(Request $request)
    {
        $this->authorizeAdmin();

        $downloads = ChromeBrowserService::getServerDownloads();

        return response()->json($downloads);
    }

    /**
     * Hapus berkas unduhan dari server
     */
    public function chromeDeleteDownload(Request $request, $filename)
    {
        $this->authorizeAdmin();

        $deleted = ChromeBrowserService::deleteServerDownload($filename);

        return response()->json([
            'success' => $deleted,
            'message' => $deleted ? 'Berkas berhasil dihapus dari server.' : 'Berkas tidak ditemukan.',
        ]);
    }

    /**
     * Cek status konektivitas Docker Chromium Web GUI (noVNC)
     */
    public function chromeCheckDocker(Request $request)
    {
        $this->authorizeAdmin();

        $url = $request->query('url', 'http://127.0.0.1:3001');

        $status = ChromeBrowserService::checkDockerStatus($url);

        return response()->json($status);
    }
}
