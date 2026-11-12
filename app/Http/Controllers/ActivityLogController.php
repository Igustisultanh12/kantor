<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/** Log Aktivitas (khusus admin): jejak aksi pengguna terhadap data + cetak PDF. */
class ActivityLogController extends Controller
{
    private const PDF_LIMIT = 1500;

    public function index(Request $request)
    {
        ActivityLog::ensureSchema();
        $filters = $this->filters($request);

        $logs = $this->query($filters)->latest('id')->paginate(50)->withQueryString()
            ->through(fn (ActivityLog $l) => $this->row($l));

        return Inertia::render('ActivityLogs/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::orderBy('name')->get(['id', 'name', 'pangkat', 'nrp']),
            'modules' => ActivityLog::query()->whereIn('module', ActivityLog::IMPORTANT_MODULES)->distinct()->orderBy('module')->pluck('module'),
            'pdfLimit' => self::PDF_LIMIT,
        ]);
    }

    public function pdf(Request $request)
    {
        ActivityLog::ensureSchema();
        $filters = $this->filters($request);
        $query = $this->query($filters);
        $total = (clone $query)->count();
        $logs = $query->oldest('id')->limit(self::PDF_LIMIT)->get();

        $commander = OpenMeetingController::getCommanderData();
        $printer = $request->user();
        $user = $filters['user_id'] ? User::find($filters['user_id']) : null;

        $pdf = Pdf::loadView('pdf.activity_logs', [
            'logs' => $logs,
            'total' => $total,
            'limit' => self::PDF_LIMIT,
            'filters' => $filters,
            'filterUser' => $user,
            'period' => $this->periodLabel($filters),
            'printedAt' => now(),
            'printerName' => $printer->name,
            'printerPangkatNrp' => trim(($printer->pangkat ?? '') . ($printer->nrp ? ' NRP ' . $printer->nrp : '')),
            'printerJabatan' => $printer->jabatan ?? 'Administrator SISINDEN',
            'commanderName' => $commander['name'],
            'commanderPangkatNrp' => $commander['pangkat_nrp'],
        ]);
        $pdf->setPaper([0, 0, 609.45, 935.43], 'landscape'); // Folio 215 x 330 mm, sama dengan daftar hadir rapat

        return $pdf->stream('log_aktivitas_' . now()->format('Ymd_His') . '.pdf');
    }

    private function filters(Request $request): array
    {
        $date = fn ($v) => $v && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : null;
        return [
            'search' => trim((string) $request->query('search', '')) ?: null,
            'user_id' => $request->integer('user_id') ?: null,
            'module' => trim((string) $request->query('module', '')) ?: null,
            'channel' => in_array($request->query('channel'), ['web', 'android'], true) ? $request->query('channel') : null,
            'status' => in_array($request->query('status'), ['success', 'failed'], true) ? $request->query('status') : null,
            'from' => $date($request->query('from')),
            'to' => $date($request->query('to')),
        ];
    }

    private function query(array $f): Builder
    {
        return ActivityLog::query()
            ->whereIn('module', ActivityLog::IMPORTANT_MODULES)
            ->when($f['user_id'], fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['module'], fn ($q, $v) => $q->where('module', $v))
            ->when($f['channel'], fn ($q, $v) => $q->where('channel', $v))
            ->when($f['status'], fn ($q, $v) => $q->where('success', $v === 'success'))
            ->when($f['from'], fn ($q, $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($f['to'], fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($f['search'], function ($q, $v) {
                $like = '%' . addcslashes($v, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('user_name', 'like', $like)->orWhere('nrp', 'like', $like)
                    ->orWhere('action', 'like', $like)->orWhere('path', 'like', $like)
                    ->orWhere('ip', 'like', $like)->orWhere('device', 'like', $like));
            });
    }

    private function row(ActivityLog $l): array
    {
        return [
            'id' => $l->id,
            'at' => $l->created_at?->toIso8601String(),
            'user' => ['id' => $l->user_id, 'name' => $l->user_name, 'pangkat' => $l->pangkat, 'nrp' => $l->nrp, 'role' => $l->role],
            'channel' => $l->channel,
            'module' => $l->module,
            'action' => $l->action,
            'method' => $l->method,
            'path' => $l->path,
            'subject' => $l->subject,
            'payload' => $l->payload,
            'status' => $l->status,
            'success' => (bool) $l->success,
            'ip' => $l->ip,
            'ip_location' => $l->ip_location,
            'device' => $l->device,
            'user_agent' => $l->user_agent,
            'location' => $l->latitude !== null ? ['lat' => $l->latitude, 'lng' => $l->longitude, 'accuracy' => $l->location_accuracy, 'at' => $l->location_at?->toIso8601String()] : null,
            'duration_ms' => $l->duration_ms,
        ];
    }

    private function periodLabel(array $f): string
    {
        $fmt = fn ($d) => Carbon::parse($d)->translatedFormat('j F Y');
        return match (true) {
            $f['from'] && $f['to'] => $fmt($f['from']) . ' s.d. ' . $fmt($f['to']),
            (bool) $f['from'] => 'Sejak ' . $fmt($f['from']),
            (bool) $f['to'] => 'Sampai ' . $fmt($f['to']),
            default => 'Semua waktu',
        };
    }
}
