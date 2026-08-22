<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SignatureRequest;
use Illuminate\Support\Str;

class SanitizeSignatureCodes extends Command
{
    protected $signature = 'sinden:sanitize-codes';
    protected $description = 'Sanitize old NRP verification codes in signature_requests table into unique document codes';

    public function handle()
    {
        $requests = SignatureRequest::all();
        $count = 0;

        foreach ($requests as $sig) {
            $code = $sig->verification_code;
            if (empty($code) || is_numeric($code) || strlen($code) > 20 || !str_starts_with($code, 'TTE-DOC-')) {
                $dateStr = $sig->created_at ? $sig->created_at->format('Ymd') : date('Ymd');
                $newCode = 'TTE-DOC-' . $dateStr . '-' . strtoupper(substr(md5($sig->id . ($sig->created_at ?? now())), 0, 6));
                $sig->update(['verification_code' => $newCode]);
                $this->info("Updated SignatureRequest ID {$sig->id}: {$code} -> {$newCode}");
                $count++;
            }
        }

        $this->info("Sanitization complete. Updated {$count} records.");
        return 0;
    }
}