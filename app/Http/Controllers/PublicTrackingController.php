<?php

namespace App\Http\Controllers;

use App\Models\ScSubmission;
use Illuminate\Http\Request;
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
}
