<?php

namespace App\Http\Controllers;

use App\Models\Pc;
use App\Models\PcAccessRequest;
use App\Services\PcAccessException;
use App\Services\PcAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint web (JSON) untuk minta akses penyimpanan rekan.
 * Logika ada di PcAccessService agar sama persis dengan aplikasi Android.
 */
class PcAccessController extends Controller
{
    public function __construct(private readonly PcAccessService $service)
    {
    }

    public function store(Request $request, Pc $pc): JsonResponse
    {
        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        return $this->run(fn () => $this->ok(
            'Kode verifikasi sudah dikirim ke WhatsApp Anda.',
            $this->service->request($request->user(), $pc, $data['reason'] ?? null)
        ));
    }

    public function resend(Request $request, PcAccessRequest $accessRequest): JsonResponse
    {
        return $this->run(fn () => $this->ok(
            'Kode baru sudah dikirim ke WhatsApp Anda.',
            $this->service->resend($request->user(), $accessRequest)
        ));
    }

    public function verify(Request $request, PcAccessRequest $accessRequest): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|max:10']);

        return $this->run(fn () => $this->ok(
            'Kode cocok. Permintaan Anda sudah diteruskan ke pemilik penyimpanan.',
            $this->service->verify($request->user(), $accessRequest, $data['code'])
        ));
    }

    public function approve(Request $request, PcAccessRequest $accessRequest): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:500']);

        return $this->run(fn () => $this->ok(
            'Akses diberikan. Rekan Anda sudah diberi tahu.',
            $this->service->decide($request->user(), $accessRequest, true, $data['note'] ?? null)
        ));
    }

    public function reject(Request $request, PcAccessRequest $accessRequest): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:500']);

        return $this->run(fn () => $this->ok(
            'Permintaan ditolak. Rekan Anda sudah diberi tahu.',
            $this->service->decide($request->user(), $accessRequest, false, $data['note'] ?? null)
        ));
    }

    public function destroy(Request $request, PcAccessRequest $accessRequest): JsonResponse
    {
        $isRequester = (int) $accessRequest->requester_id === (int) $request->user()->id;

        return $this->run(fn () => $this->ok(
            $isRequester ? 'Permintaan dibatalkan.' : 'Akses dicabut.',
            $this->service->withdraw($request->user(), $accessRequest)
        ));
    }

    private function ok(string $message, PcAccessRequest $req): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'request' => $this->service->payload($req),
        ]);
    }

    private function run(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (PcAccessException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->status);
        }
    }
}
