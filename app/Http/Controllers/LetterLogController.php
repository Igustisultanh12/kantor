<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\LetterSubCategory; 
use App\Models\LetterLog;
use App\Models\AppNotification;
use Illuminate\Support\Facades\Log; // Amunisi Log
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf; // Amunisi Export PDF

class LetterLogController extends Controller
{
    /**
     * Menampilkan halaman Buku Nomor (Agenda).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $canGive = \App\Support\MobileAccess::can($user, 'dispositions.give');
        $superAdmin = \App\Support\MobileAccess::isSuperAdmin($user);
        $isKomandan = \App\Support\MobileAccess::isKomandan($user);
        $jabatan = \App\Support\AgendaSurat::JABATAN_TUJUAN;
        $instruksi = \App\Models\LetterDisposition::INSTRUKSI;
        $fmt = fn ($d) => $d ? $d->format('Y-m-d H:i') : null;
        // Nama + pangkat, tanpa mengulang pangkat bila nama akun sudah memuatnya.
        $who = fn ($u) => $u ? (($u->pangkat && !str_starts_with((string) $u->name, (string) $u->pangkat)) ? "{$u->pangkat} {$u->name}" : $u->name) : null;

        $incoming = \App\Models\Letter::with(['category', 'recorder:id,name,pangkat', 'disposition.giver:id,name,pangkat',
                'disposition.targets.receiver:id,name', 'disposition.targets.completer:id,name'])
            ->whereIn('type', \App\Models\Letter::TIPE_DISPOSISI)
            ->latest()
            ->limit(1000)
            ->get()
            ->map(function ($l) use ($user, $superAdmin, $isKomandan, $jabatan, $instruksi, $fmt, $who) {
                $locked = $l->isAwaitingDisposition()
                    && !($isKomandan || $superAdmin || (int) $l->recorded_by === (int) $user->id);
                $d = $l->disposition;
                return [
                    'id' => $l->id,
                    'type' => $l->type,
                    'twu' => $l->twu,
                    'sheet_url' => !$locked && $l->disposition_sheet_path ? route('letters.disposition.sheet', $l->id) : null,
                    'sheet_merged' => (bool) $l->disposition_sheet_pages,
                    'agenda_number' => $l->agenda_number,
                    'letter_number' => $l->letter_number,
                    'issuer' => $l->issuer,
                    'subject' => $l->subject,
                    'date' => $l->date,
                    'received_at' => $l->received_at?->format('Y-m-d'),
                    'created_at' => $fmt($l->created_at),
                    'security_level' => $l->security_level,
                    'category_id' => $l->category_id,
                    'category' => $l->category?->name,
                    'status' => $l->disposition_status ?? 'lama',
                    'recorder' => $who($l->recorder),
                    'file_url' => $locked ? null : route('letters.file', $l->id),
                    'disposition' => $d ? [
                        'giver' => $who($d->giver) ?? 'Komandan',
                        'created_at' => $fmt($d->created_at),
                        'instructions' => array_values(array_map(fn ($k) => $instruksi[$k] ?? $k, $d->instructions ?? [])),
                        'note' => $d->note,
                        'urgency' => $d->urgency,
                        'due_date' => $d->due_date?->format('Y-m-d'),
                        'targets' => $d->targets->map(fn ($t) => [
                            'id' => $t->id,
                            'role' => $t->role,
                            'role_label' => $jabatan[$t->role] ?? $t->role,
                            'status' => $t->status,
                            'received_by' => $t->receiver?->name,
                            'received_at' => $fmt($t->received_at),
                            'completed_by' => $t->completer?->name,
                            'completed_at' => $fmt($t->completed_at),
                            'report_note' => $t->report_note,
                            'mine' => $t->belongsToUser($user),
                        ])->values(),
                    ] : null,
                ];
            });

        return Inertia::render('LetterLogs/Index', [
            'incoming' => $incoming,
            'disposition_options' => [
                'roles' => $jabatan,
                'instructions' => $instruksi,
                'urgencies' => \App\Models\LetterDisposition::URGENSI,
            ],
            'can' => [
                'give_disposition' => $canGive,
                'record_incoming' => \App\Support\MobileAccess::can($user, 'letters.create'),
                'respond_any' => $superAdmin,
            ],
            'incoming_format' => \App\Support\AgendaSurat::format('masuk'),
            'telegram_format' => \App\Support\AgendaSurat::format('telegram'),
            'categories' => Category::all(),
            'sub_categories' => LetterSubCategory::all(), 
            'logs' => tap(LetterLog::with(['category', 'subCategory'])->latest()->paginate(1000), function ($page) {
                $page->getCollection()->transform(fn ($log) => $log->setAttribute('can_cancel', self::canCancel($log)));
            }),
            'priorities' => [
                'B' => 'BIASA',
                'R' => 'RAHASIA',
                'K' => 'KILAT',
                'SR' => 'SANGAT RAHASIA'
            ]
        ]);
    }

    /**
     * Mengambil nomor urut berikutnya berdasarkan kategori.
     * DISESUAIKAN: Menangani sequence tipe String
     */
    public function getNextNumber(Request $request)
    {
        $request->validate(['category_id' => 'required|exists:categories,id']);
        
        $category = Category::find($request->category_id);
        
        // Cari data terakhir berdasarkan kategori dan tahun berjalan
        $lastLog = LetterLog::where('category_id', $category->id)
            ->whereYear('date', date('Y'))
            ->orderBy('id', 'desc') 
            ->first();

        $lastSequence = $lastLog ? $lastLog->sequence : null;
        
        if (is_numeric($lastSequence)) {
            $nextNumber = (int)$lastSequence + 1;
        } else {
            $nextNumber = $lastSequence ?? $category->start_number;
        }

        return response()->json([
            'sequence' => (string)$nextNumber,
            'formatted' => $nextNumber
        ]);
    }

    /**
     * Simpan "Booking" nomor surat ke Buku Nomor.
     * PERBAIKAN: Validasi sub_category_id dibuat fleksibel (nullable)
     */
    public function store(Request $request)
    {
        // Catat data yang masuk dari form ke Log untuk analisa
        Log::info('Percobaan Booking Nomor:', $request->all());
        
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'nullable', // DIKUNCI: Dibuat fleksibel agar tidak error "Invalid"
            'priority' => 'required|string',
            'subject' => 'required|string|max:255',
            'recipient' => 'nullable|string', 
            'date' => 'required|date',
            'sequence' => 'required|string', 
        ]);

        try {
            DB::beginTransaction();

            $category = Category::find($request->category_id);
            
            $timestamp = strtotime($request->date);
            $year = date('Y', $timestamp);
            $month = date('n', $timestamp);

            $sequence = $request->sequence; 
            $romanMonth = $this->toRoman($month);

            // ============================================================
            // LOGIKA PENYAMARAN KODE (SKHPP-D / SKHPP-P -> SKHPP)
            // ============================================================
            $cleanCode = explode('-', $category->code)[0];
            
            if ($cleanCode === 'R' || $cleanCode === 'Sprin') {
                $fullNumber = "{$cleanCode} / {$sequence} / {$romanMonth} / {$year}";
            } else {
                $fullNumber = "{$request->priority} / {$sequence} / {$cleanCode} / {$romanMonth} / {$year}";
            }
            // ============================================================

            LetterLog::create([
                'full_number' => $fullNumber,
                'sequence' => $sequence, 
                'priority' => $request->priority,
                'category_id' => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'subject' => $request->subject,
                'recipient' => $request->recipient, 
                'date' => $request->date,
                'is_archived' => false,
                'user_id' => auth()->id(),
            ]);

            DB::commit();
            Log::info("Sukses Booking Nomor: {$fullNumber}");

            $user = auth()->user();
            AppNotification::notify(
                null,
                'admin',
                'Nomor Surat Berhasil Di-Booking / Diarsip',
                "Nomor surat {$fullNumber} perihal \"{$request->subject}\" berhasil dicatat di Agenda Surat oleh " . ($user?->name ?? 'Sistem') . ".",
                'success',
                '/letter-logs'
            );

            return back()->with('success', "Nomor surat {$fullNumber} berhasil di-booking.");
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GAGAL SIMPAN SURAT: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return back()->with('error', 'Gagal menyimpan: ' . ' (' . \App\Support\ErrorRef::log($e) . ')');
        }
    }

    /**
     * FITUR DOWNLOAD PDF (Server-Side)
     * Menangani filter kategori dan range tanggal secara presisi.
     */
    public function downloadPdf(Request $request)
    {
        $query = LetterLog::with(['category', 'subCategory']);

        if ($request->has('category_id') && $request->category_id != '') {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('start_date') && $request->start_date != '' && $request->has('end_date') && $request->end_date != '') {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        $logs = $query->latest()->get();
        $categoryName = $request->category_id ? Category::find($request->category_id)->name : 'SEMUA KATEGORI';

        $pdf = Pdf::loadView('pdf.letter_log_report', [
            'logs' => $logs,
            'category_name' => strtoupper($categoryName),
            'start_date' => $request->start_date,
            'end_date' => $request->end_date
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('LAPORAN_REKAPITULASI_SURAT.pdf');
    }

    /**
     * FITUR PRINTER INERTIA (Tetap Dipertahankan)
     */
    public function printPdf(Request $request)
    {
        $query = LetterLog::with(['category', 'subCategory']);

        if ($request->has('category_id') && $request->category_id != '') {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        $logs = $query->latest()->get();
        $categoryName = $request->category_id ? Category::find($request->category_id)->name : 'SEMUA KATEGORI';

        return Inertia::render('LetterLogs/Index', [
            'print_logs' => $logs,
            'filter_name' => strtoupper($categoryName)
        ]);
    }

    /**
     * Batalkan booking nomor yang belum diarsipkan (PDF belum diunggah).
     * Boleh oleh pem-booking sendiri atau Admin; booking lama tanpa pemilik hanya Admin.
     * Bila yang dibatalkan nomor terakhir kategori itu, nomornya dipakai lagi oleh booking berikutnya.
     */
    public function destroy(Request $request, LetterLog $letterLog)
    {
        if ($letterLog->is_archived) {
            return back()->with('error', 'Nomor yang sudah diarsipkan (PDF terunggah) tidak dapat dibatalkan.');
        }
        abort_unless(self::canCancel($letterLog), 403, 'Hanya pem-booking nomor atau Admin yang dapat membatalkan.');

        $nomor = $letterLog->full_number;
        $letterLog->delete();

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'admin_name' => auth()->user()->name,
            'action' => 'BATAL_NOMOR_SURAT',
            'target_personnel' => $nomor,
            'description' => "Membatalkan booking nomor surat {$nomor} (perihal: {$letterLog->subject})",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', "Booking nomor {$nomor} dibatalkan.");
    }

    private static function canCancel(LetterLog $log): bool
    {
        $user = auth()->user();
        if (!$user || $log->is_archived) {
            return false;
        }
        return \App\Support\MobileAccess::isSuperAdmin($user)
            || ($log->user_id !== null && (int) $log->user_id === (int) $user->id);
    }

    /**
     * Fitur Pembersihan riwayat.
     */
    public function clearLogs()
    {
        try {
            LetterLog::truncate();
            return back()->with('success', 'Lapor! Seluruh riwayat penomoran telah berhasil dimusnahkan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memusnahkan riwayat: ' . ' (' . \App\Support\ErrorRef::log($e) . ')');
        }
    }

    /**
     * Helper Romawi.
     */
    private function toRoman($number)
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        return $map[$number] ?? 'I';
    }
}