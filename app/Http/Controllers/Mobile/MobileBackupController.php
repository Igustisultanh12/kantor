<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\Pc;
use App\Models\PcAccessRequest;
use App\Services\ArwService;
use App\Services\FileSecurityService;
use App\Services\PcAccessException;
use App\Services\PcAccessService;
use App\Services\ThumbnailService;
use App\Support\BackupAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Backup untuk aplikasi Android (/api/mobile/backup/*).
 * Aturan akses sama dengan web (App\Support\BackupAccess) dan alur minta
 * akses memakai App\Services\PcAccessService.
 *
 * Aplikasi hanya membaca (lihat, pratinjau, unduh) serta mengelola izin akses;
 * unggah & penataan berkas tetap lewat web.
 */
class MobileBackupController extends Controller
{
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'heic', 'heif'];
    private const VIDEO_EXT = ['mp4', 'mov', 'mkv', 'avi', '3gp', 'webm', 'm4v'];
    private const AUDIO_EXT = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac', 'opus'];
    private const DOC_EXT = ['doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods'];
    private const ARCHIVE_EXT = ['zip', 'rar', '7z', 'tar', 'gz'];

    public function __construct(private readonly PcAccessService $access)
    {
    }

    /** Ringkasan: penyimpanan saya, penyimpanan rekan + status akses, permintaan masuk. */
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        PcAccessRequest::ensureSchema();

        $myPcs = Pc::where('user_id', $user->id)->get();
        $otherPcs = Pc::where('user_id', '!=', $user->id)->with('user:id,name,pangkat,nrp')->orderBy('pc_name')->get();
        $states = BackupAccess::statesFor($user, $otherPcs);
        $myPcIds = $myPcs->pluck('id');

        return response()->json([
            'status' => 'success',
            'is_admin' => BackupAccess::isAdmin($user),
            'my_pcs' => $myPcs->map(fn (Pc $pc) => $this->pcPayload($pc, 'owner'))->values(),
            'other_pcs' => $otherPcs->map(fn (Pc $pc) => $this->pcPayload(
                $pc,
                $states[$pc->id]['state'] ?? 'none',
                $states[$pc->id]['request_id'] ?? null
            ))->values(),
            'incoming_requests' => PcAccessRequest::whereIn('pc_id', $myPcIds)
                ->where('status', PcAccessRequest::STATUS_PENDING)
                ->latest('id')->get()
                ->map(fn ($r) => $this->access->payload($r))->values(),
            'active_grants' => PcAccessRequest::whereIn('pc_id', $myPcIds)
                ->where('status', PcAccessRequest::STATUS_APPROVED)
                ->latest('decided_at')->get()
                ->map(fn ($r) => $this->access->payload($r))->values(),
        ]);
    }

    /** Isi folder (atau hasil pencarian di seluruh folder) sebuah penyimpanan. */
    public function files(Request $request, Pc $pc): JsonResponse
    {
        $user = $request->user();
        if (!BackupAccess::canView($user, $pc)) {
            return $this->denied('Anda belum punya akses ke penyimpanan ini. Minta akses ke pemiliknya terlebih dahulu.');
        }

        $folderId = $request->integer('folder') ?: null;
        $search = trim((string) $request->query('search', ''));

        if ($folderId && !Backup::where('pc_id', $pc->id)->where('id', $folderId)->where('is_folder', true)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Folder tidak ditemukan.'], 404);
        }

        $query = Backup::where('pc_id', $pc->id);
        if ($search !== '') {
            $query->where('file_name', 'like', '%' . addcslashes($search, '%_\\') . '%')->limit(300);
        } else {
            $query->where('parent_id', $folderId);
        }
        $items = $query->orderByDesc('is_folder')->orderBy('file_name')->get();

        $folderIds = $items->where('is_folder', true)->pluck('id');
        $childCounts = $folderIds->isEmpty() ? collect() : Backup::whereIn('parent_id', $folderIds)
            ->selectRaw('parent_id, COUNT(*) as total')->groupBy('parent_id')->pluck('total', 'parent_id');

        $breadcrumbs = [];
        $cursor = $folderId;
        $guard = 0;
        while ($cursor && $guard++ < 50) {
            $folder = Backup::where('pc_id', $pc->id)->find($cursor);
            if (!$folder) {
                break;
            }
            array_unshift($breadcrumbs, ['id' => $folder->id, 'name' => $folder->file_name]);
            $cursor = $folder->parent_id;
        }

        $pc->loadMissing('user:id,name,pangkat,nrp');
        $state = BackupAccess::isOwner($user, $pc) ? 'owner' : (BackupAccess::isAdmin($user) ? 'admin' : 'granted');

        return response()->json([
            'status' => 'success',
            'pc' => $this->pcPayload($pc, $state),
            'can_manage' => BackupAccess::canManage($user, $pc),
            'folder_id' => $folderId,
            'search' => $search !== '' ? $search : null,
            'breadcrumbs' => $breadcrumbs,
            'items' => $items->map(fn (Backup $b) => $this->itemPayload($b, (int) ($childCounts[$b->id] ?? 0)))->values(),
        ]);
    }

    public function thumbnail(Request $request, $id)
    {
        [$backup, $fullPath, $error] = $this->resolveFile($request, $id);
        if ($error) {
            return $error;
        }
        return ThumbnailService::getThumbnailResponse($fullPath, $backup->file_name, $this->isArw($backup));
    }

    /** Isi berkas untuk pratinjau di aplikasi (ARW dikonversi ke JPG). */
    public function content(Request $request, $id)
    {
        [$backup, $fullPath, $error] = $this->resolveFile($request, $id);
        if ($error) {
            return $error;
        }
        if ($this->isArw($backup)) {
            return ArwService::previewResponse($fullPath, $backup->file_name);
        }
        return FileSecurityService::streamDecryptedInline($fullPath, $backup->file_name);
    }

    public function download(Request $request, $id)
    {
        [$backup, $fullPath, $error] = $this->resolveFile($request, $id);
        if ($error) {
            return $error;
        }
        $mime = @mime_content_type($fullPath) ?: 'application/octet-stream';
        return FileSecurityService::streamDecryptedDownload($fullPath, $backup->file_name, $mime);
    }

    // ----------------------------------------------------------- Minta akses

    public function requestAccess(Request $request, Pc $pc): JsonResponse
    {
        $data = $request->json()->all() ?: $request->all();
        $v = Validator::make($data, ['reason' => 'nullable|string|max:500']);
        if ($v->fails()) {
            return response()->json(['status' => 'error', 'message' => $v->errors()->first()], 422);
        }

        return $this->run('Kode verifikasi sudah dikirim ke WhatsApp Anda.',
            fn () => $this->access->request($request->user(), $pc, $data['reason'] ?? null));
    }

    public function resend(Request $request, PcAccessRequest $accessRequest): JsonResponse
    {
        return $this->run('Kode baru sudah dikirim ke WhatsApp Anda.',
            fn () => $this->access->resend($request->user(), $accessRequest));
    }

    public function verify(Request $request, PcAccessRequest $accessRequest): JsonResponse
    {
        $code = (string) (($request->json()->all() ?: $request->all())['code'] ?? '');
        if (trim($code) === '') {
            return response()->json(['status' => 'error', 'message' => 'Masukkan kode dari WhatsApp.'], 422);
        }

        return $this->run('Kode cocok. Permintaan Anda sudah diteruskan ke pemilik penyimpanan.',
            fn () => $this->access->verify($request->user(), $accessRequest, $code));
    }

    public function approve(Request $request, PcAccessRequest $accessRequest): JsonResponse
    {
        $note = ($request->json()->all() ?: $request->all())['note'] ?? null;
        return $this->run('Akses diberikan. Rekan Anda sudah diberi tahu.',
            fn () => $this->access->decide($request->user(), $accessRequest, true, is_string($note) ? $note : null));
    }

    public function reject(Request $request, PcAccessRequest $accessRequest): JsonResponse
    {
        $note = ($request->json()->all() ?: $request->all())['note'] ?? null;
        return $this->run('Permintaan ditolak. Rekan Anda sudah diberi tahu.',
            fn () => $this->access->decide($request->user(), $accessRequest, false, is_string($note) ? $note : null));
    }

    public function withdraw(Request $request, PcAccessRequest $accessRequest): JsonResponse
    {
        $isRequester = (int) $accessRequest->requester_id === (int) $request->user()->id;
        return $this->run($isRequester ? 'Permintaan dibatalkan.' : 'Akses dicabut.',
            fn () => $this->access->withdraw($request->user(), $accessRequest));
    }

    // ----------------------------------------------------------- internal

    private function run(string $message, callable $action): JsonResponse
    {
        try {
            $req = $action();
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'request' => $this->access->payload($req),
            ]);
        } catch (PcAccessException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->status);
        }
    }

    /** @return array{0: ?Backup, 1: ?string, 2: ?\Symfony\Component\HttpFoundation\Response} */
    private function resolveFile(Request $request, $id): array
    {
        $backup = Backup::find((int) $id);
        if (!$backup || $backup->is_folder) {
            return [null, null, response()->json(['status' => 'error', 'message' => 'Berkas tidak ditemukan.'], 404)];
        }
        $pc = Pc::find($backup->pc_id);
        if (!$pc || !BackupAccess::canView($request->user(), $pc)) {
            return [null, null, $this->denied('Anda tidak punya akses ke berkas ini.')];
        }
        $fullPath = FileSecurityService::verifySafeStoragePath($backup->file_path);
        if (!file_exists($fullPath)) {
            return [null, null, response()->json(['status' => 'error', 'message' => 'Berkas fisik tidak ditemukan di server.'], 404)];
        }
        return [$backup, $fullPath, null];
    }

    private function denied(string $message): JsonResponse
    {
        return response()->json(['status' => 'error', 'code' => 'backup_forbidden', 'message' => $message], 403);
    }

    private function isArw(Backup $b): bool
    {
        return ArwService::isArw($b->file_type ?: $b->file_name);
    }

    private function pcPayload(Pc $pc, string $state, ?int $requestId = null): array
    {
        $quota = (int) $pc->max_quota;
        $usage = (int) $pc->current_usage;
        $owner = $pc->relationLoaded('user') ? $pc->user : null;

        return [
            'id' => $pc->id,
            'name' => $pc->pc_name,
            'owner' => $owner ? [
                'id' => $owner->id,
                'name' => $owner->name,
                'pangkat' => $owner->pangkat,
                'nrp' => $owner->nrp,
            ] : null,
            'usage' => $usage,
            'quota' => $quota,
            'usage_human' => $this->formatBytes($usage),
            'quota_human' => $this->formatBytes($quota),
            'usage_percentage' => $quota > 0 ? round($usage / $quota * 100, 1) : 0,
            // owner | admin | granted | pending | verifying | rejected | none
            'access_state' => $state,
            'access_request_id' => $requestId,
        ];
    }

    private function itemPayload(Backup $b, int $childCount): array
    {
        $ext = strtolower(pathinfo((string) $b->file_name, PATHINFO_EXTENSION));
        $kind = match (true) {
            (bool) $b->is_folder => 'folder',
            $this->isArw($b) => 'raw',
            in_array($ext, self::IMAGE_EXT, true) => 'image',
            $ext === 'pdf' => 'pdf',
            in_array($ext, self::VIDEO_EXT, true) => 'video',
            in_array($ext, self::AUDIO_EXT, true) => 'audio',
            in_array($ext, self::DOC_EXT, true) => 'document',
            in_array($ext, self::ARCHIVE_EXT, true) => 'archive',
            default => 'file',
        };

        return [
            'id' => $b->id,
            'name' => $b->file_name,
            'is_folder' => (bool) $b->is_folder,
            'kind' => $kind,
            'extension' => $ext ?: null,
            'size' => (int) $b->file_size,
            'size_human' => $b->is_folder ? null : $this->formatBytes((int) $b->file_size),
            'child_count' => $b->is_folder ? $childCount : null,
            'has_thumbnail' => in_array($kind, ['image', 'raw'], true),
            'updated_at' => $b->updated_at?->toIso8601String(),
            'date_human' => $b->created_at?->format('d M Y H:i'),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = min((int) floor(log($bytes, 1024)), count($units) - 1);
        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }
}
