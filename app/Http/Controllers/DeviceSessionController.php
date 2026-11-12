<?php

namespace App\Http\Controllers;

use App\Services\DeviceSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Perangkat & sesi aktif akun sendiri (web): lihat, keluarkan satu, keluarkan semua lainnya.
 * Aplikasi Android mengirim info perangkatnya lewat heartbeat().
 */
class DeviceSessionController extends Controller
{
    public function __construct(private readonly DeviceSessionService $devices)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'web_supported' => $this->devices->webSessionsSupported(),
            'devices' => $this->devices->list($request->user(), $request->session()->getId(), null),
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $this->devices->reportWeb($request, $this->validTelemetry($request));
        return response()->json(['status' => 'success']);
    }

    public function destroy(Request $request, string $kind, string $id): JsonResponse
    {
        if ($kind === 'web' && $id === $request->session()->getId()) {
            return response()->json(['status' => 'error', 'message' => 'Ini perangkat yang sedang Anda pakai. Gunakan menu Keluar untuk keluar dari perangkat ini.'], 422);
        }
        if (!in_array($kind, ['web', 'android'], true) || !$this->devices->revoke($request->user(), $kind, $id)) {
            return response()->json(['status' => 'error', 'message' => 'Sesi tidak ditemukan atau sudah berakhir.'], 404);
        }
        return response()->json(['status' => 'success', 'message' => 'Perangkat sudah dikeluarkan dari akun Anda.']);
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        $n = $this->devices->revokeOthers($request->user(), $request->session()->getId(), null);
        return response()->json(['status' => 'success', 'message' => $n > 0 ? "{$n} perangkat lain sudah dikeluarkan." : 'Tidak ada perangkat lain yang sedang masuk.']);
    }

    /** POST /api/mobile/devices/heartbeat */
    public function heartbeat(Request $request): JsonResponse
    {
        $data = $this->validTelemetry($request, mobile: true);
        $this->devices->heartbeatAndroid($request, $data);
        return response()->json(['status' => 'success']);
    }

    private function validTelemetry(Request $request, bool $mobile = false): array
    {
        $input = $request->json()->all() ?: $request->all();
        $rules = [
            'battery' => 'nullable|integer|min:0|max:100',
            'charging' => 'nullable|boolean',
            'network' => 'nullable|string|max:30',
            'screen' => 'nullable|string|max:30',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0|max:100000',
            'location_at' => 'nullable|integer',
        ];
        if ($mobile) {
            $rules += [
                'device_id' => 'nullable|string|max:64',
                'manufacturer' => 'nullable|string|max:60',
                'model' => 'nullable|string|max:80',
                'os_version' => 'nullable|string|max:20',
                'sdk' => 'nullable|integer|min:1|max:200',
                'app_version' => 'nullable|string|max:20',
                'push_token' => 'nullable|string|max:4096',
            ];
        }
        // Data telemetri tidak wajib: field tidak valid dibuang, bukan menolak seluruh laporan.
        $v = Validator::make($input, $rules);
        $bad = array_keys($v->errors()->toArray());
        return array_diff_key(array_intersect_key($input, $rules), array_flip($bad));
    }
}
