<?php

namespace App\Http\Controllers;

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
}
