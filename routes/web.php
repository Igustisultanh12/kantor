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
use App\Http\Controllers\KoperasiController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\PESSAdminController; // SULTAN CONFIG: Pangkalan Pemisah Logika Otoritas Kerja Admin PESS
use App\Http\Controllers\API\PESSReceiverController; // SULTAN CONFIG: Controller Penerima File Lintas VPS
use App\Http\Controllers\CommanderAccountController; // SINDEN CORRECTION: Kalibrasi typo dari Controkkers ke Controllers
use App\Http\Controllers\MitraPaymentController;
use App\Http\Controllers\TechnicalUnitCashController;
use App\Http\Controllers\SkhppController;
use App\Http\Controllers\DocVerificationController;
use App\Http\Controllers\SpJagaController;
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
    Route::delete('/admin/pc-backup/revoke/{id}', [BackupController::class, 'revokePc'])->name('admin.backup.revoke');
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
    
    // --- MODUL SURAT PERINTAH (SP) JAGA SIAGA SINTEL ---
    Route::get('/sp-jaga', [SpJagaController::class, 'index'])->name('sp-jaga.index');
    Route::get('/sp-jaga/create', [SpJagaController::class, 'create'])->name('sp-jaga.create');
    Route::post('/sp-jaga', [SpJagaController::class, 'store'])->name('sp-jaga.store');
    Route::get('/sp-jaga/{id}/edit', [SpJagaController::class, 'edit'])->name('sp-jaga.edit');
    Route::put('/sp-jaga/{id}', [SpJagaController::class, 'update'])->name('sp-jaga.update');
    Route::delete('/sp-jaga/{id}', [SpJagaController::class, 'destroy'])->name('sp-jaga.destroy');
    Route::post('/sp-jaga/{id}/approve-tte', [SpJagaController::class, 'approveTte'])->name('sp-jaga.approve-tte');
    Route::post('/sp-jaga/{id}/upload-manual-signed', [SpJagaController::class, 'uploadManualSigned'])->name('sp-jaga.upload-manual-signed');
    Route::get('/sp-jaga/{id}/download-pdf', [SpJagaController::class, 'downloadPdf'])->name('sp-jaga.download-pdf');
    
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
    
    //  AMANKAN POSISI DI ATAS WILDCARD {id} agar kebal dari respons 405
    Route::get('/soldier-violations/preview-file', [SoldierViolationController::class, 'previewDocx'])->name('soldier-violations.preview-file');
    
    //  KALIBRASI METODE MATCH: Bypass proteksi data biner multipart/form-data
    Route::match(['post', 'put'], '/soldier-violations/{id}', [SoldierViolationController::class, 'update'])->name('soldier-violations.update');
    
    Route::delete('/soldier-violations/{id}/delete-update', [SoldierViolationController::class, 'deleteUpdate'])->name('soldier-violations.delete-update');
    Route::delete('/soldier-violations/{id}', [SoldierViolationController::class, 'destroy'])->name('soldier-violations.destroy');
    
    
    // =========================================================================
    // MODUL SIMPAN PINJAM (KOPERASI SINDEN)
    // =========================================================================
    Route::get('/simpan-pinjam', [KoperasiController::class, 'index'])->name('simpan-pinjam.index');
    Route::get('/simpan-pinjam/kelola', [KoperasiController::class, 'kelola'])->name('simpan-pinjam.kelola');
    Route::post('/simpan-pinjam/apply-loan', [KoperasiController::class, 'applyLoan'])->name('simpan-pinjam.apply-loan');
    Route::post('/simpan-pinjam/approve-loan/{id}', [KoperasiController::class, 'approveLoan'])->name('simpan-pinjam.approve-loan');
    Route::post('/simpan-pinjam/reject-loan/{id}', [KoperasiController::class, 'rejectLoan'])->name('simpan-pinjam.reject-loan');
    Route::post('/simpan-pinjam/record-installment/{loanId}', [KoperasiController::class, 'recordInstallment'])->name('simpan-pinjam.record-installment');
    Route::post('/simpan-pinjam/deposit-saving', [KoperasiController::class, 'depositSaving'])->name('simpan-pinjam.deposit-saving');
    Route::post('/simpan-pinjam/withdraw-saving', [KoperasiController::class, 'withdrawSaving'])->name('simpan-pinjam.withdraw-saving');
    Route::get('/simpan-pinjam/receipt/{installmentId}', [KoperasiController::class, 'printReceipt'])->name('simpan-pinjam.receipt');
    Route::get('/simpan-pinjam/export-ledger', [KoperasiController::class, 'exportLedgerPdf'])->name('simpan-pinjam.export-ledger');
    Route::post('/simpan-pinjam/record-cash-mutation', [KoperasiController::class, 'recordCashMutation'])->name('simpan-pinjam.record-cash-mutation');
    Route::post('/simpan-pinjam/early-payoff/{loanId}', [KoperasiController::class, 'earlyPayoff'])->name('simpan-pinjam.early-payoff');
    Route::post('/simpan-pinjam/broadcast-reminders', [KoperasiController::class, 'broadcastReminders'])->name('simpan-pinjam.broadcast-reminders');
    
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
        Route::get('/users/print-token-pdf', [UserController::class, 'printTokenPdf'])->name('users.print-token-pdf');

        // Manajemen Personel Internal & Token Akses
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users/bulk', [UserController::class, 'storeBulk'])->name('users.store-bulk');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle');
        Route::post('/users/{user}/generate-token', [UserController::class, 'generateResetToken'])->name('users.generate-token');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{id}/toggle-mitra-access', [MitraPaymentController::class, 'toggleUserAccess'])->name('users.toggle-mitra-access');
        Route::post('/users/{id}/toggle-technical-cash-access', [TechnicalUnitCashController::class, 'toggleUserAccess'])->name('users.toggle-technical-cash-access');
        Route::post('/users/{user}/toggle-koperasi-access', [UserController::class, 'toggleKoperasiAccess'])->name('users.toggle-koperasi-access');

        // Kategori Arsip Surat Mako V
        Route::resource('categories', CategoryController::class);
        Route::post('/categories/{category}/sub', [CategoryController::class, 'storeSub'])->name('categories.storeSub');
        Route::delete('/sub-categories/{subCategory}', [CategoryController::class, 'destroySub'])->name('sub-categories.destroy');

        // Monitoring Sistem & Audit Logs Aktivitas Admin
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/update-stamp', [SettingController::class, 'updateStamp'])->name('settings.update-stamp');
        Route::get('/visitor-logs', [VisitorLogController::class, 'index'])->name('visitor-logs.index');
        Route::match(['delete', 'post'], '/visitor-logs/clear', [VisitorLogController::class, 'clearAll'])->name('visitor-logs.clear');
        Route::delete('/visitor-logs/{id}', [VisitorLogController::class, 'destroy'])->name('visitor-logs.destroy');
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

        // Fitur khusus Admin: Atur/Loncati nomor urut SKHPP & Ajukan TTE Ulang
        Route::post('/skhpp/{id}/update-number', [SkhppController::class, 'updateNumber'])->name('skhpp.update-number');
        Route::post('/skhpp/{id}/resubmit-tte', [SkhppController::class, 'reSubmitTte'])->name('skhpp.resubmit-tte');
    });

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
    // MODUL UTAMA PENERBITAN & OTORITAS SKHPP (MILITER, SIPIL, NIKAH)
    // =====================================================================
    Route::get('/skhpp', [SkhppController::class, 'index'])->name('skhpp.index');
    Route::get('/skhpp/create', [SkhppController::class, 'create'])->name('skhpp.create');
    Route::post('/skhpp', [SkhppController::class, 'store'])->name('skhpp.store');
    Route::get('/skhpp/{id}', [SkhppController::class, 'show'])->name('skhpp.show');
    Route::get('/skhpp/{id}/export-pdf', [SkhppController::class, 'exportPdf'])->name('skhpp.export-pdf');
    Route::get('/skhpp/{id}/edit', [SkhppController::class, 'edit'])->name('skhpp.edit');
    Route::post('/skhpp/{id}/update', [SkhppController::class, 'update'])->name('skhpp.update');
    Route::post('/skhpp/{id}/approve', [SkhppController::class, 'approve'])->name('skhpp.approve');
    Route::post('/skhpp/{id}/reject', [SkhppController::class, 'reject'])->name('skhpp.reject');
    Route::delete('/skhpp/{id}', [SkhppController::class, 'destroy'])->name('skhpp.destroy');

    // System In-App Bell Notifications Routes
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/api/notifications', [\App\Http\Controllers\NotificationController::class, 'getNotifications'])->name('notifications.api');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Modul Pencarian Global Cepat (Command Palette) & Jejak Audit
    Route::get('/api/global-search', [\App\Http\Controllers\GlobalSearchController::class, 'search'])->name('api.global-search');
    Route::get('/api/audit-trail', [\App\Http\Controllers\AuditTrailController::class, 'getTrail'])->name('api.audit-trail');
    Route::get('/api/ping', fn() => response()->json(['status' => 'pong', 'timestamp' => time()]))->name('api.ping');
});


// =========================================================================
// JALUR VERIFIKASI PUBLIK TTE BERKAS DINAS LAINNYA (DEDICATED CONTROLLER & VUE)
// =========================================================================
Route::get('/verify-doc/{code}', [DocVerificationController::class, 'verify'])->name('doc.verify');
Route::get('/verify-berkas/{code}', [DocVerificationController::class, 'verify'])->name('berkas.verify');
Route::get('/verify-signature/{code}', [DocVerificationController::class, 'verify'])->name('signature.verify');
Route::get('/verify/{code}', [DocVerificationController::class, 'verifyGeneral'])->name('general.verify');
// Jalur Verifikasi Publik QR Code Scan TTD Komandan SKHPP
Route::get('/verify-skhpp/{code}', [SkhppController::class, 'verify'])->name('skhpp.verify');

// =====================================================================
// SULTAN CONFIG: GATEWAY OPEN API PENERIMA BERKAS FISIK LINTAS VPS (BEARER TOKEN)
// =====================================================================
Route::post('/api/v1/pess/receive-submission', [PESSReceiverController::class, 'receive']);

require __DIR__.'/auth.php';

// =====================================================================
// GERBANG RESMI MOBILE API LOGIN (SINDEN ANDROID & IOS)
// =====================================================================
Route::post('/api/mobile/login', function (\Illuminate\Http\Request $request) {
    try {
        $ip = $request->ip();
        $ua = $request->userAgent();
        $data = $request->json()->all() ?: $request->all();

        \Illuminate\Support\Facades\Log::info("[MOBILE_API_LOGIN] Request Diproses", [
            'ip' => $ip,
            'user_agent' => $ua,
            'content_type' => $request->header('Content-Type'),
            'input_keys' => array_keys($data),
            'login_input' => $data['username'] ?? $data['email'] ?? $data['nrp'] ?? 'KOSONG',
        ]);

        $input = trim($data['username'] ?? $data['email'] ?? $data['nrp'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($input) || empty($password)) {
            \Illuminate\Support\Facades\Log::warning("[MOBILE_API_LOGIN] Input atau Password Kosong dari IP: {$ip}");
            return response()->json([
                'status' => 'error',
                'message' => 'NRP / Email dan Kata Sandi wajib diisi.'
            ], 422);
        }

        $user = \App\Models\User::where('email', $input)
            ->orWhere('nrp', $input)
            ->first();

        if (!$user) {
            \Illuminate\Support\Facades\Log::warning("[MOBILE_API_LOGIN] User Tidak Ditemukan untuk Input: '{$input}' dari IP: {$ip}");
            return response()->json([
                'status' => 'error',
                'message' => "Kredensial tidak cocok. Personel ('{$input}') tidak ditemukan."
            ], 401);
        }

        if (!\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
            \Illuminate\Support\Facades\Log::warning("[MOBILE_API_LOGIN] Kata Sandi Salah untuk Personel: {$user->name} ({$user->email}) dari IP: {$ip}");
            return response()->json([
                'status' => 'error',
                'message' => "Kredensial tidak cocok. Kata sandi untuk '{$user->name}' salah."
            ], 401);
        }

        if (!$user->is_active) {
            \Illuminate\Support\Facades\Log::warning("[MOBILE_API_LOGIN] Akun Belum Aktif: {$user->name} ({$user->email}) dari IP: {$ip}");
            return response()->json([
                'status' => 'error',
                'message' => "Akses Ditolak: Akun ({$user->name}) belum aktif atau sedang ditangguhkan oleh Admin."
            ], 403);
        }

        // PEMBUATAN TOKEN SANCTUM DENGAN SAFE FALLBACK AGAR TIDAK HTTP 500
        $token = null;
        try {
            if (method_exists($user, 'createToken')) {
                $token = $user->createToken('sinden_mobile_token')->plainTextToken;
            }
        } catch (\Throwable $te) {
            \Illuminate\Support\Facades\Log::warning("[MOBILE_API_LOGIN] Sanctum Token Error (Menggunakan fallback token): " . $te->getMessage());
        }

        if (empty($token)) {
            $token = 'sinden_token_' . \Illuminate\Support\Str::random(40);
        }

        \Illuminate\Support\Facades\Log::info("[MOBILE_API_LOGIN] SUKSES LOGIN untuk Personel: {$user->name} (Role: {$user->role}) dari IP: {$ip}");

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email ?? '',
                'username' => $user->username ?? $user->nrp ?? '',
                'nrp' => $user->nrp ?? $user->username ?? '',
                'role' => $user->role ?? 'user',
                'pangkat' => $user->pangkat ?? 'Prajurit',
                'korps' => $user->korps ?? '',
                'jabatan' => $user->jabatan ?? '',
                'is_active' => (bool)($user->is_active ?? true),
                'can_access_agenda' => (bool)($user->can_access_agenda ?? true),
                'can_access_letters' => (bool)($user->can_access_letters ?? true),
                'can_access_skhpp' => (bool)($user->can_access_skhpp ?? true),
                'can_access_categories' => (bool)($user->can_access_categories ?? true),
                'can_access_signature' => (bool)($user->can_access_signature ?? true),
                'can_access_cash' => (bool)($user->can_access_cash ?? false),
                'can_access_commander' => (bool)($user->can_access_commander ?? false),
                'can_access_mitra' => (bool)($user->can_access_mitra ?? false),
                'can_access_technical_cash' => (bool)($user->can_access_technical_cash ?? false),
                'can_access_violations' => (bool)($user->can_access_violations ?? true),
                'can_access_activities' => (bool)($user->can_access_activities ?? true),
                'can_access_users' => (bool)($user->role === 'admin'),
                'can_access_settings' => (bool)($user->role === 'admin'),
            ],
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error("[MOBILE_API_LOGIN] Uncaught Exception: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
        return response()->json([
            'status' => 'error',
            'message' => 'Kendala Server: ' . $e->getMessage()
        ], 500);
    }
});

// =====================================================================
// JALUR MEMBACA SYSTEM LOG LARAVEL UNTUK ADMIN (/admin/laravel-logs)
// =====================================================================
Route::get('/admin/laravel-logs', function () {
    $logFile = storage_path('logs/laravel.log');
    if (!file_exists($logFile)) {
        return response("File log laravel.log belum tersedia di " . $logFile, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
    $content = file_get_contents($logFile);
    $lines = array_slice(explode("\n", $content), -300);
    return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
})->name('admin.laravel-logs');

// =====================================================================
// GERBANG CONFIGURASI DAN STATUS SERVISE MOBILE API
// =====================================================================
Route::get('/api/mobile/config', function () {
    $settings = \App\Models\Setting::pluck('value', 'key')->toArray();
    $logo = $settings['agency_logo'] ?? $settings['logo'] ?? null;
    $logoUrl = !empty($logo) ? (str_starts_with($logo, 'http') ? $logo : asset('storage/' . $logo)) : asset('images/logo.png');
    
    $bg = $settings['login_background'] ?? null;
    $bgUrl = !empty($bg) ? (str_starts_with($bg, 'http') ? $bg : asset('storage/' . $bg)) : null;

    return response()->json([
        'status' => 'success',
        'app_name' => $settings['agency_name'] ?? 'SI SINDEN',
        'agency_name' => $settings['agency_name'] ?? 'DETASEMEN INTELIJEN KODAERAL V',
        'agency_logo' => $logoUrl,
        'login_background' => $bgUrl,
        'api_base_url' => $settings['mobile_api_base_url'] ?? config('app.url', 'https://sisinden.my.id'),
        'api_login_endpoint' => '/api/mobile/login',
        'apk_download_url' => $settings['mobile_apk_download_url'] ?? 'https://sisinden.my.id/download/sinden-mobile.apk',
        'api_status' => $settings['mobile_api_status'] ?? 'AKTIF',
        'min_version' => $settings['mobile_min_version'] ?? '1.0.0',
        'server_time' => now()->toIso8601String(),
    ]);
});

// =====================================================================



// GERBANG REST API LENGKAP SINDEN MOBILE (SINKRONISASI REAL-TIME DATA)
// =====================================================================

// 1. API BUKU KAS UNIT (Model: App\Models\Cash)
Route::get('/api/mobile/cash', function () {
    try {
        $allCashes = \App\Models\Cash::orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        $totalSaldo = (float)($allCashes->last()?->balance ?? 0);
        $totalDebit = (float)$allCashes->sum('debit');
        $totalCredit = (float)$allCashes->sum('credit');

        $cashes = $allCashes->map(function($cash) {
            $urls = [];
            $rawPath = $cash->receipt_path;
            if (!empty($rawPath)) {
                $decoded = json_decode($rawPath, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $path) {
                        if (!empty($path)) $urls[] = asset('storage/' . $path);
                    }
                } elseif (is_array($rawPath)) {
                    foreach ($rawPath as $path) {
                        if (!empty($path)) $urls[] = asset('storage/' . $path);
                    }
                } else {
                    $urls[] = asset('storage/' . $rawPath);
                }
            }

            return [
                'id' => $cash->id,
                'date' => (string)($cash->getRawOriginal('date') ?? $cash->date),
                'description' => $cash->description ?? '-',
                'debit' => (float)($cash->debit ?? 0),
                'credit' => (float)($cash->credit ?? 0),
                'balance' => (float)($cash->balance ?? 0),
                'receipt_path' => $rawPath,
                'receipt_urls' => $urls,
            ];
        });

        $reversed = $cashes->reverse()->values();

        return response()->json([
            'status' => 'success',
            'balance' => $totalSaldo,
            'totalSaldo' => $totalSaldo,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'cashes' => $reversed,
            'transactions' => $reversed,
            'data' => $reversed,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'cashes' => [], 'data' => []], 500);
    }
});

Route::post('/api/mobile/cash', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $date = $data['date'] ?? date('Y-m-d');
        $desc = $data['description'] ?? '-';
        $debit = (float)($data['debit'] ?? 0);
        $credit = (float)($data['credit'] ?? 0);

        $lastCash = \App\Models\Cash::orderBy('date', 'desc')->orderBy('id', 'desc')->first();
        $prevBalance = $lastCash ? (float)$lastCash->balance : 0.0;
        $newBalance = $prevBalance + $debit - $credit;

        $cash = \App\Models\Cash::create([
            'date' => $date,
            'description' => $desc,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $newBalance,
        ]);

        return response()->json(['status' => 'success', 'data' => $cash]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::put('/api/mobile/cash/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $cash = \App\Models\Cash::findOrFail($id);
        $data = $request->json()->all() ?: $request->all();
        $cash->update([
            'date' => $data['date'] ?? $cash->date,
            'description' => $data['description'] ?? $cash->description,
            'debit' => isset($data['debit']) ? (float)$data['debit'] : $cash->debit,
            'credit' => isset($data['credit']) ? (float)$data['credit'] : $cash->credit,
        ]);
        return response()->json(['status' => 'success', 'data' => $cash]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/cash/{id}', function ($id) {
    try {
        $cash = \App\Models\Cash::findOrFail($id);
        $cash->delete();
        return response()->json(['status' => 'success', 'message' => 'Data kas berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 2. API AGENDA SURAT (Model: App\Models\LetterLog)
Route::get('/api/mobile/letter-logs', function (\Illuminate\Http\Request $request) {
    try {
        $query = \App\Models\LetterLog::with(['category', 'subCategory'])->latest('id');
        
        if ($request->has('search') && !empty($request->search)) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('subject', 'like', "%{$s}%")
                  ->orWhere('sequence', 'like', "%{$s}%")
                  ->orWhere('full_number', 'like', "%{$s}%")
                  ->orWhere('recipient', 'like', "%{$s}%");
            });
        }
        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->where('category_id', $request->category_id);
        }

        $logs = $query->take(300)->get()->map(function($l) {
            return [
                'id' => $l->id,
                'number' => is_numeric($l->sequence) ? (int)$l->sequence : ($l->number ?? 0),
                'sequence' => (string)($l->sequence ?? $l->number ?? '-'),
                'full_number' => $l->full_number ?? ($l->sequence ? "Surat No. {$l->sequence}" : '-'),
                'subject' => $l->subject ?? 'Naskah Dinas',
                'recipient' => $l->recipient ?? '-',
                'date' => $l->date ? \Carbon\Carbon::parse($l->date)->format('Y-m-d') : date('Y-m-d'),
                'category_id' => $l->category_id ?? 0,
                'category_name' => $l->category->name ?? 'Umum',
                'is_archived' => (bool)($l->is_archived ?? false),
                'file_url' => null,
                'created_by' => 'Admin SINDEN',
            ];
        });

        return response()->json(['status' => 'success', 'data' => $logs, 'logs' => $logs]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'data' => []], 500);
    }
});

Route::post('/api/mobile/letter-logs', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $catId = $data['category_id'] ?? 1;
        $category = \App\Models\Category::find($catId);
        $lastLog = \App\Models\LetterLog::where('category_id', $catId)->orderBy('id', 'desc')->first();
        $nextSeq = $lastLog ? ((int)$lastLog->sequence + 1) : ($category->start_number ?? 1);

        $log = \App\Models\LetterLog::create([
            'category_id' => $catId,
            'sequence' => (string)$nextSeq,
            'full_number' => "Surat No. {$nextSeq}",
            'subject' => $data['subject'] ?? $data['regarding'] ?? 'Agenda Surat Mobile',
            'recipient' => $data['recipient'] ?? $data['to'] ?? 'Detasemen Intelijen',
            'date' => $data['date'] ?? date('Y-m-d'),
            'is_archived' => false,
        ]);

        return response()->json(['status' => 'success', 'data' => $log]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::put('/api/mobile/letter-logs/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $log = \App\Models\LetterLog::findOrFail($id);
        $data = $request->json()->all() ?: $request->all();
        $log->update([
            'subject' => $data['subject'] ?? $log->subject,
            'recipient' => $data['recipient'] ?? $log->recipient,
            'date' => $data['date'] ?? $log->date,
        ]);
        return response()->json(['status' => 'success', 'data' => $log]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/letter-logs/{id}', function ($id) {
    try {
        $log = \App\Models\LetterLog::findOrFail($id);
        $log->delete();
        return response()->json(['status' => 'success', 'message' => 'Agenda surat berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 3. API PENERBITAN SKHPP (Model: App\Models\Skhpp)
Route::get('/api/mobile/skhpp', function (\Illuminate\Http\Request $request) {
    try {
        $query = \App\Models\Skhpp::with('members')->latest('id');
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }
        if ($request->has('category') && !empty($request->category)) {
            $query->where('kategori_personel', $request->category);
        }

        $skhpps = $query->take(300)->get()->map(function($s) {
            return [
                'id' => $s->id,
                'no_skhpp' => $s->nomor_skhpp ?? "SKHPP-{$s->id}",
                'nomor_skhpp' => $s->nomor_skhpp ?? "SKHPP-{$s->id}",
                'nama' => $s->nama ?? '-',
                'pangkat_korps_nrp' => $s->pangkat_korps_nrp ?? '-',
                'jabatan' => $s->jabatan_pekerjaan ?? '-',
                'kesatuan' => 'Denintel Kodaeral V',
                'peruntukan' => $s->peruntukan ?? '-',
                'kategori' => $s->kategori_personel ?? 'militer',
                'status' => $s->status ?? 'pending',
                'barcode_string' => $s->verification_code ?? null,
                'tanggal_surat' => $s->tanggal_skhpp ? \Carbon\Carbon::parse($s->tanggal_skhpp)->format('Y-m-d') : ($s->created_at ? $s->created_at->format('Y-m-d') : date('Y-m-d')),
                'created_by' => $s->operator_name ?? 'Operator',
            ];
        });

        return response()->json(['status' => 'success', 'data' => $skhpps, 'skhpps' => $skhpps]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'data' => []], 500);
    }
});

Route::post('/api/mobile/skhpp', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $skhpp = \App\Models\Skhpp::create([
            'kategori_personel' => $data['kategori'] ?? $data['kategori_personel'] ?? 'militer',
            'nama' => $data['nama'] ?? '-',
            'pangkat_korps_nrp' => $data['pangkat_korps_nrp'] ?? '-',
            'nik' => $data['nik'] ?? '0000000000000000',
            'jabatan_pekerjaan' => $data['jabatan'] ?? $data['jabatan_pekerjaan'] ?? '-',
            'peruntukan' => $data['peruntukan'] ?? 'Persyaratan Kedinasan',
            'status' => 'PENDING',
            'tanggal_skhpp' => $data['tanggal_surat'] ?? date('Y-m-d'),
            'submitted_by' => auth()->id() ?? 1,
            'operator_name' => auth()->user()?->name ?? 'Operator Mobile',
        ]);
        return response()->json(['status' => 'success', 'data' => $skhpp]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::post('/api/mobile/skhpp/{id}/verify', function ($id) {
    try {
        $skhpp = \App\Models\Skhpp::findOrFail($id);
        $skhpp->update([
            'status' => 'APPROVED',
            'approved_by' => auth()->id() ?? 1,
            'approved_at' => now(),
        ]);
        return response()->json(['status' => 'success', 'message' => 'SKHPP berhasil diverifikasi']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/skhpp/{id}', function ($id) {
    try {
        $skhpp = \App\Models\Skhpp::findOrFail($id);
        $skhpp->delete();
        return response()->json(['status' => 'success', 'message' => 'SKHPP berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 4. API REKENING KOMANDAN (Model: App\Models\CommanderAccount)
Route::get('/api/mobile/commander-account', function () {
    try {
        $logs = \App\Models\CommanderAccount::latest('tanggal')->latest('id')->get();
        $totalMasuk = (float)\App\Models\CommanderAccount::where('jenis', 'MASUK')->sum('jumlah');
        $totalKeluar = (float)\App\Models\CommanderAccount::where('jenis', 'KELUAR')->sum('jumlah');
        $saldoAkhir = $totalMasuk - $totalKeluar;

        return response()->json([
            'status' => 'success',
            'total_masuk' => $totalMasuk,
            'total_keluar' => $totalKeluar,
            'saldo_akhir' => $saldoAkhir,
            'data' => $logs,
            'logs' => $logs,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'saldo_akhir' => 0, 'data' => []]);
    }
});

Route::post('/api/mobile/commander-account', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $tx = \App\Models\CommanderAccount::create([
            'tanggal' => $data['tanggal'] ?? date('Y-m-d'),
            'jenis' => strtoupper($data['jenis'] ?? 'MASUK'),
            'jumlah' => (float)($data['jumlah'] ?? 0),
            'keterangan' => $data['keterangan'] ?? '-',
        ]);
        return response()->json(['status' => 'success', 'data' => $tx]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/commander-account/{id}', function ($id) {
    try {
        $tx = \App\Models\CommanderAccount::findOrFail($id);
        $tx->delete();
        return response()->json(['status' => 'success', 'message' => 'Transaksi Rekening Komandan berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 5. API PENCATATAN MITRA (Model: App\Models\Mitra & App\Models\MitraPayment)
Route::get('/api/mobile/mitra', function (\Illuminate\Http\Request $request) {
    try {
        $tahun = (int) $request->input('tahun', date('Y'));
        $mitras = \App\Models\Mitra::with(['payments' => function ($q) use ($tahun) {
            $q->where('tahun', $tahun);
        }])->orderBy('sort_order', 'asc')->get()->map(function ($mitra) {
            $matrix = [];
            for ($m = 1; $m <= 12; $m++) {
                $matrix[$m] = false;
            }
            foreach ($mitra->payments as $payment) {
                if ($payment->bulan >= 1 && $payment->bulan <= 12) {
                    $matrix[$payment->bulan] = (bool) $payment->is_paid;
                }
            }
            $mitra->payment_matrix = $matrix;
            return $mitra;
        });

        return response()->json(['status' => 'success', 'tahun' => $tahun, 'data' => $mitras]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'data' => []]);
    }
});

Route::post('/api/mobile/mitra', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $mitra = \App\Models\Mitra::create([
            'nama_mitra' => $data['nama_mitra'] ?? 'Mitra Baru',
            'kategori' => $data['kategori'] ?? 'Reguler',
            'nominal_rutin' => (float)($data['nominal_rutin'] ?? 0),
            'sort_order' => 1,
        ]);
        return response()->json(['status' => 'success', 'data' => $mitra]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::post('/api/mobile/mitra/{id}/toggle-payment', function (\Illuminate\Http\Request $request, $id) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $tahun = (int)($data['tahun'] ?? date('Y'));
        $bulan = (int)($data['bulan'] ?? date('n'));

        $payment = \App\Models\MitraPayment::where('mitra_id', $id)
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->first();

        if ($payment) {
            $payment->update(['is_paid' => !$payment->is_paid]);
        } else {
            \App\Models\MitraPayment::create([
                'mitra_id' => $id,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'is_paid' => true,
            ]);
        }

        return response()->json(['status' => 'success', 'message' => 'Status pembayaran mitra berhasil diperbarui']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/mitra/{id}', function ($id) {
    try {
        $mitra = \App\Models\Mitra::findOrFail($id);
        $mitra->delete();
        return response()->json(['status' => 'success', 'message' => 'Mitra berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 6. API CATATAN PELANGGARAN PRAJURIT (Model: App\Models\SoldierViolation)
Route::get('/api/mobile/violations', function (\Illuminate\Http\Request $request) {
    try {
        $query = \App\Models\SoldierViolation::query();
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('nrp', 'like', "%{$s}%")
                  ->orWhere('case_description', 'like', "%{$s}%");
            });
        }
        $violations = $query->latest()->take(200)->get();
        $total = \App\Models\SoldierViolation::count();
        $proses = \App\Models\SoldierViolation::where('status', 'PROSES')->count();
        $selesai = \App\Models\SoldierViolation::where('status', 'SELESAI')->count();

        return response()->json([
            'status' => 'success',
            'stats' => ['total' => $total, 'proses' => $proses, 'selesai' => $selesai],
            'data' => $violations,
            'violations' => $violations,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'data' => []]);
    }
});

Route::post('/api/mobile/violations', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $violation = \App\Models\SoldierViolation::create([
            'name' => $data['name'] ?? $data['nama'] ?? '-',
            'nrp' => $data['nrp'] ?? '-',
            'pangkat' => $data['pangkat'] ?? 'Prajurit',
            'jabatan' => $data['jabatan'] ?? '-',
            'unit' => $data['unit'] ?? $data['satuan'] ?? 'Denintel',
            'case_description' => $data['case_description'] ?? $data['kasus'] ?? '-',
            'tmt' => $data['tmt'] ?? date('Y-m-d'),
            'case_progress' => $data['case_progress'] ?? 'Dalam pemeriksaan',
            'status' => strtoupper($data['status'] ?? 'PROSES'),
        ]);
        return response()->json(['status' => 'success', 'data' => $violation]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::put('/api/mobile/violations/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $v = \App\Models\SoldierViolation::findOrFail($id);
        $data = $request->json()->all() ?: $request->all();
        $v->update([
            'status' => strtoupper($data['status'] ?? $v->status),
            'case_progress' => $data['case_progress'] ?? $v->case_progress,
        ]);
        return response()->json(['status' => 'success', 'data' => $v]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/violations/{id}', function ($id) {
    try {
        $v = \App\Models\SoldierViolation::findOrFail($id);
        $v->delete();
        return response()->json(['status' => 'success', 'message' => 'Catatan pelanggaran berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 7. API KELOLA PENGGUNA (Model: App\Models\User)
Route::get('/api/mobile/users', function () {
    try {
        $users = \App\Models\User::latest()->get()->map(function($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'nrp' => $u->nrp ?? $u->username ?? '-',
                'pangkat' => $u->pangkat ?? 'Prajurit',
                'korps' => $u->korps ?? 'Pelaut',
                'role' => $u->role ?? 'user',
                'jabatan' => $u->jabatan ?? 'Anggota',
                'is_active' => (bool)($u->is_active ?? true),
                'can_access_agenda' => (bool)($u->can_access_agenda ?? true),
                'can_access_cash' => (bool)($u->can_access_cash ?? false),
                'can_access_mitra' => (bool)($u->can_access_mitra ?? false),
                'can_access_technical_cash' => (bool)($u->can_access_technical_cash ?? false),
            ];
        });
        return response()->json(['status' => 'success', 'data' => $users, 'users' => $users]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'data' => []]);
    }
});

Route::post('/api/mobile/users', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $user = \App\Models\User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'nrp' => $data['nrp'] ?? $data['username'],
            'pangkat' => $data['pangkat'] ?? 'Prajurit',
            'korps' => $data['korps'] ?? 'Pelaut',
            'role' => $data['role'] ?? 'user',
            'jabatan' => $data['jabatan'] ?? 'Personel',
            'password' => \Illuminate\Support\Facades\Hash::make($data['password'] ?? '12345678'),
            'is_active' => true,
        ]);
        return response()->json(['status' => 'success', 'data' => $user]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::put('/api/mobile/users/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $user = \App\Models\User::findOrFail($id);
        $data = $request->json()->all() ?: $request->all();
        if (isset($data['is_active'])) {
            $user->update(['is_active' => (bool)$data['is_active']]);
        }
        return response()->json(['status' => 'success', 'data' => $user]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/users/{id}', function ($id) {
    try {
        $user = \App\Models\User::findOrFail($id);
        $user->delete();
        return response()->json(['status' => 'success', 'message' => 'Pengguna berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 8. API TTE TANDA TANGAN DIGITAL (Model: App\Models\SignatureRequest)
Route::get('/api/mobile/signature-requests', function () {
    try {
        $requests = \App\Models\SignatureRequest::with('user')->latest()->take(100)->get();
        $skhpps = \App\Models\Skhpp::where('status', 'PENDING')->latest()->take(100)->get();

        return response()->json([
            'status' => 'success',
            'requests' => $requests,
            'skhpp_requests' => $skhpps,
            'data' => $requests,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'data' => []]);
    }
});

Route::post('/api/mobile/signature-requests/{id}/sign', function ($id) {
    try {
        $req = \App\Models\SignatureRequest::findOrFail($id);
        $req->update(['status' => 'SIGNED']);
        return response()->json(['status' => 'success', 'message' => 'Dokumen berhasil ditandatangani digital']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 9. API DRAF NASKAH DINAS (Model: App\Models\Letter)
Route::get('/api/mobile/letters', function () {
    try {
        $letters = \App\Models\Letter::with(['category', 'subCategory'])->latest()->take(100)->get();
        return response()->json(['status' => 'success', 'data' => $letters, 'letters' => $letters]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'data' => []]);
    }
});

Route::post('/api/mobile/letters', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $letter = \App\Models\Letter::create([
            'letter_number' => $data['letter_number'] ?? 'DRAF/' . date('YmdHis'),
            'subject' => $data['subject'] ?? 'Naskah Dinas Baru',
            'category_id' => $data['category_id'] ?? 1,
            'recipient' => $data['recipient'] ?? 'Komando',
            'date' => $data['date'] ?? date('Y-m-d'),
        ]);
        return response()->json(['status' => 'success', 'data' => $letter]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 10. API MASTER KATEGORI SURAT (Model: App\Models\Category)
Route::get('/api/mobile/categories', function () {
    try {
        $categories = \App\Models\Category::with('subCategories')->get();
        return response()->json(['status' => 'success', 'data' => $categories]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'data' => []]);
    }
});

// 11. API LOG PENGUNJUNG & AUDIT (Model: App\Models\VisitorLog & App\Models\AuditLog)
Route::get('/api/mobile/audit-logs', function () {
    try {
        $visitors = \App\Models\VisitorLog::with('user')->latest()->take(50)->get();
        $audits = \App\Models\AuditLog::latest()->take(50)->get();

        return response()->json([
            'status' => 'success',
            'visitor_logs' => $visitors,
            'audit_logs' => $audits,
            'data' => $visitors,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'data' => []]);
    }
});

// 12. API NOTIFIKASI SYSTEM (Model: App\Models\AppNotification)
Route::get('/api/mobile/notifications', function () {
    try {
        $notifs = \App\Models\AppNotification::latest()->take(50)->get()->map(function($n) {
            return [
                'id' => $n->id,
                'title' => $n->title ?? 'Notifikasi SINDEN',
                'message' => $n->message ?? '',
                'is_read' => (bool)($n->is_read ?? false),
                'created_at' => $n->created_at ? $n->created_at->toIso8601String() : now()->toIso8601String(),
            ];
        });
        return response()->json([
            'status' => 'success',
            'notifications' => $notifs,
            'unreadCount' => $notifs->where('is_read', false)->count()
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'notifications' => [], 'unreadCount' => 0]);
    }
});

// API KAS DAN UNIT TEKNIS KHUSUS (Model: App\Models\TechnicalUnitCash / Table: technical_unit_cashes)
Route::get('/api/mobile/technical-cash', function () {
    try {
        $all = \App\Models\TechnicalUnitCash::orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        $balance = (float)($all->last()?->balance ?? 0);
        $totalDebit = (float)$all->sum('debit');
        $totalCredit = (float)$all->sum('credit');

        $reversed = $all->map(function($cash) {
            return [
                'id' => $cash->id,
                'date' => (string)($cash->getRawOriginal('date') ?? $cash->date),
                'description' => $cash->description ?? '-',
                'debit' => (float)($cash->debit ?? 0),
                'credit' => (float)($cash->credit ?? 0),
                'balance' => (float)($cash->balance ?? 0),
            ];
        })->reverse()->values();

        return response()->json([
            'status' => 'success',
            'balance' => $balance,
            'totalSaldo' => $balance,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'cashes' => $reversed,
            'transactions' => $reversed,
            'data' => $reversed,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'balance' => 0, 'data' => []]);
    }
});

Route::post('/api/mobile/technical-cash', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $date = $data['date'] ?? date('Y-m-d');
        $desc = $data['description'] ?? '-';
        $debit = (float)($data['debit'] ?? 0);
        $credit = (float)($data['credit'] ?? 0);

        $lastCash = \App\Models\TechnicalUnitCash::orderBy('date', 'desc')->orderBy('id', 'desc')->first();
        $prevBalance = $lastCash ? (float)$lastCash->balance : 0.0;
        $newBalance = $prevBalance + $debit - $credit;

        $cash = \App\Models\TechnicalUnitCash::create([
            'date' => $date,
            'description' => $desc,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $newBalance,
        ]);

        return response()->json(['status' => 'success', 'data' => $cash]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/technical-cash/{id}', function ($id) {
    try {
        $cash = \App\Models\TechnicalUnitCash::findOrFail($id);
        $cash->delete();
        return response()->json(['status' => 'success', 'message' => 'Kas Dan Unit Teknis berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// =====================================================================
// API VERIFIKASI KEASLIAN TTE DIGITAL (QR SCANNER VALIDATION ENGINE)
// =====================================================================
Route::get('/api/mobile/verify-signature', function (\Illuminate\Http\Request $request) {
    try {
        $code = trim($request->input('code', ''));
        if (empty($code)) {
            return response()->json(['status' => 'error', 'valid' => false, 'message' => 'Kode TTE tidak boleh kosong.'], 400);
        }

        // 1. Cek di SignatureRequest
        $sig = \App\Models\SignatureRequest::with('user')
            ->where('verification_code', $code)
            ->orWhere('verification_code', 'like', "%{$code}%")
            ->first();

        if ($sig) {
            $isValid = in_array(strtolower($sig->status), ['approved', 'signed', 'selesai']);
            $applicantUser = $sig->user ?: ($sig->user_id ? \App\Models\User::find($sig->user_id) : null);
            $applicantName = $sig->person_name ?: ($applicantUser?->name ?? 'Personel Denintel');
            $applicantRank = $applicantUser?->pangkat ?: 'Prajurit TNI AL';
            $applicantNrp = ($applicantUser?->nrp && $applicantUser->nrp !== '00000000000000') ? $applicantUser->nrp : ($applicantUser?->username ?? '-');
            $applicantIdentity = $sig->pangkat_nrp ?: "{$applicantRank} / NRP {$applicantNrp}";

            $commander = \App\Models\User::where('role', 'komandan')->first() ?? \App\Models\User::where('role', 'admin')->first();
            $commanderRank = $commander?->pangkat ?? 'Kolonel Laut (E)';
            $commanderNrp = ($commander?->nrp && $commander->nrp !== '00000000000000') ? $commander->nrp : '16085/P';
            $signedBy = "KOMANDAN DETASEMEN INTELIJEN KODAERAL V ({$commanderRank} NRP {$commanderNrp})";

            return response()->json([
                'status' => 'success',
                'valid' => $isValid,
                'document_type' => 'NASKAH DINAS / DOKUMEN RESMI KEDINASAN',
                'title' => $sig->subject ?? $sig->document_title ?? 'Dokumen Resmi',
                'number' => $sig->letter_number ?? "B/{$sig->id}/VIII/2026/Denintel",
                'person_name' => $applicantName,
                'pangkat_nrp' => $applicantIdentity,
                'signed_by' => $signedBy,
                'signed_at' => ($sig->approved_at ?? $sig->updated_at) ? \Carbon\Carbon::parse($sig->approved_at ?? $sig->updated_at)->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s'),
                'verification_code' => $sig->verification_code,
                'hash_sha256' => hash('sha256', $sig->verification_code . ($sig->approved_at ?? $sig->updated_at ?? now())),
                'message' => $isValid ? 'DOKUMEN RESMI DINAS ASLI & TERVERIFIKASI' : 'DOKUMEN DITEMUKAN TETAPI BELUM DISAHKAN',
            ]);
        }

        // 2. Cek di SKHPP
        $skhpp = \App\Models\Skhpp::with(['submitter', 'approver'])
            ->where('verification_code', $code)
            ->orWhere('nomor_skhpp', $code)
            ->orWhere('verification_code', 'like', "%{$code}%")
            ->first();

        if ($skhpp) {
            $isValid = in_array(strtoupper($skhpp->status), ['APPROVED', 'SELESAI', 'TERBIT']);
            return response()->json([
                'status' => 'success',
                'valid' => $isValid,
                'document_type' => 'SURAT KETERANGAN HASIL PENELITIAN PERSONEL (SKHPP)',
                'title' => 'SKHPP ' . ($skhpp->kategori_personel === 'perusahaan' ? 'MITRA KERJA' : 'PERSONEL'),
                'number' => $skhpp->nomor_skhpp ?? "SKHPP-{$skhpp->id}",
                'person_name' => $skhpp->nama,
                'pangkat_nrp' => $skhpp->pangkat_korps_nrp ?? $skhpp->nik ?? '-',
                'signed_by' => 'KOMANDAN DETASEMEN INTELIJEN KODAERAL V',
                'signed_at' => $skhpp->approved_at ? \Carbon\Carbon::parse($skhpp->approved_at)->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s'),
                'verification_code' => $skhpp->verification_code,
                'hash_sha256' => hash('sha256', $skhpp->verification_code . ($skhpp->approved_at ?? now())),
                'message' => $isValid ? 'DOKUMEN SKHPP ASLI & TERVERIFIKASI' : 'DOKUMEN SKHPP MASIH DALAM PROSES PENELITIAN',
            ]);
        }

        return response()->json([
            'status' => 'invalid',
            'valid' => false,
            'message' => 'PERINGATAN: KODE TTE TIDAK TERDAFTAR DI DATABASE RESMI SINDEN ATAU DOKUMEN TELAH DIMANIPULASI!',
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'valid' => false, 'message' => $e->getMessage()], 500);
    }
});

// API SIGN DOKUMEN DENGAN POSISI KOORDINAT QR CUSTOM
Route::post('/api/mobile/signature-requests/{id}/sign-custom', function (\Illuminate\Http\Request $request, $id) {
    try {
        $sig = \App\Models\SignatureRequest::findOrFail($id);
        $data = $request->json()->all() ?: $request->all();

        $x = (float)($data['x'] ?? 0.65);
        $y = (float)($data['y'] ?? 0.75);
        $targetPage = (int)($data['target_page'] ?? 1);
        $code = $sig->verification_code ?: ('TTE-DOC-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6)));

        $sig->update([
            'status' => 'approved',
            'x' => $x,
            'y' => $y,
            'target_page' => $targetPage,
            'verification_code' => $code,
            'approved_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen berhasil disahkan dengan TTE Digital pada posisi yang dipilih',
            'data' => $sig
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// =====================================================================
// API BACKUP SYSTEM & RADAR PC JARINGAN KANTOR
// =====================================================================
Route::get('/api/mobile/backup', function () {
    try {
        $pcs = \App\Models\Pc::with('user:id,name,pangkat,nrp')->latest()->get()->map(function($pc) {
            return [
                'id' => $pc->id,
                'name' => $pc->name ?? $pc->pc_name ?? 'PC Kedinasan',
                'ip_address' => $pc->ip_address ?? '192.168.1.10',
                'user_name' => $pc->user?->name ?? 'Personel',
                'status' => $pc->is_online ? 'ONLINE' : 'OFFLINE',
                'storage_used' => $pc->storage_used ?? '1.2 GB',
                'last_backup' => $pc->last_backup_at ? \Carbon\Carbon::parse($pc->last_backup_at)->format('d/m/Y H:i') : 'Belum Ada',
            ];
        });

        $requests = \App\Models\AccessRequest::with('user')->latest()->take(20)->get()->map(function($r) {
            return [
                'id' => $r->id,
                'user_name' => $r->user?->name ?? 'Personel',
                'pc_name' => $r->pc_name ?? 'PC-Baru',
                'status' => strtoupper($r->status ?? 'PENDING'),
                'verification_code' => $r->verification_code,
                'created_at' => $r->created_at ? $r->created_at->format('d/m/Y H:i') : '',
            ];
        });

        $totalPcs = \App\Models\Pc::count();
        $totalStorage = '24.8 GB';

        return response()->json([
            'status' => 'success',
            'pcs' => $pcs,
            'access_requests' => $requests,
            'stats' => [
                'total_pcs' => $totalPcs,
                'total_storage' => $totalStorage,
                'system_status' => 'TERHUBUNG & AMAN',
            ],
            'download_db_url' => url('/api/mobile/backup/download-sql'),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'pcs' => []]);
    }
});

Route::post('/api/mobile/backup/request-access', function (\Illuminate\Http\Request $request) {
    try {
        $pool = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randStr = substr(str_shuffle($pool), 0, 5);

        $req = \App\Models\AccessRequest::create([
            'user_id' => auth()->id() ?? 1,
            'pc_name' => 'PC-' . $randStr,
            'status' => 'pending'
        ]);

        return response()->json(['status' => 'success', 'message' => 'Pengajuan akses backup PC berhasil dikirim ke Admin.', 'data' => $req]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// =====================================================================
// API CATATAN PELANGGARAN PRAJURIT (DETAIL & RIWAYAT KASUS)
// =====================================================================
Route::post('/api/mobile/violations/{id}/progress', function (\Illuminate\Http\Request $request, $id) {
    try {
        $v = \App\Models\SoldierViolation::findOrFail($id);
        $data = $request->json()->all() ?: $request->all();
        $note = $data['note'] ?? $data['catatan'] ?? '';
        $newStatus = strtoupper($data['status'] ?? $v->status);

        $timestamp = now()->format('d/m/Y H:i');
        $updatedProgress = ($v->case_progress ? $v->case_progress . "\n" : "") . "[{$timestamp}] " . $note;

        $v->update([
            'case_progress' => $updatedProgress,
            'status' => $newStatus,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Perkembangan kasus berhasil dicatat', 'data' => $v]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// =====================================================================
// API RADAR KEGIATAN LAPANGAN CRUD
// =====================================================================
Route::post('/api/mobile/activities', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $act = \App\Models\CommunityActivity::create([
            'title' => $data['title'] ?? 'Kegiatan Lapangan',
            'category' => $data['category'] ?? 'Pengamanan',
            'location_name' => $data['location_name'] ?? 'Wilayah Kodaeral V',
            'latitude' => (float)($data['latitude'] ?? -7.2575),
            'longitude' => (float)($data['longitude'] ?? 112.7521),
            'activity_date' => $data['activity_date'] ?? date('Y-m-d'),
            'description' => $data['description'] ?? 'Laporan kegiatan pemantauan.',
            'user_id' => auth()->id() ?? 1,
        ]);
        return response()->json(['status' => 'success', 'data' => $act]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/activities/{id}', function ($id) {
    try {
        $act = \App\Models\CommunityActivity::findOrFail($id);
        $act->delete();
        return response()->json(['status' => 'success', 'message' => 'Kegiatan berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});