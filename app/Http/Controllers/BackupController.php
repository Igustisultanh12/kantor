<?php

namespace App\Http\Controllers;

use App\Models\Pc;
use App\Models\Backup;
use App\Models\AccessRequest; 
use App\Models\OfficeNetwork;
use App\Models\User;
use App\Models\BackupShare;
use App\Services\ArwService;
use App\Services\FileSecurityService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache; 
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BackupController extends Controller
{
    /**
     * RADAR PUSAT & DASHBOARD BACKUP
     */
    public function index()
    {
        $user = Auth::user();
        // Kalibrasi Nama Komandan agar Bypass Terdeteksi
        $isAdmin = $user->role === 'admin' || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';

        return Inertia::render('Backup/Index', [
            'myPcs' => Pc::where('user_id', $user->id)->get()->map(fn($pc) => $this->formatPcData($pc)),
            'publicPcs' => Pc::where('user_id', '!=', $user->id)->with('user:id,name,pangkat,nrp')->get()->map(fn($pc) => $this->formatPcData($pc)),
            
            'authRequest' => AccessRequest::where('user_id', $user->id)->latest()->first(),
            
            // Mengambil antrean yang statusnya pending atau approved (belum completed)
            'pendingRequests' => $isAdmin ? AccessRequest::with('user')->where('status', '!=', 'completed')->latest()->get() : [],
            
            'networks' => $isAdmin ? OfficeNetwork::latest()->get() : [],
            'globalStats' => $isAdmin ? [
                'total_pcs' => Pc::count(),
                'total_storage' => $this->formatBytes(Backup::sum('file_size')),
            ] : null,
            'allUsers' => $isAdmin ? User::select('id', 'name', 'pangkat', 'nrp')->orderBy('name')->get() : [],
            'isAdmin' => $isAdmin
        ]);
    }

    /**
     * ALUR 1: PENGAJUAN AKSES OLEH PERSONEL
     * BYPASS TOTAL: Bebas dari dependensi Facade Str
     */
    public function requestAccess(Request $request)
    {
        $user = Auth::user();
        if (Pc::where('user_id', $user->id)->exists()) {
            return back()->with('error', 'Status aktif.');
        }
        $existing = AccessRequest::where('user_id', $user->id)->where('status', 'pending')->first();
        if ($existing) return back()->with('info', ' Pengajuan anda masih dalam antrian.');

        // Algoritma Acak Native PHP - Kebal Terhadap Kerusakan Autoloader Framework
        $pool = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomString = substr(str_shuffle($pool), 0, 5);

        AccessRequest::create([
            'user_id' => $user->id,
            'pc_name' => 'PC-' . $randomString,
            'status' => 'pending'
        ]);

        return back()->with('success', 'Pengajuan akses telah dikirim.');
    }

    /**
     * ALUR 2: PERSETUJUAN OLEH ADMIN (DENGAN KODE VISUAL VIA PORT 3000)
     */
    public function approveAccess($id)
    {
        if (Auth::user()->role !== 'admin' && Auth::user()->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            return abort(403, 'Anda tidak memiliki otoritas menyetujui.');
        }

        $access = AccessRequest::with('user')->findOrFail($id);
        $code = (string) rand(100000, 999999);
        
        $access->update([
            'status' => 'approved',
            'verification_code' => $code,
            'expires_at' => now()->addMinutes(30)
        ]);

        try {
            Http::timeout(5)->post('http://localhost:3000/send-message', [
                'number' => $access->user->whatsapp_number ?? $access->user->phone ?? $access->user->identity_number,
                'message' => "SINDEN RADAR:\nPengajuan akses disetujui.\n\nKODE AKSES: *{$code}*\nBerlaku: 30 Menit.\n\nSegera masukkan kode di Dashboard."
            ]);
        } catch (\Exception $e) { }

        return back()->with([
            'success' => 'Otoritas diberikan! Kode dikirim via WhatsApp.',
            'generatedCode' => $code 
        ]);
    }

    /**
     * ALUR 3: VERIFIKASI KODE & PEMBUATAN PC OTOMATIS
     */
    public function verifyAccess(Request $request)
    {
        $request->validate(['code' => 'required']);
        $user = Auth::user();
        $access = AccessRequest::where('user_id', $user->id)->where('verification_code', $request->code)->where('status', 'approved')->where('expires_at', '>', now())->first();

        if (!$access) return back()->with('error', 'Kode salah atau masa berlaku (30 Menit) telah habis!');

        $pool = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomHw = substr(str_shuffle($pool), 0, 8);

        Pc::create([
            'user_id' => $user->id,
            'pc_name' => 'PC ' . $user->name,
            'hardware_id' => 'HW-' . $randomHw,
            'current_usage' => 0,
            'max_quota' => 214748364800 // Kunci Jatah Kuota 200GB
        ]);
        
        $access->update(['status' => 'completed']);
        return back()->with('success', 'Jatah PC 200GB berhasil Diberikan');
    }

    /**
     * FEATURE: DIGITAL FILE EXPLORER (DENGAN RADAR PENCARIAN GLOBAL)
     */
    public function explore($id, Request $request)
    {
        $pc = Pc::with('user')->findOrFail($id);
        $parentId = $request->query('folder'); 
        $search = $request->query('search'); 

        $query = Backup::where('pc_id', $id);

        if ($search) {
            $contents = $query->where('file_name', 'like', '%' . $search . '%')
                              ->orderBy('is_folder', 'desc')
                              ->get();
        } else {
            $contents = $query->where('parent_id', $parentId)
                              ->orderBy('is_folder', 'desc')
                              ->orderBy('file_name', 'asc')
                              ->get();
        }

        BackupShare::ensureSchema();

        $folderShares = BackupShare::where('pc_id', $id)
            ->whereNotNull('backup_id')
            ->get()
            ->keyBy('backup_id');

        $contents = $contents->map(function($item) use ($folderShares) {
            if ($item->is_folder) {
                $totalSize = $this->getFolderSize($item->id);
                $item->size_human = $totalSize > 0 ? $this->formatBytes($totalSize) : '0 B';
                $item->file_size = $totalSize;

                $share = $folderShares->get($item->id);
                $item->share_info = $share ? [
                    'id' => $share->id,
                    'is_active' => (bool)$share->is_active,
                    'share_token' => $share->share_token,
                    'pin' => $share->pin,
                    'share_url' => route('backup.shared.view', $share->share_token),
                    'access_count' => $share->access_count,
                    'last_accessed_at' => $share->last_accessed_at ? Carbon::parse($share->last_accessed_at)->format('d M Y H:i') : null,
                ] : null;
            } else {
                $item->size_human = $this->formatBytes($item->file_size);
                $item->share_info = null;
            }
            
            $item->date_human = Carbon::parse($item->created_at)->format('d M Y H:i');
            $isArw = ArwService::isArw($item->file_type ?: $item->file_name);
            $item->is_arw = $isArw;
            if ($isArw) {
                $item->preview_url = route('backup.preview-arw', $item->id);
                $item->download_jpg_url = route('backup.download-arw-jpg', $item->id);
            } else {
                $item->preview_url = !$item->is_folder ? route('backup.preview-file', $item->id) : null;
                $item->download_jpg_url = null;
            }
            $item->is_secured = true;
            return $item;
        });

        $breadcrumbs = [];
        $tempFolderId = $parentId;

        while ($tempFolderId) {
            $folder = Backup::find($tempFolderId);
            if ($folder) {
                array_unshift($breadcrumbs, [
                    'name' => $folder->file_name,
                    'id' => $folder->id
                ]);
                $tempFolderId = $folder->parent_id;
            } else {
                $tempFolderId = null;
            }
        }

        $currentShare = BackupShare::where('pc_id', $id)
            ->where(function ($q) use ($parentId) {
                if ($parentId) {
                    $q->where('backup_id', $parentId);
                } else {
                    $q->whereNull('backup_id');
                }
            })->first();

        $currentShareInfo = $currentShare ? [
            'id' => $currentShare->id,
            'is_active' => (bool)$currentShare->is_active,
            'share_token' => $currentShare->share_token,
            'pin' => $currentShare->pin,
            'share_url' => route('backup.shared.view', $currentShare->share_token),
            'access_count' => $currentShare->access_count,
            'last_accessed_at' => $currentShare->last_accessed_at ? Carbon::parse($currentShare->last_accessed_at)->format('d M Y H:i') : null,
        ] : null;

        return Inertia::render('Backup/Explore', [
            'pc' => $this->formatPcData($pc),
            'contents' => $contents,
            'currentFolderId' => $parentId,
            'breadcrumbs' => $breadcrumbs,
            'searchQuery' => $search,
            'currentShare' => $currentShareInfo,
            'isAdmin' => Auth::user()->role === 'admin' || Auth::user()->name === 'I Gusti Sultan H.A, A.Md.Kom',
        ]);
    }

    /**
     * PROTOKOL REKURSIF: Hitung total file di dalam folder dan sub-foldernya
     */
    private function getFolderSize($folderId)
    {
        $total = 0;
        $items = Backup::where('parent_id', $folderId)->get();

        foreach ($items as $item) {
            if ($item->is_folder) {
                $total += $this->getFolderSize($item->id);
            } else {
                $total += $item->file_size;
            }
        }
        return $total;
    }

    /**
     * OPERASI KONVERSI OFFICE KE PDF (LIBREOFFICE LOKAL HEADLESS)
     */
    public function viewOffice($id)
    {
        $item = Backup::findOrFail($id);
        $filePath = storage_path('app/public/' . $item->file_path);
        $outputDir = storage_path('app/public/temp_pdf/');

        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $pdfName = pathinfo($item->file_name, PATHINFO_FILENAME) . '_' . time() . '.pdf';
        $command = "libreoffice --headless --convert-to pdf --outdir " . escapeshellarg($outputDir) . " " . escapeshellarg($filePath);
        shell_exec($command);

        $originalName = pathinfo($item->file_name, PATHINFO_FILENAME);
        $expectedPdfPath = $outputDir . $originalName . '.pdf';
        $finalPdfPath = $outputDir . $pdfName;

        if (file_exists($expectedPdfPath)) {
            rename($expectedPdfPath, $finalPdfPath);
            return response()->file($finalPdfPath)->deleteFileAfterSend(true);
        }

        return abort(404, "Gagal mengonversi dokumen.");
    }

    /**
     * PRATINJAU DOKUMEN / MEDIA TEROTENTIKASI (STREAMING DEKRIPSI ON-THE-FLY)
     */
    public function previewFile($id)
    {
        $backup = Backup::findOrFail($id);

        if ($backup->is_folder) {
            abort(400, 'Folder tidak dapat dipratinjau.');
        }

        $fullPath = FileSecurityService::verifySafeStoragePath($backup->file_path);
        if (!file_exists($fullPath)) {
            abort(404, 'Berkas fisik tidak ditemukan di server.');
        }

        if (ArwService::isArw($backup->file_type ?: $backup->file_name)) {
            return ArwService::previewResponse($fullPath, $backup->file_name);
        }

        return FileSecurityService::streamDecryptedInline($fullPath, $backup->file_name);
    }

    /**
     * PRATINJAU FORMAT GAMBAR SONY RAW (.ARW) SEBAGAI JPG HD
     */
    public function previewArw($id)
    {
        $backup = Backup::findOrFail($id);
        $fullPath = FileSecurityService::verifySafeStoragePath($backup->file_path);

        if (!file_exists($fullPath)) {
            abort(404, 'Berkas fisik Sony RAW tidak ditemukan di server penyimpanan.');
        }

        return ArwService::previewResponse($fullPath, $backup->file_name);
    }

    /**
     * KONVERSI SONY RAW (.ARW) MENJADI BERKAS .JPG KUALITAS TINGGI DAN SIMPAN DI FOLDER
     */
    public function convertArw(Request $request, $id)
    {
        try {
            $backup = Backup::findOrFail($id);

            if (!ArwService::isArw($backup->file_type ?: $backup->file_name)) {
                return response()->json(['status' => 'error', 'message' => 'Berkas bukan format Sony RAW (.ARW).'], 400);
            }

            $newBackup = ArwService::convertAndSaveToBackup($backup);

            if (!$newBackup) {
                return response()->json(['status' => 'error', 'message' => 'Gagal mengonversi berkas Sony RAW (.ARW) ke JPG.'], 500);
            }

            $newBackup->size_human = $this->formatBytes($newBackup->file_size);
            $newBackup->date_human = Carbon::parse($newBackup->created_at)->format('d M Y H:i');
            $newBackup->preview_url = asset('storage/' . $newBackup->file_path);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'message' => "Berkas {$backup->file_name} berhasil dikonversi menjadi {$newBackup->file_name}.",
                    'item' => $newBackup
                ]);
            }

            return back()->with('success', "Berkas berhasil dikonversi menjadi {$newBackup->file_name}.");
        } catch (\Exception $e) {
            Log::error("Gagal convert ARW ID {$id}: " . $e->getMessage());
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * UNDUH LANGSUNG BERKAS SONY RAW (.ARW) SEBAGAI FORMAT .JPG KUALITAS TINGGI
     */
    public function downloadArwJpg($id)
    {
        $backup = Backup::findOrFail($id);

        if (!ArwService::isArw($backup->file_type ?: $backup->file_name)) {
            abort(400, 'Berkas bukan format Sony RAW (.ARW).');
        }

        return ArwService::downloadConvertedJpg($backup);
    }

    /**
     * FEATURE: BUAT FOLDER BARU
     */
    public function createFolder(Request $request)
    {
        $request->validate([
            'pc_id' => 'required',
            'folder_name' => 'required|string|max:100',
            'parent_id' => 'nullable'
        ]);

        Backup::create([
            'pc_id' => $request->pc_id,
            'parent_id' => $request->parent_id,
            'file_name' => strtoupper($request->folder_name),
            'is_folder' => true,      
            'file_path' => 'folder', 
            'file_size' => 0,
            'file_type' => 'folder'
        ]);

        return back()->with('success', 'Folder berhasil dibuat.');
    }

    /**
     * FEATURE: RENAME BERKAS/FOLDER
     */
    public function rename(Request $request, $id)
    {
        $request->validate(['new_name' => 'required|string|max:255']);
        $item = Backup::findOrFail($id);
        $name = $item->is_folder ? strtoupper($request->new_name) : $request->new_name;
        $item->update(['file_name' => $name]);
        return back()->with('success', 'Identitas berkas berhasil diperbarui.');
    }

    /**
     * FEATURE: EXTRAK ZIP STANDARD
     */
    public function extract($id)
    {
        $backup = Backup::findOrFail($id);
        $fullPath = storage_path('app/public/' . $backup->file_path);

        if ($backup->file_type !== 'zip') {
            return back()->with('error', 'Hanya berkas berekstensi ZIP yang dapat didekripsi.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($fullPath) === TRUE) {
            $folderName = 'EXTRACTED_' . strtoupper(pathinfo($backup->file_name, PATHINFO_FILENAME));
            $extractSubPath = 'backups/' . $backup->pc_id . '/' . time() . '_' . $folderName;
            
            $zip->extractTo(storage_path('app/public/' . $extractSubPath));
            $zip->close();

            Backup::create([
                'pc_id' => $backup->pc_id,
                'parent_id' => $backup->parent_id,
                'file_name' => $folderName,
                'is_folder' => true,
                'file_path' => $extractSubPath,
                'file_size' => 0,
                'file_type' => 'folder'
            ]);

            return back()->with('success', 'Berhasil di Ekstrak.');
        }
        return back()->with('error', 'Gagal membuka paket ZIP.');
    }

    /**
     * FEATURE: EKSTRAKSI CERDAS (STRUKTUR BERLAPIS & LIVE LOG PROCESS)
     */
    public function startExtract(Request $request, $id)
    {
        $backup = Backup::findOrFail($id);
        $user = Auth::user();
        $cacheKey = 'extract_progress_' . $user->id;
        $cancelKey = 'extract_cancel_' . $user->id;
        $logKey = 'extract_log_' . $user->id;

        Cache::put($cacheKey, 0, 600);
        Cache::put($logKey, 'Menyiapkan ...', 600);
        Cache::forget($cancelKey);

        $zipFile = storage_path('app/public/' . $backup->file_path);
        $destinationFolder = $request->destination ?? 'EXTRACTED_' . time();
        $extractSubPath = 'backups/' . $backup->pc_id . '/' . $destinationFolder;
        $fullExtractPath = storage_path('app/public/' . $extractSubPath);

        if (!file_exists($fullExtractPath)) {
            mkdir($fullExtractPath, 0777, true);
        }

        $zip = new \ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            $totalFiles = $zip->numFiles;

            $rootFolder = Backup::create([
                'pc_id' => $backup->pc_id,
                'parent_id' => $backup->parent_id,
                'file_name' => strtoupper($destinationFolder),
                'is_folder' => true,
                'file_path' => $extractSubPath,
                'file_size' => 0,
                'file_type' => 'folder'
            ]);

            $createdFolders = [];

            for ($i = 0; $i < $totalFiles; $i++) {
                if (Cache::has($cancelKey)) {
                    $zip->close();
                    return response()->json(['status' => 'cancelled']);
                }

                $fullZipEntryName = $zip->getNameIndex($i); 
                $zip->extractTo($fullExtractPath, array($fullZipEntryName));

                Cache::put($logKey, "Mengekstrak: " . basename($fullZipEntryName), 600);

                $pathParts = explode('/', rtrim($fullZipEntryName, '/'));
                $currentParentId = $rootFolder->id;
                $cumulativePath = $extractSubPath;

                foreach ($pathParts as $index => $part) {
                    $isLastPart = ($index === count($pathParts) - 1);
                    $cumulativePath .= '/' . $part;
                    $pathKey = implode('/', array_slice($pathParts, 0, $index + 1));

                    if (!$isLastPart || substr($fullZipEntryName, -1) === '/') {
                        if (!isset($createdFolders[$pathKey])) {
                            $newFolder = Backup::create([
                                'pc_id' => $backup->pc_id,
                                'parent_id' => $currentParentId,
                                'file_name' => strtoupper($part),
                                'is_folder' => true,
                                'file_path' => $cumulativePath,
                                'file_size' => 0,
                                'file_type' => 'folder'
                            ]);
                            $createdFolders[$pathKey] = $newFolder->id;
                        }
                        $currentParentId = $createdFolders[$pathKey];
                    } 
                    else {
                        Backup::create([
                            'pc_id' => $backup->pc_id,
                            'parent_id' => $currentParentId,
                            'file_name' => $part,
                            'file_path' => $cumulativePath,
                            'file_size' => file_exists($fullExtractPath . '/' . $fullZipEntryName) ? filesize($fullExtractPath . '/' . $fullZipEntryName) : 0,
                            'is_folder' => false,
                            'file_type' => pathinfo($part, PATHINFO_EXTENSION),
                        ]);
                    }
                }

                $progress = round((($i + 1) / $totalFiles) * 100);
                Cache::put($cacheKey, $progress, 600);
            }

            $zip->close();
            Cache::put($logKey, "Operasi Selesai.", 600);
            return response()->json(['status' => 'success', 'message' => 'Logistik dibongkar sesuai formasi.']);
        }

        return response()->json(['status' => 'error', 'message' => 'Gagal membuka paket.'], 500);
    }

    /**
     * RADAR PROGRESS & LOG EKSTRAKSI
     */
    public function getExtractProgress()
    {
        return response()->json([
            'progress' => Cache::get('extract_progress_' . Auth::id(), 0),
            'log' => Cache::get('extract_log_' . Auth::id(), 'Menunggu radar...')
        ]);
    }

    /**
     * OPERASI PEMBATALAN EKSTRAKSI
     */
    public function cancelExtract()
    {
        Cache::put('extract_cancel_' . Auth::id(), true, 60);
        return response()->json(['message' => 'Sinyal pembatalan dikirim...']);
    }

    /**
     * UPLOAD BERKAS KE STORAGE (MAX 20GB, RESPON DUKUNG JSON & INERTIA)
     */
    public function store(Request $request)
    {
        $request->validate([
            'pc_id' => 'required|exists:pcs,id', 
            'file' => 'required|file|max:20971520',
            'parent_id' => 'nullable'
        ]);

        $pc = Pc::findOrFail($request->pc_id);
        $uploadedFile = $request->file('file');
        $fileSize = $uploadedFile->getSize();
        $cleanFileName = $uploadedFile->getClientOriginalName();

        if (($pc->current_usage + $fileSize) > $pc->max_quota) {
            $msg = 'Penyimpanan Penuh! Kapasitas ' . $this->formatBytes($pc->max_quota) . ' terlampaui.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        // VALIDASI KEAMANAN TINGKAT TINGGI: Anti-Malware, Magic Bytes, Double Extension, Anti-Polyglot
        try {
            $remainingQuota = $pc->max_quota - $pc->current_usage;
            FileSecurityService::validateFileSafety($uploadedFile->getRealPath(), $cleanFileName, $remainingQuota);
        } catch (\Exception $e) {
            Log::warning("Penyusupan berkas diblokir pada PC ID {$pc->id}: " . $e->getMessage());
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }

        $uniquePrefix = time() . '_' . substr(uniqid(), -6);
        $relativeSubPath = 'backups/' . $pc->id . '/' . $uniquePrefix . '_' . $cleanFileName;
        $destFullPath = FileSecurityService::verifySafeStoragePath($relativeSubPath);

        // Enkripsi berkas fisik pada level penyimpanan disk secara streaming aman
        FileSecurityService::encryptAndStoreFile($uploadedFile->getRealPath(), $destFullPath, $remainingQuota);

        $backup = Backup::create([
            'pc_id' => $pc->id,
            'parent_id' => $request->parent_id, 
            'file_name' => $cleanFileName,
            'file_path' => $relativeSubPath,
            'file_size' => $fileSize,
            'is_folder' => false,
            'file_type' => $uploadedFile->getClientOriginalExtension(),
        ]);

        $pc->increment('current_usage', $fileSize);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Berkas ' . $cleanFileName . ' berhasil diamankan.',
                'item' => [
                    'id' => $backup->id,
                    'file_name' => $backup->file_name,
                    'file_size' => $backup->file_size,
                    'size_human' => $this->formatBytes($backup->file_size),
                    'date_human' => Carbon::now()->format('d M Y H:i'),
                ],
                'pc' => [
                    'current_usage' => $pc->current_usage,
                    'usage_percentage' => round(($pc->current_usage / $pc->max_quota) * 100, 2),
                ]
            ]);
        }

        return back()->with('success', 'Berkas berhasil diamankan.');
    }

    public function download($id)
    {
        try {
            $backup = Backup::findOrFail($id);

            if ($backup->is_folder) {
                return back()->with('error', 'Folder tidak bisa diunduh langsung.');
            }

            $fullPath = FileSecurityService::verifySafeStoragePath($backup->file_path);

            if (!file_exists($fullPath)) {
                Log::warning("File backup ID {$id} tidak ditemukan di path: {$fullPath}");
                return back()->with('error', 'Berkas fisik tidak ditemukan di server penyimpanan. Berkas mungkin belum diunggah sempurna atau telah dipindahkan.');
            }

            $mimeType = @mime_content_type($fullPath) ?: 'application/octet-stream';
            return FileSecurityService::streamDecryptedDownload($fullPath, $backup->file_name, $mimeType);
        } catch (\Exception $e) {
            Log::error("Gagal unduh berkas backup ID {$id}: " . $e->getMessage());
            return back()->with('error', 'Gagal mengunduh berkas: ' . $e->getMessage());
        }
    }

    public function destroyBackup($id)
    {
        $backup = Backup::findOrFail($id);
        $pc = Pc::findOrFail($backup->pc_id);

        if (!$backup->is_folder && Storage::disk('public')->exists($backup->file_path)) {
            Storage::disk('public')->delete($backup->file_path);
        }

        $pc->decrement('current_usage', $backup->file_size);
        $backup->delete();
        return back()->with('success', 'Data berhasil Dihapus.');
    }

    /**
     * MANAJEMEN RADAR IP
     */
    public function storeNetwork(Request $request) 
    {
        $request->validate(['location_name' => 'required', 'ip_address' => 'required']);
        OfficeNetwork::create($request->all());
        return back()->with('success', 'IP Baru telah diaktifkan!');
    }

    public function destroyNetwork($id) 
    {
        OfficeNetwork::destroy($id);
        return back()->with('success', 'IP telah dinonaktifkan.');
    }

        /**
     * OPERASI KHUSUS ADMIN: HAPUS AKSES & SELURUH BERKAS PC PERMANEN
     */
    public function revokePc($id)
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            return abort(403, 'Anda tidak memiliki otoritas menghapus akses PC.');
        }

        $pc = Pc::findOrFail($id);

        // 1. Hapus seluruh berkas fisik dari storage
        $backups = Backup::where('pc_id', $pc->id)->get();
        foreach ($backups as $backup) {
            if (!$backup->is_folder && !empty($backup->file_path) && Storage::disk('public')->exists($backup->file_path)) {
                Storage::disk('public')->delete($backup->file_path);
            }
        }

        // 2. Hapus seluruh direktori penyimpanan PC di storage/backups/{pc_id}
        if (Storage::disk('public')->exists('backups/' . $pc->id)) {
            Storage::disk('public')->deleteDirectory('backups/' . $pc->id);
        }

        // 3. Hapus seluruh record data Backup dari database
        Backup::where('pc_id', $pc->id)->delete();

        // 4. Hapus pengajuan akses personel (AccessRequest) agar personel bisa mengajukan ulang jika perlu
        AccessRequest::where('user_id', $pc->user_id)->delete();

        // 5. Hapus record PC
        $pc->delete();

        return back()->with('success', 'Otoritas akses dan seluruh berkas PC berhasil dihapus secara permanen.');
    }

    /**
     * FITUR: SIMPAN PERUBAHAN ISI BERKAS EXCEL (.XLSX / .XLS / .CSV)
     */
    public function saveExcel(Request $request, $id)
    {
        $request->validate([
            'base64_content' => 'required|string',
        ]);

        try {
            $backup = Backup::findOrFail($id);

            if ($backup->is_folder) {
                return response()->json(['status' => 'error', 'message' => 'Folder tidak dapat diedit sebagai spreadsheet.'], 400);
            }

            $decoded = base64_decode($request->base64_content);
            if ($decoded === false) {
                return response()->json(['status' => 'error', 'message' => 'Format berkas biner tidak valid.'], 400);
            }

            $pc = Pc::findOrFail($backup->pc_id);
            $oldSize = (int)$backup->file_size;
            $newSize = strlen($decoded);
            $sizeDiff = $newSize - $oldSize;

            if ($sizeDiff > 0 && ($pc->current_usage + $sizeDiff) > $pc->max_quota) {
                return response()->json(['status' => 'error', 'message' => 'Penyimpanan penuh! Kuota pangkalan PC terlampaui.'], 400);
            }

            // Simpan langsung menimpa berkas lama di public storage disk
            Storage::disk('public')->put($backup->file_path, $decoded);

            // Update metadata
            $backup->update([
                'file_size' => $newSize,
                'file_type' => pathinfo($backup->file_name, PATHINFO_EXTENSION) ?: 'xlsx',
                'updated_at' => now(),
            ]);

            if ($sizeDiff !== 0) {
                $pc->increment('current_usage', $sizeDiff);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Perubahan berkas Excel berhasil disimpan ke penyimpanan cadangan.',
                'file_size' => $newSize,
                'size_human' => $this->formatBytes($newSize),
                'date_human' => Carbon::parse($backup->updated_at)->format('d M Y H:i'),
                'pc_usage_human' => $this->formatBytes($pc->current_usage),
                'pc_usage_percentage' => $pc->max_quota > 0 ? round(($pc->current_usage / $pc->max_quota) * 100, 2) : 0,
            ]);
        } catch (\Exception $e) {
            Log::error("Gagal menyimpan berkas Excel backup ID {$id}: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal menyimpan perubahan berkas: ' . $e->getMessage()], 500);
        }
    }

    /**
     * FITUR: BUAT BERKAS SPREADSHEET EXCEL BARU LANGSUNG DI FOLDER
     */
    public function createExcel(Request $request)
    {
        $request->validate([
            'pc_id' => 'required|exists:pcs,id',
            'file_name' => 'required|string|max:100',
            'parent_id' => 'nullable',
            'base64_content' => 'required|string'
        ]);

        try {
            $pc = Pc::findOrFail($request->pc_id);
            $decoded = base64_decode($request->base64_content);
            if ($decoded === false) {
                return back()->with('error', 'Format data spreadsheet tidak valid.');
            }

            $fileSize = strlen($decoded);
            if (($pc->current_usage + $fileSize) > $pc->max_quota) {
                return back()->with('error', 'Penyimpanan penuh! Kapasitas pangkalan PC terlampaui.');
            }

            $fileName = trim($request->file_name);
            if (!str_ends_with(strtolower($fileName), '.xlsx')) {
                $fileName .= '.xlsx';
            }

            $path = 'backups/' . $pc->id . '/' . time() . '_' . $fileName;
            Storage::disk('public')->put($path, $decoded);

            Backup::create([
                'pc_id' => $pc->id,
                'parent_id' => $request->parent_id,
                'file_name' => $fileName,
                'file_path' => $path,
                'file_size' => $fileSize,
                'is_folder' => false,
                'file_type' => 'xlsx',
            ]);

            $pc->increment('current_usage', $fileSize);
            return back()->with('success', 'Berkas Excel baru berhasil dibuat dan disimpan.');
        } catch (\Exception $e) {
            Log::error("Gagal membuat berkas Excel baru: " . $e->getMessage());
            return back()->with('error', 'Gagal membuat berkas Excel: ' . $e->getMessage());
        }
    }

    /**
     * OPERASI KHUSUS ADMIN: BUAT PANGKALAN PC BARU DENGAN KUOTA GB KUSTOM
     */
    public function createPc(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            return abort(403, 'Anda tidak memiliki otoritas membuat pangkalan PC.');
        }

        $validated = $request->validate([
            'pc_name' => 'required|string|max:100',
            'user_id' => 'required|exists:users,id',
            'quota_gb' => 'required|numeric|min:1|max:100000',
        ]);

        $pool = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomHw = substr(str_shuffle($pool), 0, 8);
        $maxQuotaBytes = (int)($validated['quota_gb'] * 1073741824);

        $pc = Pc::create([
            'user_id' => $validated['user_id'],
            'pc_name' => $validated['pc_name'],
            'hardware_id' => 'HW-' . $randomHw,
            'current_usage' => 0,
            'max_quota' => $maxQuotaBytes,
        ]);

        return back()->with('success', "Pangkalan Backup '{$pc->pc_name}' dengan kuota {$validated['quota_gb']} GB berhasil dibangun.");
    }

    /**
     * OPERASI KHUSUS ADMIN: SESUAIKAN KUOTA GB PANGKALAN PC
     */
    public function updateQuota(Request $request, $id)
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            return abort(403, 'Anda tidak memiliki otoritas mengubah kuota pangkalan.');
        }

        $validated = $request->validate([
            'quota_gb' => 'required|numeric|min:1|max:100000',
        ]);

        $pc = Pc::findOrFail($id);
        $maxQuotaBytes = (int)($validated['quota_gb'] * 1073741824);

        $pc->update([
            'max_quota' => $maxQuotaBytes,
        ]);

        return back()->with('success', "Kapasitas kuota '{$pc->pc_name}' berhasil disesuaikan menjadi {$validated['quota_gb']} GB.");
    }

    private function formatPcData($pc) {
        $pc->usage_human = $this->formatBytes($pc->current_usage);
        $pc->quota_human = $this->formatBytes($pc->max_quota);
        $pc->usage_percentage = $pc->max_quota > 0 ? round(($pc->current_usage / $pc->max_quota) * 100, 2) : 0;
        return $pc;
    }

    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) $bytes /= 1024;
        return round($bytes, 2) . ' ' . $units[$i];
    }
}