<?php

namespace App\Http\Controllers;

use App\Models\ScSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PublicTrackingController extends Controller
{
    /**
     * Halaman Publik Pelacakan Status Pengajuan Security Clearance (SC)
     */
    public function index(Request $request)
    {
        $identifier = trim($request->input('identifier', ''));
        $submission = null;
        $allSubmissions = collect();
        $notFound = false;

        if (!Schema::hasTable('sc_submissions')) {
            return Inertia::render('Public/TrackingSc', [
                'submission' => null,
                'all_submissions' => $allSubmissions,
                'searched_identifier' => $identifier,
                'not_found' => !empty($identifier),
                'stages' => array_values(ScSubmission::STAGES),
            ]);
        }

        if (!empty($identifier)) {
            $cleanNumber = str_replace([' ', '-', '.', '/'], '', $identifier);

            // Cari berdasarkan identifier_number atau tracking_code
            $results = ScSubmission::with(['logs'])
                ->where(function ($q) use ($identifier, $cleanNumber) {
                    $q->where('identifier_number', $identifier)
                      ->orWhereRaw("REPLACE(REPLACE(REPLACE(identifier_number, ' ', ''), '-', ''), '.', '') = ?", [$cleanNumber])
                      ->orWhere('tracking_code', $identifier);
                })
                ->orderBy('id', 'desc')
                ->get();

            if ($results->isNotEmpty()) {
                foreach ($results as $subItem) {
                    $subItem->checkAndPurgeExpiredPreview();
                }
                $allSubmissions = $results;
                $submission = $results->first();
            } else {
                $notFound = true;
            }
        }

        return Inertia::render('Public/TrackingSc', [
            'submission' => $submission,
            'all_submissions' => $allSubmissions,
            'searched_identifier' => $identifier,
            'not_found' => $notFound,
            'stages' => array_values(ScSubmission::STAGES),
        ]);
    }

    /**
     * Streaming Pratinjau Aman Berkas PDF Hasil SC (Anti-Download & Kedaluwarsa 48 Jam)
     */
    public function previewPdf($trackingCode)
    {
        $submission = ScSubmission::where('tracking_code', $trackingCode)->firstOrFail();

        // Periksa apakah masa aktif 2x24 jam telah berakhir
        if ($submission->checkAndPurgeExpiredPreview() || !$submission->is_sc_preview_available) {
            abort(410, 'Masa berlaku petinjau berkas Security Clearance (2x24 jam) telah berakhir.');
        }

        if (!Storage::disk('public')->exists($submission->file_sc_preview)) {
            abort(404, 'Berkas petinjau SC tidak ditemukan.');
        }

        $path = Storage::disk('public')->path($submission->file_sc_preview);

        // Sajikan berkas secara inline tanpa tombol simpan / unduh langsung
        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="PETINJAU_SC_' . $submission->tracking_code . '.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, nosnippet',
        ]);
    }
}
