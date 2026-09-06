<?php

namespace App\Http\Controllers;

use App\Models\ScSubmission;
use App\Services\PdfWatermarkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        ScSubmission::ensureSchema();
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
            try {
                $cleanNumber = str_replace([' ', '-', '.', '/'], '', $identifier);
                $withRelations = Schema::hasTable('sc_submission_logs') ? ['logs'] : [];

                // Cari berdasarkan identifier_number atau tracking_code
                $results = ScSubmission::with($withRelations)
                    ->where(function ($q) use ($identifier, $cleanNumber) {
                        $q->where('identifier_number', $identifier)
                          ->orWhereRaw("REPLACE(REPLACE(REPLACE(identifier_number, ' ', ''), '-', ''), '.', '') = ?", [$cleanNumber])
                          ->orWhere('tracking_code', $identifier);
                        if (Schema::hasColumn('sc_submissions', 'nomor_resi')) {
                            $q->orWhere('nomor_resi', $identifier);
                        }
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
            } catch (\Throwable $e) {
                Log::error('Galat pelacakan SC publik: ' . $e->getMessage());
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
     * Membuat Token Akses Rahasia Sementara untuk Petinjau Berkas SC (Masa Berlaku 15 Menit)
     */
    public function generatePreviewToken(Request $request)
    {
        $request->validate([
            'tracking_code' => 'required|string',
        ]);

        $trackingCode = trim($request->input('tracking_code'));

        $submission = ScSubmission::where(function ($q) use ($trackingCode) {
            $q->where('tracking_code', $trackingCode);
            if (Schema::hasColumn('sc_submissions', 'nomor_resi')) {
                $q->orWhere('nomor_resi', $trackingCode);
            }
        })->first();

        if (!$submission) {
            return response()->json(['error' => 'Data pengajuan berkas tidak ditemukan.'], 404);
        }

        if ($submission->checkAndPurgeExpiredPreview() || !$submission->is_sc_preview_available) {
            return response()->json(['error' => 'Masa berlaku petinjau dokumen (2x24 jam) telah berakhir.'], 410);
        }

        if (!$submission->file_sc_preview || !Storage::disk('public')->exists($submission->file_sc_preview)) {
            return response()->json(['error' => 'Softfile petinjau SC belum tersedia atau telah terhapus.'], 404);
        }

        // Buat token rahasia acak berkeamanan tinggi (64 karakter heksadesimal)
        $token = bin2hex(random_bytes(32));

        // Simpan token ke dalam cache dengan masa berlaku singkat (15 menit)
        Cache::put("sc_preview_{$token}", $submission->id, now()->addMinutes(15));

        return response()->json([
            'success' => true,
            'stream_url' => '/tracking-sc/stream/' . $token,
        ]);
    }

    /**
     * Streaming Pratinjau Aman Berkas PDF Ber-Watermark Resmi (Anti-Download, Header Ketat, Token Rahasia)
     */
    public function streamSecurePdf($token)
    {
        $submissionId = Cache::get("sc_preview_{$token}");

        if (!$submissionId) {
            abort(403, 'Akses petinjau kedinasan tidak valid atau tautan rahasia telah kedaluwarsa.');
        }

        $submission = ScSubmission::findOrFail($submissionId);

        if ($submission->checkAndPurgeExpiredPreview() || !$submission->is_sc_preview_available) {
            abort(410, 'Masa berlaku petinjau berkas Security Clearance (2x24 jam) telah berakhir.');
        }

        if (!$submission->file_sc_preview || !Storage::disk('public')->exists($submission->file_sc_preview)) {
            abort(404, 'Berkas petinjau SC tidak ditemukan.');
        }

        // Pastikan watermark stempel PETINJAU telah menempel di dalam berkas PDF
        $watermarkedRelativePath = PdfWatermarkService::applyWatermark($submission->file_sc_preview) ?? $submission->file_sc_preview;
        $path = Storage::disk('public')->path($watermarkedRelativePath);

        // Sajikan berkas secara inline dengan proteksi header anti-unduh kedinasan
        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="PETINJAU_KEDINASAN.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Robots-Tag' => 'noindex, nofollow, nosnippet',
        ]);
    }

    /**
     * Streaming Pratinjau Berkas PDF (Dukungan Kompatibilitas Admin)
     */
    public function previewPdf($trackingCode)
    {
        $submission = ScSubmission::where(function ($q) use ($trackingCode) {
            $q->where('tracking_code', $trackingCode);
            if (Schema::hasColumn('sc_submissions', 'nomor_resi')) {
                $q->orWhere('nomor_resi', $trackingCode);
            }
        })->firstOrFail();

        if ($submission->checkAndPurgeExpiredPreview() || !$submission->is_sc_preview_available) {
            abort(410, 'Masa berlaku petinjau berkas Security Clearance (2x24 jam) telah berakhir.');
        }

        if (!Storage::disk('public')->exists($submission->file_sc_preview)) {
            abort(404, 'Berkas petinjau SC tidak ditemukan.');
        }

        // Terapkan watermark stempel PETINJAU fisik ke PDF
        $watermarkedRelativePath = PdfWatermarkService::applyWatermark($submission->file_sc_preview) ?? $submission->file_sc_preview;
        $path = Storage::disk('public')->path($watermarkedRelativePath);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="PETINJAU_KEDINASAN.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }
}
