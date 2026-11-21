<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Letter;
use App\Models\LetterDisposition;
use App\Models\LetterDispositionTarget;
use App\Models\User;
use App\Support\AgendaSurat;
use App\Support\LembarDisposisi;
use App\Support\MobileAccess;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Surat & telegram masuk bernomor agenda + disposisi wajib.
 * Alur: dicatat (status menunggu) → Komandan mendisposisi ke jabatan (lembar disposisi A5 digabung ke depan PDF)
 * → tiap jabatan terima & lapor selesai (kolom Paraf pada lembar diperbarui).
 */
class LetterDispositionController extends Controller
{
    private const LINK = '/letter-logs?tab=disposisi';

    /** Pratinjau nomor agenda surat masuk berikutnya untuk tanggal terima tertentu. */
    public function nextNumber(Request $request)
    {
        $data = $request->validate(['received_at' => 'required|date', 'type' => 'nullable|in:masuk,telegram']);
        $type = AgendaSurat::normalizeType($data['type'] ?? null);
        $seq = (string) AgendaSurat::nextSequence($data['received_at'], $type);
        return response()->json([
            'sequence' => $seq,
            'formatted' => AgendaSurat::build($seq, $data['received_at'], $type),
            'format' => AgendaSurat::format($type),
        ]);
    }

    /** Catat surat masuk baru (wajib PDF) → menunggu disposisi Komandan. */
    public function storeIncoming(Request $request)
    {
        $data = $request->validate([
            'type' => 'nullable|in:masuk,telegram',
            'twu' => 'nullable|required_if:type,telegram|string|max:100',
            'letter_number' => 'required|string|max:255|unique:letters,letter_number',
            'issuer' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'date' => 'required|date',
            'received_at' => 'required|date',
            'security_level' => 'required|in:biasa,rahasia',
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'nullable|integer',
            'agenda_sequence' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+[A-Za-z]?$/'],
            'file' => 'required|file|mimes:pdf|max:20480',
        ], [
            'letter_number.unique' => 'Nomor ini sudah tercatat. Periksa kembali, mungkin sudah pernah diagendakan.',
            'twu.required_if' => 'TWU telegram wajib diisi.',
            'agenda_sequence.regex' => 'Nomor urut hanya angka, boleh diakhiri satu huruf (mis. 14a).',
            'file.required' => 'PDF surat masuk wajib diunggah.',
            'file.mimes' => 'Berkas surat masuk harus PDF.',
        ]);

        $letter = self::catatSuratMasuk($data, $request->file('file'), $request->user(), $request->ip());

        $jenis = $letter->isTelegram() ? 'Telegram' : 'Surat masuk';
        return back()->with('success', "{$jenis} dicatat dengan nomor agenda {$letter->agenda_number}. Menunggu disposisi Komandan.");
    }

    /**
     * Dipakai juga oleh LetterController@store (type "masuk") agar semua surat masuk lewat jalur yang sama.
     *
     * @param array<string, mixed> $data
     */
    public static function catatSuratMasuk(array $data, UploadedFile $file, User $user, ?string $ip = null): Letter
    {
        $receivedAt = $data['received_at'] ?? now()->toDateString();
        $type = AgendaSurat::normalizeType($data['type'] ?? null);
        $path = $file->store('archives', 'local'); // privat (audit S04)

        try {
            $letter = DB::transaction(function () use ($data, $receivedAt, $path, $user, $type) {
                $seq = trim((string) ($data['agenda_sequence'] ?? ''));
                $seq = $seq !== '' ? strtolower($seq) : (string) AgendaSurat::nextSequence($receivedAt, $type);
                $nomor = AgendaSurat::build($seq, $receivedAt, $type);
                if (Letter::where('agenda_number', $nomor)->exists()) {
                    throw ValidationException::withMessages(['agenda_sequence' => "Nomor agenda {$nomor} sudah dipakai."]);
                }

                return Letter::create([
                    'type' => $type,
                    'twu' => $type === 'telegram' ? ($data['twu'] ?? null) : null,
                    'security_level' => $data['security_level'] ?? 'biasa',
                    'category_id' => $data['category_id'],
                    'sub_category_id' => $data['sub_category_id'] ?? null,
                    'letter_number' => $data['letter_number'],
                    'subject' => $data['subject'],
                    'date' => $data['date'],
                    'issuer' => $data['issuer'] ?? '-',
                    'file_path' => $path,
                    'agenda_number' => $nomor,
                    'agenda_sequence' => $seq,
                    'received_at' => $receivedAt,
                    'recorded_by' => $user->id,
                    'disposition_status' => Letter::DISPOSISI_MENUNGGU,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        $jenis = $letter->isTelegram() ? 'Telegram' : 'Surat masuk';
        $aksi = $letter->isTelegram() ? 'CATAT_TELEGRAM' : 'CATAT_SURAT_MASUK';
        self::audit($user, $aksi, $letter->agenda_number, "Mencatat " . strtolower($jenis) . " {$letter->letter_number} dari {$letter->issuer}: {$letter->subject}", $ip);

        AppNotification::notify(
            null,
            'komandan',
            "{$jenis} menunggu disposisi",
            "{$letter->agenda_number} · {$letter->issuer}: \"{$letter->subject}\". Dicatat oleh {$user->name}.",
            'warning',
            self::LINK,
            "*SISINDEN — {$jenis} menunggu disposisi*\n{$letter->agenda_number}\nDari: {$letter->issuer}\nPerihal: {$letter->subject}\n\nBuka Agenda Surat untuk memberi disposisi."
        );

        return $letter;
    }

    /** Komandan memberi disposisi ke satu atau beberapa jabatan. */
    public function dispose(Request $request, Letter $letter)
    {
        $user = $request->user();
        abort_unless(MobileAccess::can($user, 'dispositions.give'), 403, 'Disposisi hanya dapat diberikan oleh Komandan.');
        abort_unless($letter->isAwaitingDisposition(), 422, 'Surat ini tidak sedang menunggu disposisi.');

        $data = $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => ['required', 'string', Rule::in(array_keys(AgendaSurat::JABATAN_TUJUAN))],
            'instructions' => 'required|array|min:1',
            'instructions.*' => ['required', 'string', Rule::in(array_keys(LetterDisposition::INSTRUKSI))],
            'note' => 'nullable|string|max:2000',
            'urgency' => ['required', Rule::in(array_keys(LetterDisposition::URGENSI))],
            'due_date' => 'nullable|date',
        ], [
            'roles.required' => 'Pilih minimal satu jabatan tujuan.',
            'instructions.required' => 'Pilih minimal satu instruksi.',
        ]);

        $roles = array_values(array_unique($data['roles']));
        DB::transaction(function () use ($letter, $data, $roles, $user) {
            $d = LetterDisposition::create([
                'letter_id' => $letter->id,
                'given_by' => $user->id,
                'instructions' => array_values(array_unique($data['instructions'])),
                'note' => $data['note'] ?? null,
                'urgency' => $data['urgency'],
                'due_date' => $data['due_date'] ?? null,
            ]);
            foreach ($roles as $role) {
                $d->targets()->create(['role' => $role, 'status' => LetterDispositionTarget::BARU]);
            }
            $letter->update(['disposition_status' => Letter::DISPOSISI_DIDISPOSISI]);
        });

        // Lembar disposisi A5 digabung ke depan PDF; unggahan asli dihapus setelah gabungan tersimpan.
        $lembar = LembarDisposisi::generate($letter->fresh());

        $instruksi = implode(', ', array_map(fn ($k) => LetterDisposition::INSTRUKSI[$k], $data['instructions']));
        $judulDisposisi = $letter->isTelegram() ? 'Disposisi telegram dari Komandan' : 'Disposisi baru dari Komandan';
        foreach ($roles as $role) {
            AppNotification::notify(
                null,
                $role,
                $judulDisposisi,
                "{$letter->agenda_number} · \"{$letter->subject}\" — {$instruksi}. Terima lalu laporkan bila selesai.",
                'info',
                self::LINK
            );
        }

        $tujuan = implode(', ', array_map(fn ($r) => AgendaSurat::JABATAN_TUJUAN[$r], $roles));
        self::audit($user, 'DISPOSISI_SURAT', $letter->agenda_number, "Disposisi ke {$tujuan}: {$instruksi}", $request->ip());

        $pesan = "Disposisi diteruskan ke {$tujuan}.";
        if ($lembar['sheet_only']) {
            $pesan .= ' Lembar disposisi dibuat terpisah karena PDF unggahan tidak bisa digabung.';
        }
        return back()->with('success', $pesan);
    }

    /** Pemegang jabatan tujuan menandai disposisi diterima. */
    public function receive(Request $request, LetterDispositionTarget $target)
    {
        $user = $request->user();
        $this->authorizeTarget($user, $target);
        abort_unless($target->status === LetterDispositionTarget::BARU, 422, 'Disposisi ini sudah diterima.');

        $target->update(['status' => LetterDispositionTarget::DITERIMA, 'received_by' => $user->id, 'received_at' => now()]);
        $letter = $target->disposition->letter;
        self::audit($user, 'TERIMA_DISPOSISI', $letter->agenda_number, 'Menerima disposisi untuk ' . (AgendaSurat::JABATAN_TUJUAN[$target->role] ?? $target->role), $request->ip());

        return back()->with('success', 'Disposisi ditandai diterima.');
    }

    /** Pemegang jabatan tujuan melaporkan tindak lanjut selesai. */
    public function complete(Request $request, LetterDispositionTarget $target)
    {
        $user = $request->user();
        $this->authorizeTarget($user, $target);
        abort_if($target->status === LetterDispositionTarget::SELESAI, 422, 'Disposisi ini sudah dilaporkan selesai.');

        $data = $request->validate(['report_note' => 'required|string|min:3|max:2000'], [
            'report_note.required' => 'Tuliskan laporan tindak lanjut.',
        ]);

        $disposition = $target->disposition;
        $letter = $disposition->letter;
        $allDone = DB::transaction(function () use ($target, $data, $user, $disposition, $letter) {
            $target->update([
                'status' => LetterDispositionTarget::SELESAI,
                'received_by' => $target->received_by ?? $user->id,
                'received_at' => $target->received_at ?? now(),
                'completed_by' => $user->id,
                'completed_at' => now(),
                'report_note' => $data['report_note'],
            ]);
            $done = !$disposition->targets()->where('status', '!=', LetterDispositionTarget::SELESAI)->exists();
            if ($done) {
                $letter->update(['disposition_status' => Letter::DISPOSISI_SELESAI]);
            }
            return $done;
        });

        // Perbarui kolom Paraf pada lembar disposisi.
        LembarDisposisi::generate($letter->fresh());

        $jabatan = AgendaSurat::JABATAN_TUJUAN[$target->role] ?? $target->role;
        self::audit($user, 'SELESAI_DISPOSISI', $letter->agenda_number, "{$jabatan} melaporkan selesai: {$data['report_note']}", $request->ip());

        $judul = $allDone ? 'Disposisi surat selesai seluruhnya' : "Tindak lanjut disposisi: {$jabatan} selesai";
        $pesan = "{$letter->agenda_number} · \"{$letter->subject}\" — {$jabatan}: {$data['report_note']}";
        AppNotification::notify($disposition->given_by, $disposition->given_by ? null : 'komandan', $judul, $pesan, 'success', self::LINK);
        if ($allDone && $letter->recorded_by && $letter->recorded_by !== $disposition->given_by) {
            AppNotification::notify($letter->recorded_by, null, $judul, $pesan, 'success', self::LINK);
        }

        return back()->with('success', $allDone ? 'Laporan tersimpan. Seluruh disposisi surat ini selesai.' : 'Laporan tindak lanjut tersimpan.');
    }

    /** Lembar disposisi terpisah (hanya bila PDF unggahan tidak bisa digabung). */
    public function sheet(Request $request, Letter $letter)
    {
        $u = $request->user();
        if ($letter->isAwaitingDisposition()) {
            abort_unless(MobileAccess::isKomandan($u) || MobileAccess::isSuperAdmin($u) || (int) $letter->recorded_by === (int) $u->id, 403);
        }
        abort_unless($letter->disposition_sheet_path && Storage::disk('local')->exists($letter->disposition_sheet_path), 404, 'Lembar disposisi sudah tergabung di berkas arsip.');

        return response()->file(Storage::disk('local')->path($letter->disposition_sheet_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="lembar-disposisi-' . $letter->id . '.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeTarget(User $user, LetterDispositionTarget $target): void
    {
        abort_unless(
            $target->belongsToUser($user) || MobileAccess::isSuperAdmin($user),
            403,
            'Disposisi ini ditujukan untuk jabatan lain.'
        );
    }

    private static function audit(User $user, string $action, ?string $target, string $desc, ?string $ip): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'admin_name' => $user->name,
            'action' => $action,
            'target_personnel' => $target,
            'description' => $desc,
            'ip_address' => $ip,
        ]);
    }
}
