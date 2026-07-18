<?php

namespace App\Http\Controllers;

use App\Models\Pc;
use App\Models\Backup;
use App\Models\AccessRequest; 
use App\Models\OfficeNetwork;
use App\Models\User;
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

        $contents = $contents->map(function($item) {
            if ($item->is_folder) {
                $totalSize = $this->getFolderSize($item->id);
                $item->size_human = $totalSize > 0 ? $this->formatBytes($totalSize) : '0 B';
                $item->file_size = $totalSize;
            } else {
                $item->size_human = $this->formatBytes($item->file_size);
            }
            
            $item->date_human = Carbon::parse($item->created_at)->format('d M Y H:i');
            $item->preview_url = !$item->is_folder ? asset('storage/' . $item->file_path) : null;
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

        return Inertia::render('Backup/Explore', [
            'pc' => $this->formatPcData($pc),
            'contents' => $contents,
            'currentFolderId' => $parentId,
            'breadcrumbs' => $breadcrumbs,
            'searchQuery' => $search
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
     * UPLOAD BERKAS KE STORAGE (MAX 20MB)
     */
    public function store(Request $request)
    {
        $request->validate([
            'pc_id' => 'required|exists:pcs,id', 
            'file' => 'required|file|max:20971520',
            'parent_id' => 'nullable'
        ]);

        $pc = Pc::findOrFail($request->pc_id);
        $fileSize = $request->file('file')->getSize();

        if (($pc->current_usage + $fileSize) > $pc->max_quota) {
            return back()->with('error', 'Penyimpanan Penuh! Kapasitas 200GB terlampaui.');
        }

        $path = $request->file('file')->storeAs('backups/' . $pc->id, time() . '_' . $request->file('file')->getClientOriginalName(), 'public');

        Backup::create([
            'pc_id' => $pc->id,
            'parent_id' => $request->parent_id, 
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $fileSize,
            'is_folder' => false,
            'file_type' => $request->file('file')->getClientOriginalExtension(),
        ]);

        $pc->increment('current_usage', $fileSize);
        return back()->with('success', 'Berkas berhasil diamankan .');
    }

    public function download($id)
    {
        $backup = Backup::findOrFail($id);
        if ($backup->is_folder) return back()->with('error', 'Folder tidak bisa diunduh langsung.');
        return Storage::disk('public')->download($backup->file_path, $backup->file_name);
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