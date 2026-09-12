<?php

namespace App\Http\Controllers;

use App\Models\Pc;
use App\Models\Backup;
use App\Models\BackupShare;
use App\Services\ArwService;
use App\Services\FileSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Carbon\Carbon;
use ZipArchive;

class BackupShareController extends Controller
{
    /**
     * Menyimpan atau memperbarui konfigurasi tautan berbagi (PIN, Status Aktif)
     */
    public function saveShare(Request $request)
    {
        BackupShare::ensureSchema();

        $validated = $request->validate([
            'pc_id' => 'required|exists:pcs,id',
            'backup_id' => 'nullable|exists:backups,id',
            'pin' => 'required|string|min:4|max:30',
            'is_active' => 'required|boolean',
            'share_name' => 'nullable|string|max:150',
        ]);

        $user = Auth::user();
        $pc = Pc::findOrFail($validated['pc_id']);

        // Verifikasi kepemilikan atau otoritas admin
        $isAdmin = $user->role === 'admin' || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';
        if (!$isAdmin && $pc->user_id !== $user->id) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak otoritas membagikan folder ini.'], 403);
        }

        $share = BackupShare::where('pc_id', $pc->id)
            ->where(function ($q) use ($validated) {
                if (!empty($validated['backup_id'])) {
                    $q->where('backup_id', $validated['backup_id']);
                } else {
                    $q->whereNull('backup_id');
                }
            })
            ->first();

        $defaultName = $validated['share_name'];
        if (empty($defaultName)) {
            if (!empty($validated['backup_id'])) {
                $folder = Backup::find($validated['backup_id']);
                $defaultName = $folder?->file_name ?: 'Folder Berkas';
            } else {
                $defaultName = $pc->pc_name ?: 'Pangkalan Backup';
            }
        }

        if (!$share) {
            $share = new BackupShare([
                'pc_id' => $pc->id,
                'backup_id' => $validated['backup_id'] ?? null,
                'share_token' => Str::random(32),
                'created_by' => $user->id,
            ]);
        }

        $share->pin = trim($validated['pin']);
        $share->is_active = (bool)$validated['is_active'];
        $share->share_name = $defaultName;
        $share->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Tautan berbagi dan PIN keamanan berhasil diperbarui.',
            'share' => $share,
            'share_url' => route('backup.shared.view', $share->share_token),
        ]);
    }

    /**
     * Menonaktifkan atau menghapus tautan berbagi
     */
    public function revokeShare(Request $request, $id)
    {
        $share = BackupShare::findOrFail($id);
        $user = Auth::user();
        $isAdmin = $user->role === 'admin' || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';

        if (!$isAdmin && $share->created_by !== $user->id && $share->pc->user_id !== $user->id) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki otoritas.'], 403);
        }

        $share->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Akses tautan berbagi berhasil dinonaktifkan seketika.',
            'share' => $share,
        ]);
    }

    /**
     * Halaman Akses Berbagi Folder (Google Drive Style)
     * Dapat diakses oleh seluruh personel yang memiliki Tautan & PIN
     */
    public function show($token, Request $request)
    {
        BackupShare::ensureSchema();

        $share = BackupShare::with(['pc', 'folder'])
            ->where('share_token', $token)
            ->first();

        if (!$share) {
            return Inertia::render('Backup/SharedFolder', [
                'isNotFound' => true,
                'shareName' => 'Tautan Tidak Ditemukan',
            ]);
        }

        // Jika tautan telah dinonaktifkan oleh Admin
        if (!$share->is_active) {
            return Inertia::render('Backup/SharedFolder', [
                'isDeactivated' => true,
                'shareName' => $share->share_name,
            ]);
        }

        $sessionKey = 'verified_backup_share_' . $share->id;
        $isVerified = session()->get($sessionKey) === true;

        // Jika belum memasukkan PIN yang benar
        if (!$isVerified) {
            return Inertia::render('Backup/SharedFolder', [
                'needsPin' => true,
                'shareToken' => $token,
                'shareName' => $share->share_name,
                'folderName' => $share->folder?->file_name ?: $share->pc->pc_name,
                'pcName' => $share->pc->pc_name,
                'currentUser' => Auth::user() ? [
                    'id' => Auth::id(),
                    'name' => Auth::user()->name,
                    'pangkat' => Auth::user()->pangkat ?? 'Personel',
                    'nrp' => Auth::user()->nrp ?? Auth::user()->nip ?? null,
                ] : null,
            ]);
        }

        // Menentukan folder aktif dalam cakupan berbagi
        $requestedFolderId = $request->query('folder');
        $currentFolderId = null;

        if ($requestedFolderId) {
            $candidateFolder = Backup::find($requestedFolderId);
            if ($candidateFolder && $candidateFolder->is_folder && $share->isWithinScope($candidateFolder)) {
                $currentFolderId = $candidateFolder->id;
            } else {
                $currentFolderId = $share->backup_id;
            }
        } else {
            $currentFolderId = $share->backup_id;
        }

        // Mengambil daftar isi folder
        $search = trim($request->query('search', ''));
        $query = Backup::where('pc_id', $share->pc_id);

        if ($search) {
            $contents = $query->where('file_name', 'like', '%' . $search . '%')
                ->orderBy('is_folder', 'desc')
                ->get();
        } else {
            $contents = $query->where('parent_id', $currentFolderId)
                ->orderBy('is_folder', 'desc')
                ->orderBy('file_name', 'asc')
                ->get();
        }

        // Saring secara ketat agar hanya item di dalam lingkup share yang dimunculkan
        $filteredContents = $contents->filter(fn($item) => $share->isWithinScope($item))->values();

        $mappedContents = $filteredContents->map(function ($item) use ($token) {
            $itemArray = $item->toArray();
            $itemArray['size_human'] = !$item->is_folder ? $this->formatBytes($item->file_size) : '--';
            $itemArray['date_human'] = Carbon::parse($item->updated_at ?: $item->created_at)->format('d M Y H:i');
            $itemArray['download_url'] = !$item->is_folder ? route('backup.shared.download', ['token' => $token, 'fileId' => $item->id]) : null;
            $isArw = ArwService::isArw($item->file_type ?: $item->file_name);
            $itemArray['is_arw'] = $isArw;
            $itemArray['preview_url'] = !$item->is_folder ? route('backup.shared.preview', ['token' => $token, 'fileId' => $item->id]) : null;
            $itemArray['download_jpg_url'] = $isArw ? route('backup.shared.download-arw-jpg', ['token' => $token, 'fileId' => $item->id]) : null;
            $itemArray['is_secured'] = true;
            return $itemArray;
        });

        // Susun Breadcrumb Terbatas (tidak melewati batas atas folder yang dibagikan)
        $breadcrumbs = [];
        $tempFolderId = $currentFolderId;
        $maxDepth = 20;
        $depth = 0;

        while ($tempFolderId && $depth < $maxDepth) {
            if ($share->backup_id && (int)$tempFolderId === (int)$share->backup_id) {
                $folder = Backup::find($tempFolderId);
                array_unshift($breadcrumbs, [
                    'id' => $folder->id,
                    'name' => $folder->file_name,
                ]);
                break;
            }

            $folder = Backup::find($tempFolderId);
            if ($folder) {
                array_unshift($breadcrumbs, [
                    'id' => $folder->id,
                    'name' => $folder->file_name,
                ]);
                $tempFolderId = $folder->parent_id;
            } else {
                break;
            }
            $depth++;
        }

        return Inertia::render('Backup/SharedFolder', [
            'needsPin' => false,
            'isDeactivated' => false,
            'shareToken' => $token,
            'shareName' => $share->share_name,
            'pcName' => $share->pc->pc_name,
            'contents' => $mappedContents,
            'currentFolderId' => $currentFolderId,
            'shareRootFolderId' => $share->backup_id,
            'breadcrumbs' => $breadcrumbs,
            'searchQuery' => $search,
            'currentUser' => Auth::user() ? [
                'id' => Auth::id(),
                'name' => Auth::user()->name,
                'pangkat' => Auth::user()->pangkat ?? 'Personel',
                'nrp' => Auth::user()->nrp ?? Auth::user()->nip ?? null,
            ] : null,
        ]);
    }

    /**
     * Memverifikasi PIN Keamanan yang dimasukkan personel (dengan Rate-Limiting)
     */
    public function verifyPin($token, Request $request)
    {
        $request->validate([
            'pin' => 'required|string',
        ]);

        $share = BackupShare::where('share_token', $token)->first();

        if (!$share || !$share->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tautan tidak valid atau telah dinonaktifkan oleh Administrator.',
            ], 403);
        }

        $throttleKey = 'backup_share_pin_' . $share->id . '_' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'status' => 'error',
                'message' => "Terlalu banyak percobaan salah! Silakan tunggu {$seconds} detik lagi.",
            ], 429);
        }

        if (!$share->verifyPin($request->pin)) {
            RateLimiter::hit($throttleKey, 300); // Kunci 5 menit jika gagal 5x
            $remaining = RateLimiter::remaining($throttleKey, 5);

            return response()->json([
                'status' => 'error',
                'message' => "PIN keamanan tidak sesuai! Sisa percobaan: {$remaining} kali.",
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        // Catat keberhasilan verifikasi ke dalam session
        session()->put('verified_backup_share_' . $share->id, true);

        // Catat log audit personel yang mengakses
        $user = Auth::user();
        if ($user) {
            Log::info("Personel {$user->name} (" . ($user->nrp ?? $user->id) . ") berhasil memverifikasi PIN folder share ID {$share->id}");
        }

        // Perbarui statistik akses
        $share->increment('access_count');
        $share->update(['last_accessed_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'PIN terverifikasi. Membuka folder...',
        ]);
    }

    /**
     * Keluar dari sesi verifikasi PIN (Kunci Kembali Folder)
     */
    public function exitShare($token)
    {
        $share = BackupShare::where('share_token', $token)->first();
        if ($share) {
            session()->forget('verified_backup_share_' . $share->id);
        }

        return redirect()->route('backup.shared.view', $token);
    }

    /**
     * Unduh Berkas Tunggal secara Aman (Terbatas dalam Lingkup Folder)
     */
    public function downloadFile($token, $fileId)
    {
        $share = BackupShare::where('share_token', $token)->where('is_active', true)->firstOrFail();

        $sessionKey = 'verified_backup_share_' . $share->id;
        if (session()->get($sessionKey) !== true) {
            abort(403, 'Otoritas PIN keamanan belum terverifikasi.');
        }

        $file = Backup::where('id', $fileId)->where('pc_id', $share->pc_id)->firstOrFail();

        if ($file->is_folder) {
            abort(400, 'Folder tidak dapat diunduh langsung.');
        }

        if (!$share->isWithinScope($file)) {
            abort(403, 'Akses ditolak: Berkas berada di luar cakupan folder yang dibagikan.');
        }

        $fullPath = FileSecurityService::verifySafeStoragePath($file->file_path);
        if (!file_exists($fullPath)) {
            Log::warning("Shared file download not found: {$fullPath}");
            abort(404, 'Berkas fisik tidak ditemukan di server penyimpanan.');
        }

        $mimeType = @mime_content_type($fullPath) ?: 'application/octet-stream';
        return FileSecurityService::streamDecryptedDownload($fullPath, $file->file_name, $mimeType);
    }

    /**
     * Pratinjau Berkas (Gambar, PDF, dsb.) secara Aman
     */
    public function previewFile($token, $fileId)
    {
        if (request()->header('Sec-Fetch-Dest') === 'document' || request()->header('Sec-Fetch-Mode') === 'navigate') {
            abort(403, 'Akses Ditolak: Pratinjau berkas hanya diizinkan melalui antarmuka aplikasi internal.');
        }

        $share = BackupShare::where('share_token', $token)->where('is_active', true)->firstOrFail();

        $sessionKey = 'verified_backup_share_' . $share->id;
        if (session()->get($sessionKey) !== true) {
            abort(403, 'Otoritas PIN keamanan belum terverifikasi.');
        }

        $file = Backup::where('id', $fileId)->where('pc_id', $share->pc_id)->firstOrFail();

        if ($file->is_folder || !$share->isWithinScope($file)) {
            abort(403, 'Akses ditolak.');
        }

        $fullPath = FileSecurityService::verifySafeStoragePath($file->file_path);
        if (!file_exists($fullPath)) {
            abort(404, 'Berkas fisik tidak ditemukan.');
        }

        // Dukungan khusus format Sony RAW (.ARW) -> Pratinjau sebagai JPG kualitas tinggi
        if (ArwService::isArw($file->file_type ?: $file->file_name)) {
            return ArwService::previewResponse($fullPath, $file->file_name);
        }

        return FileSecurityService::streamDecryptedInline($fullPath, $file->file_name);
    }

    /**
     * Unduh Berkas Sony RAW (.ARW) Terkonversi sebagai Format JPG HD dalam Shared Folder
     */
    public function downloadArwJpg($token, $fileId)
    {
        $share = BackupShare::where('share_token', $token)->where('is_active', true)->firstOrFail();

        $sessionKey = 'verified_backup_share_' . $share->id;
        if (session()->get($sessionKey) !== true) {
            abort(403, 'Otoritas PIN keamanan belum terverifikasi.');
        }

        $file = Backup::where('id', $fileId)->where('pc_id', $share->pc_id)->firstOrFail();

        if ($file->is_folder || !$share->isWithinScope($file)) {
            abort(403, 'Akses ditolak: Berkas berada di luar cakupan.');
        }

        if (!ArwService::isArw($file->file_type ?: $file->file_name)) {
            abort(400, 'Berkas bukan format Sony RAW (.ARW).');
        }

        FileSecurityService::verifySafeStoragePath($file->file_path);

        return ArwService::downloadConvertedJpg($file);
    }

    /**
     * Fitur Tambahan Google Drive: Unduh Seluruh Isi Folder Menjadi Paket ZIP
     */
    public function downloadFolderZip($token, Request $request)
    {
        $share = BackupShare::where('share_token', $token)->where('is_active', true)->firstOrFail();

        $sessionKey = 'verified_backup_share_' . $share->id;
        if (session()->get($sessionKey) !== true) {
            abort(403, 'Otoritas PIN keamanan belum terverifikasi.');
        }

        $targetFolderId = $request->query('folder_id') ?: $share->backup_id;
        $targetFolder = null;

        if ($targetFolderId) {
            $targetFolder = Backup::findOrFail($targetFolderId);
            if (!$share->isWithinScope($targetFolder)) {
                abort(403, 'Folder berada di luar cakupan.');
            }
        }

        // Ambil seluruh berkas non-folder di dalam target folder
        $files = Backup::where('pc_id', $share->pc_id)
            ->where('parent_id', $targetFolderId)
            ->where('is_folder', false)
            ->get();

        if ($files->isEmpty()) {
            return back()->with('error', 'Tidak ada berkas yang dapat dikompresi di folder ini.');
        }

        $folderTitle = $targetFolder ? $targetFolder->file_name : ($share->share_name ?: 'BERKAS_BAGIKAN');
        $zipFileName = 'UNDUHAN_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $folderTitle) . '_' . date('Ymd_His') . '.zip';
        $zipTempPath = storage_path('app/public/temp_' . $zipFileName);

        $zip = new ZipArchive();
        $createdTempFiles = [];

        if ($zip->open($zipTempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($files as $file) {
                $fullPath = FileSecurityService::verifySafeStoragePath($file->file_path);
                if (file_exists($fullPath)) {
                    if (FileSecurityService::isEncrypted($fullPath)) {
                        $tmp = FileSecurityService::createDecryptedTempFile($fullPath);
                        if ($tmp) {
                            $createdTempFiles[] = $tmp;
                            $zip->addFile($tmp, $file->file_name);
                        }
                    } else {
                        $zip->addFile($fullPath, $file->file_name);
                    }
                }
            }
            $zip->close();

            // Bersihkan file sementara yang dibuat untuk zip
            foreach ($createdTempFiles as $tmp) {
                @unlink($tmp);
            }

            if (file_exists($zipTempPath)) {
                return response()->download($zipTempPath, $zipFileName)->deleteFileAfterSend(true);
            }
        }

        abort(500, 'Gagal mengompresi berkas ke dalam ZIP.');
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) $bytes /= 1024;
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
