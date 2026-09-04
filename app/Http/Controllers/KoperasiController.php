<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\KoperasiAccount;
use App\Models\KoperasiLoan;
use App\Models\KoperasiInstallment;
use App\Models\KoperasiSavingsTransaction;
use App\Models\KoperasiCashMutation;
use App\Models\AppNotification;
use App\Services\WhatsappService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class KoperasiController extends Controller
{
    /**
     * Halaman Utama: Portal Mandiri Personel / Anggota Koperasi
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // 1. Pastikan user memiliki akun koperasi (auto create jika belum ada)
        $cleanNrp = $user->nrp ? preg_replace('/[^A-Za-z0-9]/', '', $user->nrp) : str_pad($user->id, 5, '0', STR_PAD_LEFT);
        $myAccount = KoperasiAccount::firstOrCreate(
            ['user_id' => $user->id],
            [
                'member_number' => 'KOP-' . $cleanNrp,
                'status' => 'active',
                'balance_simpanan_pokok' => 0,
                'balance_simpanan_wajib' => 0,
                'balance_simpanan_sukarela' => 0,
                'total_simpanan' => 0,
            ]
        );

        $isPengurus = $user->isPengurusKoperasi();

        // 2. Data Personal Pengguna
        $myActiveLoan = KoperasiLoan::with(['installments' => function($q) {
            $q->orderBy('installment_no', 'asc');
        }])
        ->where('user_id', $user->id)
        ->whereIn('status', ['pending', 'approved', 'active'])
        ->latest()
        ->first();

        $myLoanHistory = KoperasiLoan::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $myLatestRejectedLoan = KoperasiLoan::where('user_id', $user->id)
            ->where('status', 'rejected')
            ->latest('updated_at')
            ->first();

        $mySavingsTransactions = KoperasiSavingsTransaction::where('user_id', $user->id)
            ->latest()
            ->take(20)
            ->get();

        return Inertia::render('SimpanPinjam/Index', [
            'myAccount' => $myAccount,
            'myActiveLoan' => $myActiveLoan,
            'myLatestRejectedLoan' => $myLatestRejectedLoan,
            'myLoanHistory' => $myLoanHistory,
            'mySavingsTransactions' => $mySavingsTransactions,
            'isPengurus' => $isPengurus,
        ]);
    }

    /**
     * Halaman Khusus Pengurus & Administrator: Pusat Kendali Simpan Pinjam
     */
    public function kelola(Request $request)
    {
        $user = auth()->user();
        if (!$user->isPengurusKoperasi()) {
            abort(403, 'Akses ditolak. Anda tidak memiliki otoritas sebagai Pengurus Koperasi.');
        }

        $activeLoansRemaining = (float) KoperasiLoan::where('status', 'active')->sum('remaining_amount');
        $totalSimpananAll = (float) KoperasiAccount::sum('total_simpanan');
        $cicilanBulanIni = (float) KoperasiInstallment::whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount_paid');
        $pendingLoansCount = KoperasiLoan::where('status', 'pending')->count();
        $totalAnggota = KoperasiAccount::count();

        // Saldo Kas Koperasi dari Mutasi Terakhir
        $latestMutation = KoperasiCashMutation::latest('id')->first();
        $kasKoperasiBalance = $latestMutation ? (float)$latestMutation->balance : (float)($totalSimpananAll + $cicilanBulanIni - $activeLoansRemaining);
        if ($kasKoperasiBalance < 0) $kasKoperasiBalance = 0;

        $metrics = [
            'kas_koperasi' => $kasKoperasiBalance,
            'pinjaman_aktif_total' => $activeLoansRemaining,
            'simpanan_anggota_total' => $totalSimpananAll,
            'cicilan_masuk_bulan_ini' => $cicilanBulanIni,
            'pengajuan_pending_count' => $pendingLoansCount,
            'total_anggota_count' => $totalAnggota,
        ];

        // Seluruh Pinjaman untuk Pengurus
        $queryLoans = KoperasiLoan::with(['user', 'installments'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status') && $request->status !== 'all') {
            $queryLoans->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $queryLoans->where(function($q) use ($search) {
                $q->where('loan_code', 'like', "%{$search}%")
                  ->orWhereHas('user', function($qu) use ($search) {
                      $qu->where('name', 'like', "%{$search}%")
                        ->orWhere('nrp', 'like', "%{$search}%");
                  });
            });
        }

        $allLoans = $queryLoans->paginate(15)->withQueryString();

        // Seluruh Rekening Anggota
        $allMembers = KoperasiAccount::with(['user', 'user.koperasiLoans' => function($q) {
            $q->where('status', 'active');
        }])
        ->paginate(15, ['*'], 'members_page')
        ->withQueryString();

        // Seluruh Mutasi Kas Koperasi
        $allMutations = KoperasiCashMutation::with('recorder')
            ->latest()
            ->take(40)
            ->get();

        // Riwayat Pembayaran Cicilan Terkini
        $allInstallments = KoperasiInstallment::with(['user', 'loan', 'recorder'])
            ->where('amount_paid', '>', 0)
            ->latest('updated_at')
            ->take(40)
            ->get();

        // Daftar personel aktif untuk dropdown
        $personels = User::select('id', 'name', 'pangkat', 'nrp', 'phone')
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        return Inertia::render('SimpanPinjam/Kelola', [
            'metrics' => $metrics,
            'allLoans' => $allLoans,
            'allMembers' => $allMembers,
            'allMutations' => $allMutations,
            'allInstallments' => $allInstallments,
            'personels' => $personels,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    /**
     * Pengajuan Pinjaman Baru oleh Personel
     */
    public function applyLoan(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'loan_type' => 'required|string|in:reguler,darurat,barang,pendidikan',
            'amount_requested' => 'required|numeric|min:100000|max:100000000',
            'duration_months' => 'nullable|integer',
            'purpose' => 'required|string|max:1000',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Cek apakah ada pinjaman yang masih pending atau aktif
        $existing = KoperasiLoan::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'active'])
            ->first();

        if ($existing) {
            return back()->with('error', 'Anda masih memiliki pengajuan pinjaman yang sedang berjalan atau menunggu persetujuan.');
        }

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('koperasi_documents', 'public');
        }

        $loanCode = 'PINJ-' . date('Ym') . '-' . str_pad(KoperasiLoan::count() + 1, 4, '0', STR_PAD_LEFT);
        $amountReq = (float)$request->amount_requested;
        // Tenor otomatis 10 bulan sesuai kebijakan Koperasi SINDEN
        $months = 10;
        $monthlyEst = round($amountReq / $months);

        $loan = KoperasiLoan::create([
            'user_id' => $user->id,
            'loan_code' => $loanCode,
            'loan_type' => $request->loan_type,
            'amount_requested' => $amountReq,
            'amount_approved' => $amountReq,
            'interest_rate_percent' => 0,
            'duration_months' => $months,
            'monthly_installment' => $monthlyEst,
            'total_loan_amount' => $amountReq,
            'total_paid' => 0,
            'remaining_amount' => $amountReq,
            'purpose' => $request->purpose,
            'status' => 'pending',
            'document_path' => $documentPath,
            'notes' => 'Pengajuan mandiri via aplikasi SINDEN (Tenor Otomatis 10 Bulan)',
        ]);

        // 1. Notifikasi In-App untuk Admin & Pengurus Koperasi
        AppNotification::create([
            'user_id' => null,
            'role' => 'admin',
            'title' => 'Pengajuan Pinjaman Koperasi Baru',
            'message' => "Personel {$user->name} ({$user->pangkat} NRP {$user->nrp}) mengajukan pinjaman sebesar Rp " . number_format($amountReq, 0, ',', '.') . " tenor {$months} bulan (Cicilan Rp " . number_format($monthlyEst, 0, ',', '.') . "/bulan).",
            'type' => 'info',
            'link' => route('simpan-pinjam.kelola'),
            'is_read' => false,
        ]);

        // 2. Notifikasi In-App untuk Personel Pemohon
        AppNotification::create([
            'user_id' => $user->id,
            'role' => null,
            'title' => 'Pengajuan Pinjaman Berhasil Diajukan',
            'message' => "Permohonan pinjaman Anda ({$loanCode}) sebesar Rp " . number_format($amountReq, 0, ',', '.') . " telah berhasil terkirim dan sedang menunggu verifikasi Pengurus Koperasi.",
            'type' => 'success',
            'link' => route('simpan-pinjam.index'),
            'is_read' => false,
        ]);

        // 3. Notifikasi WhatsApp Resmi ke Personel yang Bersangkutan
        if (!empty($user->phone)) {
            try {
                $pangkatName = trim(($user->pangkat ?: 'Personel') . ' ' . $user->name);
                $nrpText = $user->nrp ?: '-';
                $amountFormatted = number_format($amountReq, 0, ',', '.');
                $estMonthlyFormatted = number_format($monthlyEst, 0, ',', '.');

                $waApplicantMsg = "PEMBERITAHUAN KOPERASI SINDEN\n"
                    . "KONFIRMASI PENGAJUAN PINJAMAN\n"
                    . "==============================\n"
                    . "Yth. {$pangkatName}\n"
                    . "NRP: {$nrpText}\n\n"
                    . "Laporan: Permohonan pinjaman Anda telah berhasil diajukan ke sistem Koperasi SINDEN dengan rincian sebagai berikut:\n\n"
                    . "- Nomor Registrasi: *{$loanCode}*\n"
                    . "- Jenis Pinjaman: *" . strtoupper($request->loan_type) . "*\n"
                    . "- Plafon Diajukan: *Rp {$amountFormatted}*\n"
                    . "- Tenor: *{$months} Bulan*\n"
                    . "- Estimasi Cicilan: *Rp {$estMonthlyFormatted} / bulan*\n"
                    . "- Keperluan: {$request->purpose}\n"
                    . "- Status Saat Ini: *MENUNGGU VERIFIKASI PENGURUS*\n\n"
                    . "Pengurus Koperasi akan segera memverifikasi permohonan Anda. Perkembangan status pengajuan dapat dipantau langsung melalui menu Simpan Pinjam di aplikasi SINDEN.\n\n"
                    . "Demikian pemberitahuan ini disampaikan. Terima kasih.";

                WhatsappService::sendMessage($user->phone, $waApplicantMsg);
            } catch (\Exception $waErr) {
                Log::error("Gagal mengirim notifikasi WhatsApp pengajuan pinjaman ke pemohon: " . $waErr->getMessage());
            }
        }

        return back()->with('message', 'Pengajuan pinjaman berhasil dikirim. Notifikasi konfirmasi telah dikirimkan ke WhatsApp Anda dan berkas sedang menunggu verifikasi pengurus.');
    }

    /**
     * Persetujuan Pinjaman oleh Pengurus / Admin
     */
    public function approveLoan(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user->isPengurusKoperasi()) {
            abort(403, 'Anda tidak memiliki otoritas sebagai Pengurus Koperasi.');
        }

        $loan = KoperasiLoan::with('user')->findOrFail($id);

        if ($loan->status !== 'pending') {
            return back()->with('error', 'Pinjaman ini sudah diproses sebelumnya.');
        }

        $request->validate([
            'amount_approved' => 'required|numeric|min:100000',
            'duration_months' => 'nullable|integer|min:1|max:60',
            'interest_rate_percent' => 'nullable|numeric|min:0|max:10',
            'notes' => 'nullable|string',
        ]);

        $approvedAmount = (float)$request->amount_approved;
        // Tenor otomatis 10 bulan, suku bunga 0%
        $months = (int)($request->duration_months ?: 10);
        $rate = (float)($request->interest_rate_percent ?? 0);

        $totalInterest = round(($approvedAmount * ($rate / 100)) * $months);
        $totalLoan = $approvedAmount + $totalInterest;
        $monthlyInstallment = round($totalLoan / $months);

        DB::beginTransaction();
        try {
            $loan->update([
                'amount_approved' => $approvedAmount,
                'interest_rate_percent' => $rate,
                'duration_months' => $months,
                'monthly_installment' => $monthlyInstallment,
                'total_loan_amount' => $totalLoan,
                'remaining_amount' => $totalLoan,
                'status' => 'active',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'disbursed_by' => $user->id,
                'disbursed_at' => now(),
                'notes' => $request->notes ?: $loan->notes,
            ]);

            // Bentuk Jadwal Angsuran: Jatuh tempo tepat pada tanggal 1 setiap bulan berikutnya
            for ($i = 1; $i <= $months; $i++) {
                $dueDate = now()->copy()->startOfMonth()->addMonths($i)->format('Y-m-d');
                KoperasiInstallment::create([
                    'loan_id' => $loan->id,
                    'user_id' => $loan->user_id,
                    'installment_no' => $i,
                    'due_date' => $dueDate,
                    'amount_due' => $monthlyInstallment,
                    'amount_paid' => 0,
                    'remaining_loan_after' => $totalLoan,
                    'status' => 'unpaid',
                ]);
            }

            // Catat Mutasi Kas Keluar Koperasi
            $latestMut = KoperasiCashMutation::latest('id')->first();
            $prevBal = $latestMut ? (float)$latestMut->balance : 50000000;
            $newBal = $prevBal - $approvedAmount;

            KoperasiCashMutation::create([
                'transaction_code' => 'MUT-' . date('YmdHis') . '-OUT',
                'date' => now()->format('Y-m-d'),
                'type' => 'out',
                'category' => 'pencairan_pinjaman',
                'amount' => $approvedAmount,
                'balance' => $newBal,
                'reference_type' => 'KoperasiLoan',
                'reference_id' => $loan->id,
                'description' => "Pencairan pinjaman {$loan->loan_code} untuk {$loan->user->name} ({$loan->user->pangkat})",
                'recorded_by' => $user->id,
            ]);

            DB::commit();

            // Notifikasi WhatsApp
            if ($loan->user->phone) {
                $msgWa = "PEMBERITAHUAN KOPERASI SINDEN\n"
                    . "==============================\n"
                    . "Yth. {$loan->user->pangkat} {$loan->user->name}\n\n"
                    . "Pengajuan pinjaman Anda nomor *{$loan->loan_code}* telah *DISETUJUI & DICAIRKAN*.\n\n"
                    . "Rincian Pinjaman:\n"
                    . "- Plafon Disetujui: Rp " . number_format($approvedAmount, 0, ',', '.') . "\n"
                    . "- Tenor: {$months} Bulan\n"
                    . "- Angsuran Bulanan: Rp " . number_format($monthlyInstallment, 0, ',', '.') . "\n"
                    . "- Status: AKTIF BERJALAN\n\n"
                    . "Silakan periksa kartu angsuran Anda melalui menu Simpan Pinjam di SINDEN.";
                WhatsappService::sendMessage($loan->user->phone, $msgWa);
            }

            return back()->with('message', 'Pinjaman berhasil disetujui dan dicairkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal approve loan: " . $e->getMessage());
            return back()->with('error', 'Terjadi kendala saat memproses persetujuan pinjaman.');
        }
    }

    /**
     * Penolakan Pinjaman oleh Pengurus
     */
    public function rejectLoan(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user->isPengurusKoperasi()) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $loan = KoperasiLoan::with('user')->findOrFail($id);
        $loan->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $borrower = $loan->user;
        $amountFormatted = number_format($loan->amount_requested, 0, ',', '.');
        $pangkatName = ($borrower->pangkat ? $borrower->pangkat . ' ' : '') . $borrower->name;
        $nrpText = $borrower->nrp ?: '-';

        // 1. Notifikasi Dalam Aplikasi (Bell Lonceng SINDEN)
        AppNotification::create([
            'user_id' => $borrower->id,
            'role' => null,
            'title' => 'Pengajuan Pinjaman Koperasi Ditolak',
            'message' => "Yth. {$pangkatName}, pengajuan pinjaman nomor {$loan->loan_code} sebesar Rp {$amountFormatted} telah ditolak dengan catatan: \"{$request->rejection_reason}\".",
            'type' => 'error',
            'link' => route('simpan-pinjam.index'),
            'is_read' => false,
        ]);

        // 2. Notifikasi WhatsApp Resmi Kedinasan
        if ($borrower->phone) {
            $msgWa = "PEMBERITAHUAN KOPERASI SINDEN\n"
                . "STATUS PENGAJUAN PINJAMAN\n"
                . "==============================\n"
                . "Yth. {$pangkatName}\n"
                . "NRP: {$nrpText}\n\n"
                . "Diberitahukan bahwa permohonan pinjaman Anda nomor *{$loan->loan_code}* sebesar Rp {$amountFormatted} telah ditinjau dan dinyatakan *DITOLAK* oleh Pengurus Koperasi.\n\n"
                . "Alasan / Catatan Penolakan:\n"
                . "\"{$request->rejection_reason}\"\n\n"
                . "Status rincian penolakan dapat dipantau langsung pada menu Simpan Pinjam di aplikasi SINDEN. Anda dapat berkonsultasi dengan Pengurus Koperasi atau mengajukan kembali permohonan baru.\n\n"
                . "Demikian untuk menjadi maklum. Terima kasih.";

            WhatsappService::sendMessage($borrower->phone, $msgWa);
        }

        return back()->with('message', 'Pengajuan pinjaman telah ditolak dan notifikasi resmi telah dikirimkan.');
    }

    /**
     * FITUR UTAMA: Pencatatan Cicilan dengan Nominal Bebas / Fleksibel oleh Petugas
     */
    public function recordInstallment(Request $request, $loanId)
    {
        $officer = auth()->user();
        if (!$officer->isPengurusKoperasi()) {
            abort(403, 'Akses ditolak. Anda bukan pengurus simpan pinjam.');
        }

        $request->validate([
            'amount_paid' => 'required|numeric|min:1000',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:potong_gaji,transfer,tunai',
            'notes' => 'nullable|string|max:255',
        ]);

        $loan = KoperasiLoan::with(['user', 'installments'])->findOrFail($loanId);

        if ($loan->status !== 'active') {
            return back()->with('error', 'Pinjaman tidak dalam status aktif.');
        }

        $payAmount = (float)$request->amount_paid;

        DB::beginTransaction();
        try {
            $currentRemaining = (float)$loan->remaining_amount;
            $newRemaining = max(0, $currentRemaining - $payAmount);
            $newTotalPaid = (float)$loan->total_paid + $payAmount;

            $receiptNumber = 'KW-KOP-' . date('Ym') . '-' . str_pad(KoperasiInstallment::whereNotNull('receipt_number')->count() + 1, 4, '0', STR_PAD_LEFT);

            $unpaidSchedule = KoperasiInstallment::where('loan_id', $loan->id)
                ->where('status', '!=', 'paid')
                ->orderBy('installment_no', 'asc')
                ->first();

            $installmentNo = $unpaidSchedule ? $unpaidSchedule->installment_no : (KoperasiInstallment::where('loan_id', $loan->id)->count() + 1);

            $recordedInst = KoperasiInstallment::create([
                'loan_id' => $loan->id,
                'user_id' => $loan->user_id,
                'installment_no' => $installmentNo,
                'receipt_number' => $receiptNumber,
                'due_date' => $unpaidSchedule?->due_date ?: $request->payment_date,
                'amount_due' => $loan->monthly_installment,
                'amount_paid' => $payAmount,
                'remaining_loan_after' => $newRemaining,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method,
                'status' => 'paid',
                'recorded_by' => $officer->id,
                'notes' => $request->notes ?: "Pembayaran cicilan angsuran ke-{$installmentNo}",
            ]);

            if ($unpaidSchedule) {
                $unpaidSchedule->update([
                    'status' => 'paid',
                    'amount_paid' => $payAmount,
                    'payment_date' => $request->payment_date,
                    'receipt_number' => $receiptNumber,
                    'recorded_by' => $officer->id,
                ]);
            }

            $newStatus = ($newRemaining <= 0) ? 'paid_off' : 'active';
            $loan->update([
                'remaining_amount' => $newRemaining,
                'total_paid' => $newTotalPaid,
                'status' => $newStatus,
            ]);

            $latestMut = KoperasiCashMutation::latest('id')->first();
            $prevBal = $latestMut ? (float)$latestMut->balance : 50000000;
            $newBal = $prevBal + $payAmount;

            KoperasiCashMutation::create([
                'transaction_code' => 'MUT-' . date('YmdHis') . '-IN',
                'date' => $request->payment_date,
                'type' => 'in',
                'category' => 'pembayaran_cicilan',
                'amount' => $payAmount,
                'balance' => $newBal,
                'reference_type' => 'KoperasiInstallment',
                'reference_id' => $recordedInst->id,
                'description' => "Pembayaran cicilan pinjaman {$loan->loan_code} ({$loan->user->name}) - Kuitansi: {$receiptNumber}",
                'recorded_by' => $officer->id,
            ]);

            DB::commit();

            if ($loan->user->phone) {
                $statusKet = ($newRemaining <= 0) ? "*LUNAS SEPENUHNYA*" : "Sisa Tagihan: Rp " . number_format($newRemaining, 0, ',', '.');
                $waText = "BUKTI PEMBAYARAN CICILAN KOPERASI SINDEN\n"
                    . "========================================\n"
                    . "No. Kuitansi : *{$receiptNumber}*\n"
                    . "Nama Anggota : {$loan->user->pangkat} {$loan->user->name}\n"
                    . "NRP/NIP      : {$loan->user->nrp}\n"
                    . "No. Pinjaman : {$loan->loan_code}\n"
                    . "Tanggal Bayar: " . Carbon::parse($request->payment_date)->isoFormat('D MMMM Y') . "\n"
                    . "Nomor Cicilan: Angsuran Ke-{$installmentNo}\n"
                    . "Metode       : " . strtoupper(str_replace('_', ' ', $request->payment_method)) . "\n"
                    . "----------------------------------------\n"
                    . "Jumlah Bayar : *Rp " . number_format($payAmount, 0, ',', '.') . "*\n"
                    . "Status Hutang: {$statusKet}\n"
                    . "----------------------------------------\n"
                    . "Penerima/Kasir: {$officer->name}\n\n"
                    . "Simpan pesan ini sebagai bukti sah pembayaran cicilan Anda.";
                WhatsappService::sendMessage($loan->user->phone, $waText);
            }

            return back()->with([
                'message' => "Pembayaran cicilan Rp " . number_format($payAmount, 0, ',', '.') . " berhasil dicatat. Kuitansi: {$receiptNumber}",
                'receipt_id' => $recordedInst->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal catat cicilan: " . $e->getMessage());
            return back()->with('error', 'Terjadi kendala saat mencatat pembayaran cicilan.');
        }
    }

    /**
     * Pencatatan Setoran Simpanan oleh Petugas
     */
    public function depositSaving(Request $request)
    {
        $officer = auth()->user();
        if (!$officer->isPengurusKoperasi()) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'saving_type' => 'required|in:pokok,wajib,sukarela',
            'amount' => 'required|numeric|min:1000',
            'payment_method' => 'required|in:potong_gaji,transfer,tunai',
            'notes' => 'nullable|string|max:255',
        ]);

        $targetUser = User::findOrFail($request->user_id);
        $amount = (float)$request->amount;

        $cleanNrp = $targetUser->nrp ? preg_replace('/[^A-Za-z0-9]/', '', $targetUser->nrp) : str_pad($targetUser->id, 5, '0', STR_PAD_LEFT);
        $account = KoperasiAccount::firstOrCreate(
            ['user_id' => $targetUser->id],
            [
                'member_number' => 'KOP-' . $cleanNrp,
                'status' => 'active',
                'balance_simpanan_pokok' => 0,
                'balance_simpanan_wajib' => 0,
                'balance_simpanan_sukarela' => 0,
                'total_simpanan' => 0,
            ]
        );

        DB::beginTransaction();
        try {
            if ($request->saving_type === 'pokok') {
                $account->balance_simpanan_pokok += $amount;
            } elseif ($request->saving_type === 'wajib') {
                $account->balance_simpanan_wajib += $amount;
            } else {
                $account->balance_simpanan_sukarela += $amount;
            }
            $account->total_simpanan += $amount;
            $account->save();

            $trxCode = 'TRX-SIMP-' . date('YmdHis') . '-' . rand(100, 999);

            KoperasiSavingsTransaction::create([
                'account_id' => $account->id,
                'user_id' => $targetUser->id,
                'transaction_code' => $trxCode,
                'type' => 'deposit',
                'saving_type' => $request->saving_type,
                'amount' => $amount,
                'balance_after' => $account->total_simpanan,
                'payment_method' => $request->payment_method,
                'recorded_by' => $officer->id,
                'notes' => $request->notes ?: "Setoran simpanan " . ucfirst($request->saving_type),
            ]);

            $latestMut = KoperasiCashMutation::latest('id')->first();
            $prevBal = $latestMut ? (float)$latestMut->balance : 50000000;
            $newBal = $prevBal + $amount;

            KoperasiCashMutation::create([
                'transaction_code' => 'MUT-' . date('YmdHis') . '-IN',
                'date' => now()->format('Y-m-d'),
                'type' => 'in',
                'category' => 'setoran_simpanan',
                'amount' => $amount,
                'balance' => $newBal,
                'description' => "Setoran simpanan {$request->saving_type} ({$targetUser->name}) - {$trxCode}",
                'recorded_by' => $officer->id,
            ]);

            DB::commit();

            if ($targetUser->phone) {
                $msg = "BUKTI SETORAN SIMPANAN KOPERASI SINDEN\n"
                    . "====================================\n"
                    . "No. Transaksi: {$trxCode}\n"
                    . "Nama Anggota : {$targetUser->pangkat} {$targetUser->name}\n"
                    . "Jenis Simpan : Simpanan " . strtoupper($request->saving_type) . "\n"
                    . "Jumlah Setor : Rp " . number_format($amount, 0, ',', '.') . "\n"
                    . "Total Saldo  : Rp " . number_format($account->total_simpanan, 0, ',', '.') . "\n"
                    . "Petugas      : {$officer->name}\n\n"
                    . "Terima kasih atas partisipasi aktif Anda di Koperasi SINDEN.";
                WhatsappService::sendMessage($targetUser->phone, $msg);
            }

            return back()->with('message', 'Setoran simpanan berhasil dicatat.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal setor simpanan: " . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat mencatat simpanan.');
        }
    }

    /**
     * Penarikan Simpanan Sukarela
     */
    public function withdrawSaving(Request $request)
    {
        $officer = auth()->user();
        if (!$officer->isPengurusKoperasi()) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1000',
            'notes' => 'nullable|string|max:255',
        ]);

        $targetUser = User::findOrFail($request->user_id);
        $account = KoperasiAccount::where('user_id', $targetUser->id)->firstOrFail();
        $amount = (float)$request->amount;

        if ($account->balance_simpanan_sukarela < $amount) {
            return back()->with('error', 'Saldo simpanan sukarela anggota tidak mencukupi untuk penarikan ini.');
        }

        DB::beginTransaction();
        try {
            $account->balance_simpanan_sukarela -= $amount;
            $account->total_simpanan -= $amount;
            $account->save();

            $trxCode = 'TRX-TARIK-' . date('YmdHis') . '-' . rand(100, 999);

            KoperasiSavingsTransaction::create([
                'account_id' => $account->id,
                'user_id' => $targetUser->id,
                'transaction_code' => $trxCode,
                'type' => 'withdrawal',
                'saving_type' => 'sukarela',
                'amount' => $amount,
                'balance_after' => $account->total_simpanan,
                'payment_method' => 'tunai',
                'recorded_by' => $officer->id,
                'notes' => $request->notes ?: "Penarikan simpanan sukarela",
            ]);

            $latestMut = KoperasiCashMutation::latest('id')->first();
            $prevBal = $latestMut ? (float)$latestMut->balance : 50000000;
            $newBal = $prevBal - $amount;

            KoperasiCashMutation::create([
                'transaction_code' => 'MUT-' . date('YmdHis') . '-OUT',
                'date' => now()->format('Y-m-d'),
                'type' => 'out',
                'category' => 'penarikan_simpanan',
                'amount' => $amount,
                'balance' => $newBal,
                'description' => "Penarikan simpanan sukarela ({$targetUser->name}) - {$trxCode}",
                'recorded_by' => $officer->id,
            ]);

            DB::commit();

            return back()->with('message', 'Penarikan simpanan sukarela berhasil dicatat.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal tarik simpanan: " . $e->getMessage());
            return back()->with('error', 'Terjadi kendala saat memproses penarikan.');
        }
    }

    /**
     * Cetak Kuitansi Pembayaran Cicilan Resmi PDF
     */
    public function printReceipt($installmentId)
    {
        $installment = KoperasiInstallment::with(['user', 'loan', 'recorder'])->findOrFail($installmentId);

        $currUser = auth()->user();
        if ($currUser->id !== $installment->user_id && !$currUser->isPengurusKoperasi()) {
            abort(403, 'Akses ditolak.');
        }

        $pdf = Pdf::loadView('pdf.koperasi_receipt', compact('installment'));
        $pdf->setPaper('a5', 'landscape');

        return $pdf->stream("KUITANSI_CICILAN_{$installment->receipt_number}.pdf");
    }

    /**
     * Ekspor Rekap Pembukuan Koperasi PDF
     */
    public function exportLedgerPdf(Request $request)
    {
        $user = auth()->user();
        if (!$user->isPengurusKoperasi()) {
            abort(403, 'Akses ditolak.');
        }

        $loans = KoperasiLoan::with(['user', 'installments'])->latest()->get();
        $mutations = KoperasiCashMutation::with('recorder')->latest()->take(100)->get();
        $accounts = KoperasiAccount::with('user')->get();

        $pdf = Pdf::loadView('pdf.koperasi_ledger', compact('loans', 'mutations', 'accounts'));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream("BUKU_KAS_KOPERASI_" . date('Ymd') . ".pdf");
    }

    /**
     * Manual Trigger: Siaran Pengingat Tagihan Cicilan Tanggal 1 ke Seluruh Anggota Aktif via SINDEN & WhatsApp
     */
    public function broadcastReminders(Request $request)
    {
        $user = auth()->user();
        if (!$user->isPengurusKoperasi()) {
            abort(403, 'Akses ditolak. Anda tidak memiliki otoritas Pengurus Koperasi.');
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('koperasi:send-reminders', ['--force' => true]);
            return back()->with('message', 'Pengingat cicilan tagihan tanggal 1 berhasil dipancarkan ke seluruh personel peminjam via notifikasi SINDEN dan WhatsApp.');
        } catch (\Exception $e) {
            Log::error("Gagal siaran pengingat koperasi: " . $e->getMessage());
            return back()->with('error', 'Kendala saat menyiarkan pengingat: ' . $e->getMessage());
        }
    }
    /**
     * Pencatatan Mutasi Kas Koperasi Langsung (Kas Masuk / Debit & Kas Keluar / Pengeluaran Kredit)
     * Contoh: Pembayaran keperluan A, Beli barang B, ATK kantor, Operasional, Penambahan Modal, dll.
     */
    public function recordCashMutation(Request $request)
    {
        $officer = auth()->user();
        if (!$officer->isPengurusKoperasi()) {
            abort(403, 'Akses ditolak. Anda tidak memiliki otoritas sebagai Pengurus Koperasi.');
        }

        $request->validate([
            'type' => 'required|in:in,out',
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:1000',
            'date' => 'required|date',
            'description' => 'required|string|max:500',
        ]);

        $amount = (float)$request->amount;
        $type = $request->type;

        DB::beginTransaction();
        try {
            $latestMut = KoperasiCashMutation::latest('id')->first();
            $prevBal = $latestMut ? (float)$latestMut->balance : 50000000;
            
            $newBal = ($type === 'in') ? ($prevBal + $amount) : ($prevBal - $amount);

            $trxCode = 'MUT-' . date('YmdHis') . '-' . strtoupper($type) . '-' . rand(10, 99);

            $mutation = KoperasiCashMutation::create([
                'transaction_code' => $trxCode,
                'date' => $request->date,
                'type' => $type,
                'category' => $request->category,
                'amount' => $amount,
                'balance' => $newBal,
                'description' => $request->description,
                'recorded_by' => $officer->id,
            ]);

            DB::commit();

            $jenisText = ($type === 'in') ? 'Kas Masuk (Debit)' : 'Kas Keluar / Pengeluaran (Kredit)';
            return back()->with('message', "{$jenisText} sebesar Rp " . number_format($amount, 0, ',', '.') . " berhasil dicatat ke Buku Kas Koperasi.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal mencatat mutasi kas: " . $e->getMessage());
            return back()->with('error', 'Terjadi kendala saat mencatat mutasi kas: ' . $e->getMessage());
        }
    }

    /**
     * Pelunasan Dini / Dipercepat Pinjaman Sekaligus
     */
    public function earlyPayoff(Request $request, $loanId)
    {
        $officer = auth()->user();
        if (!$officer->isPengurusKoperasi()) {
            abort(403, 'Akses ditolak. Anda bukan pengurus simpan pinjam.');
        }

        $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:potong_gaji,transfer,tunai',
            'notes' => 'nullable|string|max:255',
        ]);

        $loan = KoperasiLoan::with(['user', 'installments'])->findOrFail($loanId);

        if ($loan->status !== 'active') {
            return back()->with('error', 'Pinjaman ini tidak dalam status aktif.');
        }

        $payAmount = (float)$loan->remaining_amount;
        if ($payAmount <= 0) {
            return back()->with('error', 'Pinjaman sudah lunas.');
        }

        DB::beginTransaction();
        try {
            $newTotalPaid = (float)$loan->total_paid + $payAmount;
            $receiptNumber = 'KW-KOP-' . date('Ym') . '-' . str_pad(KoperasiInstallment::whereNotNull('receipt_number')->count() + 1, 4, '0', STR_PAD_LEFT);

            // Tandai seluruh cicilan yang tersisa sebagai lunas
            $unpaidSchedules = KoperasiInstallment::where('loan_id', $loan->id)
                ->where('status', '!=', 'paid')
                ->orderBy('installment_no', 'asc')
                ->get();

            if ($unpaidSchedules->count() > 0) {
                foreach ($unpaidSchedules as $idx => $sch) {
                    $sch->update([
                        'status' => 'paid',
                        'amount_paid' => ($idx === 0) ? $payAmount : 0,
                        'payment_date' => $request->payment_date,
                        'receipt_number' => $receiptNumber,
                        'recorded_by' => $officer->id,
                        'notes' => 'Pelunasan Dipercepat / Dini Sekaligus',
                    ]);
                }
            } else {
                KoperasiInstallment::create([
                    'loan_id' => $loan->id,
                    'user_id' => $loan->user_id,
                    'installment_no' => KoperasiInstallment::where('loan_id', $loan->id)->count() + 1,
                    'receipt_number' => $receiptNumber,
                    'due_date' => $request->payment_date,
                    'amount_due' => $payAmount,
                    'amount_paid' => $payAmount,
                    'remaining_loan_after' => 0,
                    'payment_date' => $request->payment_date,
                    'payment_method' => $request->payment_method,
                    'status' => 'paid',
                    'recorded_by' => $officer->id,
                    'notes' => $request->notes ?: 'Pelunasan Dipercepat / Dini Sekaligus',
                ]);
            }

            $loan->update([
                'remaining_amount' => 0,
                'total_paid' => $newTotalPaid,
                'status' => 'paid_off',
            ]);

            $latestMut = KoperasiCashMutation::latest('id')->first();
            $prevBal = $latestMut ? (float)$latestMut->balance : 50000000;
            $newBal = $prevBal + $payAmount;

            KoperasiCashMutation::create([
                'transaction_code' => 'MUT-' . date('YmdHis') . '-IN',
                'date' => $request->payment_date,
                'type' => 'in',
                'category' => 'pelunasan_dipercepat',
                'amount' => $payAmount,
                'balance' => $newBal,
                'reference_type' => 'KoperasiLoan',
                'reference_id' => $loan->id,
                'description' => "Pelunasan dipercepat pinjaman {$loan->loan_code} ({$loan->user->name}) - Kuitansi: {$receiptNumber}",
                'recorded_by' => $officer->id,
            ]);

            DB::commit();

            if ($loan->user->phone) {
                $waText = "BUKTI PELUNASAN PINJAMAN KOPERASI SINDEN\n"
                    . "========================================\n"
                    . "No. Kuitansi : *{$receiptNumber}*\n"
                    . "Nama Anggota : {$loan->user->pangkat} {$loan->user->name}\n"
                    . "NRP/NIP      : {$loan->user->nrp}\n"
                    . "No. Pinjaman : {$loan->loan_code}\n"
                    . "Tanggal Bayar: " . Carbon::parse($request->payment_date)->isoFormat('D MMMM Y') . "\n"
                    . "Jumlah Bayar : *Rp " . number_format($payAmount, 0, ',', '.') . "*\n"
                    . "Status Tagihan: *LUNAS SEPENUHNYA*\n"
                    . "----------------------------------------\n"
                    . "Keterangan   : Pelunasan Dipercepat (Early Settlement)\n"
                    . "Penerima     : {$officer->name}\n\n"
                    . "Selamat, seluruh kewajiban pinjaman Anda telah lunas dan tercatat sah pada sistem Koperasi SINDEN.";
                WhatsappService::sendMessage($loan->user->phone, $waText);
            }

            return back()->with('message', "Pinjaman {$loan->loan_code} berhasil dilunasi sepenuhnya sebesar Rp " . number_format($payAmount, 0, ',', '.') . ".");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal pelunasan dipercepat: " . $e->getMessage());
            return back()->with('error', 'Terjadi kendala saat memproses pelunasan dipercepat: ' . $e->getMessage());
        }
    }
}
