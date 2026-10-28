<?php

namespace App\Http\Controllers;

use App\Models\OpenMeeting;
use App\Models\OpenMeetingAttendee;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OpenMeetingController extends Controller
{
    /**
     * Mengambil data resmi Komandan Detasemen Intelijen Kodaeral V dari database
     */
    public static function getCommanderData(): array
    {
        $komandan = User::where('role', 'komandan')->first()
                 ?? User::where('name', 'like', '%Hari Bagio%')->first()
                 ?? User::where('pangkat', 'like', '%Kolonel%')->first()
                 ?? User::where('pangkat', 'like', '%Letkol%')->first();

        $name = Setting::where('key', 'danden_name')->value('value')
             ?: ($komandan?->name ?? 'Hari Bagio Wijayanto, M.Tr.Opsla.');

        $rank = Setting::where('key', 'danden_pangkat')->value('value')
             ?: ($komandan?->pangkat ?? 'Kolonel Laut (E)');

        $nrp = Setting::where('key', 'danden_nrp')->value('value')
            ?: (($komandan?->nrp && $komandan->nrp !== '00000000000000') ? $komandan->nrp : '16085/P');

        $pangkatNrp = trim("{$rank} NRP {$nrp}");

        return [
            'name' => $name,
            'pangkat' => $rank,
            'nrp' => $nrp,
            'pangkat_nrp' => $pangkatNrp,
            'jabatan' => 'Komandan Detasemen Intelijen Kodaeral V',
        ];
    }

    /**
     * Tampilkan daftar sesi absensi rapat terbuka
     */
    public function index(Request $request)
    {
        $query = OpenMeeting::withCount('attendees')->latest('id');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('creator_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && in_array($request->status, ['open', 'closed'])) {
            $query->where('status', $request->status);
        }

        $meetings = $query->paginate(12)->withQueryString();

        $stats = [
            'total_meetings' => OpenMeeting::count(),
            'open_meetings' => OpenMeeting::where('status', 'open')->count(),
            'closed_meetings' => OpenMeeting::where('status', 'closed')->count(),
            'total_attendees' => OpenMeetingAttendee::count(),
        ];

        return Inertia::render('OpenMeetings/Index', [
            'meetings' => $meetings,
            'filters' => $request->only(['search', 'status']),
            'stats' => $stats,
        ]);
    }

    /**
     * Halaman pembuatan sesi absensi rapat baru
     */
    public function create()
    {
        $user = auth()->user();
        $commander = self::getCommanderData();

        $defaultCreator = [
            'name' => $user?->name ?? 'Petugas SINDEN',
            'pangkat_nrp' => trim(($user?->pangkat ?? '') . ($user?->nrp ? ' NRP ' . $user->nrp : '')),
            'jabatan' => $user?->jabatan ?? ($user?->role === 'admin' ? 'Administrator SINDEN' : 'Petugas Sintel'),
        ];

        return Inertia::render('OpenMeetings/Create', [
            'commander' => $commander,
            'creator' => $defaultCreator,
        ]);
    }

    /**
     * Simpan sesi rapat terbuka baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'time_start' => 'nullable|string|max:10',
            'time_end' => 'nullable|string|max:10',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'custom_fields' => 'nullable|array',
            'custom_fields.*.name' => 'required|string|max:50',
            'custom_fields.*.label' => 'required|string|max:100',
            'custom_fields.*.type' => 'required|string|in:text,number,tel,email,select,textarea',
            'custom_fields.*.required' => 'nullable|boolean',
            'require_signature' => 'nullable|boolean',
        ]);

        $user = auth()->user();
        $commander = self::getCommanderData();

        // Buat slug/uuid acak yang aman dan mudah diakses
        $uuid = Str::lower(Str::random(10));
        while (OpenMeeting::where('uuid', $uuid)->exists()) {
            $uuid = Str::lower(Str::random(10));
        }

        // Sanitasi custom fields
        $customFields = [];
        if (!empty($request->custom_fields) && is_array($request->custom_fields)) {
            foreach ($request->custom_fields as $field) {
                if (empty($field['name']) || empty($field['label'])) continue;
                $customFields[] = [
                    'id' => $field['id'] ?? Str::slug($field['name'], '_'),
                    'name' => Str::slug($field['name'], '_'),
                    'label' => trim($field['label']),
                    'type' => $field['type'] ?? 'text',
                    'required' => (bool)($field['required'] ?? false),
                    'placeholder' => $field['placeholder'] ?? '',
                    'options' => $field['options'] ?? [],
                ];
            }
        }

        $creatorName = $user?->name ?? 'Petugas SINDEN';
        $creatorPangkatNrp = trim(($user?->pangkat ?? '') . ($user?->nrp ? ' NRP ' . $user->nrp : ''));
        $creatorJabatan = $user?->jabatan ?? ($user?->role === 'admin' ? 'Administrator SINDEN' : 'Petugas Sintel');

        $meeting = OpenMeeting::create([
            'uuid' => $uuid,
            'title' => trim($request->title),
            'date' => $request->date,
            'time_start' => $request->time_start,
            'time_end' => $request->time_end,
            'location' => $request->location ?: 'Mako Detasemen Intelijen Kodaeral V',
            'description' => $request->description,
            'status' => 'open',
            'custom_fields' => $customFields,
            'require_signature' => (bool)$request->require_signature,
            'created_by' => $user?->id,
            'creator_name' => $creatorName,
            'creator_pangkat_nrp' => $creatorPangkatNrp,
            'creator_jabatan' => $creatorJabatan,
            'commander_name' => $commander['name'],
            'commander_pangkat_nrp' => $commander['pangkat_nrp'],
            'commander_jabatan' => $commander['jabatan'],
        ]);

        return redirect()->route('rapat-terbuka.show', $meeting->id)->with('success', 'Form absensi rapat terbuka berhasil dibuat. QR Code siap digunakan.');
    }

    /**
     * Detail sesi rapat terbuka, live rekap peserta & QR Code
     */
    public function show(Request $request, $id)
    {
        $meeting = OpenMeeting::with(['attendees' => function ($q) {
            $q->orderBy('id', 'asc');
        }, 'creator'])->findOrFail($id);

        $publicUrl = route('absen.public', $meeting->uuid);

        return Inertia::render('OpenMeetings/Show', [
            'meeting' => $meeting,
            'attendees' => $meeting->attendees,
            'publicUrl' => $publicUrl,
        ]);
    }

    /**
     * Halaman edit sesi rapat
     */
    public function edit($id)
    {
        $meeting = OpenMeeting::findOrFail($id);
        $commander = self::getCommanderData();

        return Inertia::render('OpenMeetings/Edit', [
            'meeting' => $meeting,
            'commander' => $commander,
        ]);
    }

    /**
     * Simpan perubahan sesi rapat
     */
    public function update(Request $request, $id)
    {
        $meeting = OpenMeeting::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'time_start' => 'nullable|string|max:10',
            'time_end' => 'nullable|string|max:10',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:open,closed',
            'custom_fields' => 'nullable|array',
            'require_signature' => 'nullable|boolean',
        ]);

        $customFields = [];
        if (!empty($request->custom_fields) && is_array($request->custom_fields)) {
            foreach ($request->custom_fields as $field) {
                if (empty($field['name']) || empty($field['label'])) continue;
                $customFields[] = [
                    'id' => $field['id'] ?? Str::slug($field['name'], '_'),
                    'name' => Str::slug($field['name'], '_'),
                    'label' => trim($field['label']),
                    'type' => $field['type'] ?? 'text',
                    'required' => (bool)($field['required'] ?? false),
                    'placeholder' => $field['placeholder'] ?? '',
                    'options' => $field['options'] ?? [],
                ];
            }
        }

        $meeting->update([
            'title' => trim($request->title),
            'date' => $request->date,
            'time_start' => $request->time_start,
            'time_end' => $request->time_end,
            'location' => $request->location,
            'description' => $request->description,
            'status' => $request->status,
            'custom_fields' => $customFields,
            'require_signature' => (bool)$request->require_signature,
        ]);

        return redirect()->route('rapat-terbuka.show', $meeting->id)->with('success', 'Pengaturan sesi rapat berhasil diperbarui.');
    }

    /**
     * Hapus sesi rapat beserta seluruh daftar hadirnya
     */
    public function destroy($id)
    {
        $meeting = OpenMeeting::findOrFail($id);
        $meeting->delete();

        return redirect()->route('rapat-terbuka.index')->with('success', 'Sesi absensi rapat berhasil dihapus.');
    }

    /**
     * Buka / Tutup absensi rapat terbuka
     */
    public function toggleStatus($id)
    {
        $meeting = OpenMeeting::findOrFail($id);
        $newStatus = $meeting->status === 'open' ? 'closed' : 'open';
        $meeting->update(['status' => $newStatus]);

        $msg = $newStatus === 'open' ? 'Absensi rapat sekarang DIBUKA untuk peserta.' : 'Absensi rapat telah DITUTUP. Peserta luar tidak dapat mengisi form lagi.';

        return back()->with('success', $msg);
    }

    /**
     * Hapus kehadiran peserta tertentu (koreksi admin)
     */
    public function destroyAttendee($id, $attendeeId)
    {
        $meeting = OpenMeeting::findOrFail($id);
        $attendee = OpenMeetingAttendee::where('open_meeting_id', $meeting->id)->findOrFail($attendeeId);
        $attendee->delete();

        return back()->with('success', "Data kehadiran {$attendee->name} berhasil dihapus.");
    }

    /**
     * Halaman publik untuk peserta luar mengisi absensi tanpa login
     */
    public function publicForm($uuid)
    {
        $meeting = OpenMeeting::where('uuid', $uuid)->firstOrFail();

        return view('open_meetings.public_form', [
            'meeting' => $meeting,
        ]);
    }

    /**
     * Proses pengisian formulir absensi oleh peserta luar tanpa login
     */
    public function publicSubmit(Request $request, $uuid)
    {
        $meeting = OpenMeeting::where('uuid', $uuid)->firstOrFail();

        if ($meeting->status === 'closed') {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mohon maaf, sesi absensi untuk rapat ini telah DITUTUP oleh panitia / petugas.',
                ], 422);
            }
            return back()->withErrors(['message' => 'Mohon maaf, sesi absensi rapat ini telah DITUTUP oleh panitia.']);
        }

        // Validasi kolom dasar
        $rules = [
            'name' => 'required|string|max:255',
            'pangkat_nrp' => 'nullable|string|max:100',
            'jabatan' => 'nullable|string|max:255',
            'signature' => 'nullable|string',
        ];

        // Validasi kolom dinamis
        $customFields = $meeting->custom_fields ?? [];
        $customData = [];
        $inputCustom = $request->input('custom', []);

        foreach ($customFields as $field) {
            $fieldName = $field['name'];
            $label = $field['label'];
            $isRequired = !empty($field['required']);

            if ($isRequired) {
                $rules["custom.{$fieldName}"] = 'required';
            }

            $customData[$fieldName] = [
                'label' => $label,
                'value' => $inputCustom[$fieldName] ?? null,
            ];
        }

        $request->validate($rules, [
            'name.required' => 'Nama lengkap wajib diisi.',
            'custom.*.required' => 'Kolom ini wajib diisi sesuai instruksi formulir.',
        ]);

        $attendee = OpenMeetingAttendee::create([
            'open_meeting_id' => $meeting->id,
            'name' => trim($request->name),
            'pangkat_nrp' => $request->pangkat_nrp ? trim($request->pangkat_nrp) : '-',
            'jabatan' => $request->jabatan ? trim($request->jabatan) : '-',
            'custom_data' => $customData,
            'signature' => $request->signature,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'attended_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Presensi berhasil dicatat. Terima kasih atas kehadiran Anda.',
                'attendee' => $attendee,
            ]);
        }

        return back()->with('attendee_success', [
            'name' => $attendee->name,
            'pangkat_nrp' => $attendee->pangkat_nrp,
            'jabatan' => $attendee->jabatan,
            'attended_at' => $attendee->formatted_attended_at,
        ]);
    }

    /**
     * Cetak hasil absensi rapat menjadi PDF dengan format dinas militer resmi
     */
    public function downloadPdf(Request $request, $id)
    {
        $meeting = OpenMeeting::with(['attendees' => function ($q) {
            $q->orderBy('id', 'asc');
        }, 'creator'])->findOrFail($id);

        // Ambil data Komandan terupdate dari database atau snapshot meeting
        $commander = self::getCommanderData();
        $commanderName = $meeting->commander_name ?: $commander['name'];
        $commanderPangkatNrp = $meeting->commander_pangkat_nrp ?: $commander['pangkat_nrp'];
        $commanderJabatan = $meeting->commander_jabatan ?: $commander['jabatan'];

        // Ambil data Petugas Pembuat Absensi
        $creatorName = $meeting->creator_name ?: ($meeting->creator?->name ?? 'Petugas SINDEN');
        $creatorPangkatNrp = $meeting->creator_pangkat_nrp ?: trim(($meeting->creator?->pangkat ?? '') . ($meeting->creator?->nrp ? ' NRP ' . $meeting->creator->nrp : ''));
        $creatorJabatan = $meeting->creator_jabatan ?: ($meeting->creator?->jabatan ?? 'Petugas Sintel');

        // Generate QR Code Verifikasi Dokumen
        $verifyUrl = route('absen.public', $meeting->uuid);
        $qr_base64 = null;
        try {
            $qrData = @file_get_contents('https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($verifyUrl));
            if ($qrData) {
                $qr_base64 = 'data:image/png;base64,' . base64_encode($qrData);
            }
        } catch (\Exception $e) {
            Log::warning("Gagal fetch QR verification: " . $e->getMessage());
        }

        // Tentukan orientasi kertas:
        // Jika kolom kustom lebih dari 1 atau ada parameter orientation=landscape, gunakan landscape
        $customFieldsCount = count($meeting->custom_fields ?? []);
        $orientation = $request->query('orientation', ($customFieldsCount > 1 ? 'landscape' : 'portrait'));

        $pdf = Pdf::loadView('pdf.open_meeting_attendance', [
            'meeting' => $meeting,
            'attendees' => $meeting->attendees,
            'commanderName' => $commanderName,
            'commanderPangkatNrp' => $commanderPangkatNrp,
            'commanderJabatan' => $commanderJabatan,
            'creatorName' => $creatorName,
            'creatorPangkatNrp' => $creatorPangkatNrp,
            'creatorJabatan' => $creatorJabatan,
            'qr_base64' => $qr_base64,
            'verifyUrl' => $verifyUrl,
            'orientation' => $orientation,
        ]);

        // Gunakan ukuran Folio (215mm x 330mm) standar TNI
        if ($orientation === 'landscape') {
            $pdf->setPaper([0, 0, 609.45, 935.43], 'landscape');
        } else {
            $pdf->setPaper([0, 0, 609.45, 935.43], 'portrait');
        }

        $safeTitle = Str::slug($meeting->title, '_');
        $safeDate = Carbon::parse($meeting->date)->format('Ymd');
        $fileName = "DAFTAR_HADIR_RAPAT_{$safeTitle}_{$safeDate}.pdf";

        if ($request->query('stream') === '1') {
            return $pdf->stream($fileName);
        }

        return $pdf->download($fileName);
    }
}
