<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\LetterLogController;
use App\Http\Controllers\VisitorLogController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\Auth\ResetPasswordController; 
use App\Http\Controllers\SignatureRequestController;
use App\Http\Controllers\SoldierViolationController;
use App\Http\Controllers\CommunityActivityController;
use App\Http\Controllers\StampController;
use App\Http\Controllers\BackupController; 
use App\Http\Controllers\CashController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\PESSAdminController; // SULTAN CONFIG: Pangkalan Pemisah Logika Otoritas Kerja Admin PESS
use App\Http\Controllers\API\PESSReceiverController; // SULTAN CONFIG: Controller Penerima File Lintas VPS
use App\Http\Controllers\CommanderAccountController; // SINDEN CORRECTION: Kalibrasi typo dari Controkkers ke Controllers
use App\Http\Controllers\MitraPaymentController;
use App\Http\Controllers\TechnicalUnitCashController;
use App\Http\Controllers\SkhppController;
use App\Models\User;
use App\Models\LetterLog;
use App\Models\Letter;
use App\Models\VisitorLog;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

// --- HALAMAN DEPAN (DIRECT LOGIN SINDEN) ---
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// --- FITUR AKTIVASI & RESET CUSTOM (AKSES PUBLIK) ---
Route::get('/aktivasi', function () {
    return Inertia::render('Auth/Aktivasi'); 
})->name('aktivasi');
Route::post('/aktivasi', [UserController::class, 'activate'])->name('aktivasi.proses');

Route::get('forgot-password-custom', [ResetPasswordController::class, 'showResetForm'])->name('password.request.custom');
Route::post('reset-password-custom', [ResetPasswordController::class, 'store'])->name('password.update.custom');

// --- AKSES DASHBOARD UTAMA ---
Route::get('/dashboard', function () {
    $user = auth()->user();
    $recentVisitors = VisitorLog::latest()->take(5)->get()->map(function($log) {
        $log->type = 'LOGIN'; 
        return $log;
    });
    $recentAudits = AuditLog::latest()->take(5)->get()->map(function($log) {
        $log->type = 'ADMIN_ACTION'; 
        $log->user_name = $log->admin_name; 
        $log->nrp = 'ADMIN'; 
        return $log;
    });
    $combinedActivities = $user->role === 'admin' ? $recentVisitors->concat($recentAudits)->sortByDesc('created_at')->take(5)->values() : null;

    return Inertia::render('Dashboard', [
        'stats' => [
            'total_logs' => LetterLog::count(), 
            'total_archives' => Letter::count(), 
            'active_personnel' => User::where('is_active', true)->count(), 
        ],
        'recent_logs' => LetterLog::latest()->take(5)->get(),
        'combined_activities' => $combinedActivities,
        'pending_users' => $user->role === 'admin' ? User::where('is_active', false)->latest()->take(5)->get() : null,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

// =========================================================================
// MODUL BACKUP & RADAR IP (OPERASI KHUSUS KANTOR)
// =========================================================================
Route::middleware(['auth', 'office.only'])->group(function () {
    // Jalur Utama Backup
    Route::get('/pc-backup', [BackupController::class, 'index'])->name('backup.index');
    
    // --- JALUR OTORITAS AKSES (PENTING) ---
    // Sesuai pangkalan kantor.sql, rute ini dikunci ketat radar IP kantor
    Route::post('/pc-backup/request', [BackupController::class, 'requestAccess'])->name('backup.request-access');
    Route::post('/pc-backup/verify', [BackupController::class, 'verifyAccess'])->name('backup.verify'); 
    Route::post('/pc-backup/approve/{id}', [BackupController::class, 'approveAccess'])->name('admin.backup.approve');

    // Jalur Manajemen File
    Route::get('/pc-backup/explore/{id}', [BackupController::class, 'explore'])->name('backup.explore');
    Route::post('/pc-backup/store', [BackupController::class, 'store'])->name('backup.store');
    Route::get('/pc-backup/download/{id}', [BackupController::class, 'download'])->name('backup.download');
    Route::delete('/pc-backup/destroy/{id}', [BackupController::class, 'destroyBackup'])->name('backup.destroy');

    // --- JALUR RADAR IP ADMIN ---
    // Berfungsi mengunci whitelist IP pangkalan agar server VPS aman dari luar
    Route::post('/admin/backup/network', [BackupController::class, 'storeNetwork'])->name('admin.backup.network.store');
    Route::delete('/admin/backup/network/{id}', [BackupController::class, 'destroyNetwork'])->name('admin.backup.network.destroy');
    
    //--membuat folder
    Route::post('/pc-backup/create-folder', [BackupController::class, 'createFolder'])->name('backup.create-folder');
    
    // --- FITUR EKSTRAKSI CERDAS & RENAME (KALIBRASI 20GB) ---
    Route::post('/pc-backup/extract/{id}', [BackupController::class, 'extract'])->name('backup.extract');
    Route::post('/pc-backup/rename/{id}', [BackupController::class, 'rename'])->name('backup.rename');
    Route::delete('/backup/{id}', [BackupController::class, 'destroyBackup'])->name('backup.destroy-backup');

    // PENAMBAHAN RADAR PROGRESS & CANCEL (KHUSUS EXTRAK BESAR SURAT MAKO)
    Route::post('/pc-backup/start-extract/{id}', [BackupController::class, 'startExtract'])->name('backup.start-extract');
    Route::get('/pc-backup/extract-progress', [BackupController::class, 'getExtractProgress'])->name('backup.extract-progress');
    Route::post('/pc-backup/cancel-extract', [BackupController::class, 'cancelExtract'])->name('backup.cancel-extract');
    Route::get('/pc-backup/view-office/{id}', [BackupController::class, 'viewOffice'])->name('backup.view-office');
});

// --- AKSES TERPROTEKSI (AUTH) ---
Route::middleware('auth')->group(function () {
    
    // Fitur GPS Lokasi Personel
    Route::post('/update-location', [LocationController::class, 'update'])->name('location.update');

    // Fitur Profil Personel
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Manajemen Surat-Menyurat
    Route::resource('letters', LetterController::class);
    Route::post('/letters/store-direct', [LetterController::class, 'storeDirect'])->name('letters.store-direct');
    Route::get('/letter-logs', [LetterLogController::class, 'index'])->name('letter-logs.index');
    Route::post('/letter-logs', [LetterLogController::class, 'store'])->name('letter-logs.store');
    Route::get('/letter-logs/next-number', [LetterLogController::class, 'getNextNumber'])->name('letter-logs.next-number');
    
    // Modul TTD Komandan (Otoritas Permohonan Surat)
    Route::get('/signature-requests', [SignatureRequestController::class, 'index'])->name('signature.index');
    Route::post('/signature-requests', [SignatureRequestController::class, 'store'])->name('signature.store');
    Route::patch('/signature-requests/{signatureRequest}', [SignatureRequestController::class, 'update'])->name('signature.update');
    Route::delete('/signature-requests/{signatureRequest}', [SignatureRequestController::class, 'destroy'])->name('signature.destroy');
    
    // Modul Stempel Digital Detasemen Intelijen
    Route::get('/otoritas-stempel', [StampController::class, 'index'])->name('stamp.index');
    Route::patch('/otoritas-stempel/eksekusi', [StampController::class, 'apply'])->name('signature.apply-stamp');
    Route::post('/otoritas-stempel/upload-manual', [StampController::class, 'uploadManual'])->name('stamp.upload-manual');
    Route::get('/settings/stamp-config', [StampController::class, 'setting'])->name('stamp.setting');

    // =========================================================================
    // MODUL PELANGGARAN DISIPLIN PRAJURIT (URUTAN STRATEGIS PASUKAN)
    // =========================================================================
    Route::get('/soldier-violations', [SoldierViolationController::class, 'index'])->name('soldier-violations.index');
    Route::post('/soldier-violations', [SoldierViolationController::class, 'store'])->name('soldier-violations.store');
    
    // ⚔️ AMANKAN POSISI DI ATAS WILDCARD {id} agar kebal dari respons 405
    Route::get('/soldier-violations/preview-file', [SoldierViolationController::class, 'previewDocx'])->name('soldier-violations.preview-file');
    
    // ⚔️ KALIBRASI METODE MATCH: Bypass proteksi data biner multipart/form-data
    Route::match(['post', 'put'], '/soldier-violations/{id}', [SoldierViolationController::class, 'update'])->name('soldier-violations.update');
    
    Route::delete('/soldier-violations/{id}/delete-update', [SoldierViolationController::class, 'deleteUpdate'])->name('soldier-violations.delete-update');
    Route::delete('/soldier-violations/{id}', [SoldierViolationController::class, 'destroy'])->name('soldier-violations.destroy');
    
    // Modul Keuangan / Cash Buku Kas Detasemen
    Route::get('/cash', [CashController::class, 'index'])->name('cash.index');
    Route::post('/cash', [CashController::class, 'store'])->name('cash.store');
    Route::get('/cash/pdf', [CashController::class, 'exportPdf'])->name('cash.pdf');
    Route::put('/cash/{id}', [CashController::class, 'update'])->name('cash.update');
    Route::delete('/cash/{id}', [CashController::class, 'destroy'])->name('cash.destroy');
    
    // Unduh Rekap Buku Log Surat Berupa Dokumen PDF
    Route::get('/letter-logs/download-pdf', [LetterLogController::class, 'downloadPdf'])->name('letter-logs.pdf');
    
    // Modul Ucapan Hari Ulang Tahun Otomatis Lewat Radar Personel
    Route::get('/admin/birthday-radar', [PersonnelController::class, 'index'])->name('birthday.index');
    Route::post('/admin/personnel', [PersonnelController::class, 'storePersonnel'])->name('personnel.store');
    Route::put('/admin/user-birthday/{id}', [PersonnelController::class, 'updateUserBirthDate'])->name('user.birthday.update');
    Route::delete('/admin/personnel/{id}', [PersonnelController::class, 'destroyPersonnel'])->name('personnel.destroy');
    Route::post('/admin/birthday-radar/settings', [PersonnelController::class, 'updateSettings'])->name('admin.settings.birthday');
    Route::post('/admin/birthday-radar/test', [PersonnelController::class, 'testGreeting'])->name('admin.birthday.test');
    
    // =========================================================================
    // MODUL STRATEGIS REKENING KOMANDAN
    // =========================================================================
    Route::get('/commander-account', [CommanderAccountController::class, 'index'])->name('commander.index');
    Route::post('/commander-account', [CommanderAccountController::class, 'store'])->name('commander.store');
    Route::put('/commander-account/{id}', [CommanderAccountController::class, 'update'])->name('commander.update');
    Route::delete('/commander-account/{id}', [CommanderAccountController::class, 'destroy'])->name('commander.destroy');
    Route::get('/commander-account/pdf', [CommanderAccountController::class, 'exportPdf'])->name('commander.pdf');

    // =========================================================================
    // MODUL MANAJEMEN PENCATATAN PEMBAYARAN MITRA (MATRIX BULANAN & CETAK PDF)
    // =========================================================================
    Route::get('/mitras', [MitraPaymentController::class, 'index'])->name('mitras.index');
    Route::post('/mitras', [MitraPaymentController::class, 'store'])->name('mitras.store');
    Route::put('/mitras/{id}', [MitraPaymentController::class, 'update'])->name('mitras.update');
    Route::delete('/mitras/{id}', [MitraPaymentController::class, 'destroy'])->name('mitras.destroy');
    Route::post('/mitras/toggle-payment', [MitraPaymentController::class, 'togglePayment'])->name('mitras.toggle-payment');
    Route::post('/mitras/reorder', [MitraPaymentController::class, 'reorder'])->name('mitras.reorder');
    Route::post('/mitras/{id}/move', [MitraPaymentController::class, 'movePosition'])->name('mitras.move');

    // =========================================================================
    // MODUL MANAJEMEN BUKU KAS DAN UNIT TEKNIS
    // =========================================================================
    Route::get('/technical-cash', [TechnicalUnitCashController::class, 'index'])->name('technical-cash.index');
    Route::post('/technical-cash', [TechnicalUnitCashController::class, 'store'])->name('technical-cash.store');
    Route::put('/technical-cash/{id}', [TechnicalUnitCashController::class, 'update'])->name('technical-cash.update');
    Route::delete('/technical-cash/{id}', [TechnicalUnitCashController::class, 'destroy'])->name('technical-cash.destroy');

    // =========================================================================
    // FITUR KHUSUS ADMIN (OTORITAS TINGGI MONEV)
    // =========================================================================
    Route::middleware(['role:admin'])->group(function () {
        Route::post('/signature-requests/clear-all', [SignatureRequestController::class, 'clearAll'])->name('signature.clear-all');
        Route::get('/users/print-report-pdf', [UserController::class, 'printPdf'])->name('users.print-pdf');

        // Manajemen Personel Internal & Token Akses
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle');
        Route::post('/users/{user}/generate-token', [UserController::class, 'generateResetToken'])->name('users.generate-token');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{id}/toggle-mitra-access', [MitraPaymentController::class, 'toggleUserAccess'])->name('users.toggle-mitra-access');
        Route::post('/users/{id}/toggle-technical-cash-access', [TechnicalUnitCashController::class, 'toggleUserAccess'])->name('users.toggle-technical-cash-access');

        // Kategori Arsip Surat Mako V
        Route::resource('categories', CategoryController::class);
        Route::post('/categories/{category}/sub', [CategoryController::class, 'storeSub'])->name('categories.storeSub');
        Route::delete('/sub-categories/{subCategory}', [CategoryController::class, 'destroySub'])->name('sub-categories.destroy');

        // Monitoring Sistem & Audit Logs Aktivitas Admin
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/update-stamp', [SettingController::class, 'updateStamp'])->name('settings.update-stamp');
        Route::get('/visitor-logs', [VisitorLogController::class, 'index'])->name('visitor-logs.index');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        
        // Rencana Kegiatan Pengamanan Aktivitas Masyarakat
        Route::get('/community-activities', [CommunityActivityController::class, 'index'])->name('activities.index');
        Route::post('/community-activities', [CommunityActivityController::class, 'store'])->name('activities.store');
        Route::get('/community-activities/{id}', [CommunityActivityController::class, 'show'])->name('activities.show');
        Route::delete('/community-activities/{id}', [CommunityActivityController::class, 'destroy'])->name('activities.destroy');
        Route::put('/community-activities/{id}', [CommunityActivityController::class, 'update'])->name('activities.update');

        // Jalur MFA WhatsApp Gateway Engine Lokal (Port 3000)
        Route::get('/admin/setting/wa-status', function () {
            try {
                $response = Http::timeout(3)->get('http://localhost:3000/status');
                return $response->json();
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'OFFLINE',
                    'pairing_code' => null,
                    'error' => 'WA Gateway tidak merespon'
                ], 500);
            }
        })->name('settings.wa-status');

        // =====================================================================
        // SULTAN CONFIG: JALUR VERIFIKASI INTEGRASI PESS & SINDEN (TTD DIGITAL/SINKODV)
        // =====================================================================
        Route::get('/applicants/dashboard', [PESSAdminController::class, 'index'])->name('admin.pess.dashboard');
        Route::post('/submissions/verify/{id}', [PESSAdminController::class, 'verifyFiles'])->name('admin.pess.verify');
        Route::post('/submissions/interview/{id}', [PESSAdminController::class, 'scheduleInterview'])->name('admin.pess.interview');
        Route::post('/submissions/skhpp/{id}', [PESSAdminController::class, 'issueSKHPP'])->name('admin.pess.skhpp');
        Route::post('/submissions/sign-komandan/{id}', [PESSAdminController::class, 'signDigitalKomandan'])->name('admin.pess.sign_komandan');
        Route::post('/submissions/forward-sinkodv/{id}', [PESSAdminController::class, 'forwardToSINKODV'])->name('admin.pess.forward_sinkodv');
        Route::post('/submissions/sc/upload-sign/{id}', [PESSAdminController::class, 'uploadAndSignSC'])->name('admin.pess.sc_upload_sign');

        // =====================================================================
        // SULTAN CONFIG: TAMBAHAN MURNI MANAJEMEN KONTROL PORTAL AKUN PERSONEL
        // =====================================================================
        // =====================================================================
        // MODUL UTAMA PENERBITAN & OTORITAS SKHPP (MILITER, SIPIL, NIKAH)
        // =====================================================================
        Route::get('/skhpp', [SkhppController::class, 'index'])->name('skhpp.index');
        Route::get('/skhpp/create', [SkhppController::class, 'create'])->name('skhpp.create');
        Route::post('/skhpp', [SkhppController::class, 'store'])->name('skhpp.store');
        Route::get('/skhpp/{id}', [SkhppController::class, 'show'])->name('skhpp.show');
        Route::post('/skhpp/{id}/approve', [SkhppController::class, 'approve'])->name('skhpp.approve');
        Route::post('/skhpp/{id}/reject', [SkhppController::class, 'reject'])->name('skhpp.reject');
        Route::delete('/skhpp/{id}', [SkhppController::class, 'destroy'])->name('skhpp.destroy');
    });
});

// Jalur Verifikasi Publik QR Code Scan TTD Komandan SKHPP
Route::get('/verify-skhpp/{code}', [SkhppController::class, 'verify'])->name('skhpp.verify');

// =====================================================================
// SULTAN CONFIG: GATEWAY OPEN API PENERIMA BERKAS FISIK LINTAS VPS (BEARER TOKEN)
// =====================================================================
Route::post('/api/v1/pess/receive-submission', [PESSReceiverController::class, 'receive']);

require __DIR__.'/auth.php';