<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pesan exception internal (SQL, path server, dsb.) tidak ditampilkan ke pengguna (audit R05).
 * Detail dicatat di log dengan kode referensi pendek yang ditampilkan ke pengguna.
 */
class ErrorRef
{
    public static function log(\Throwable $e): string
    {
        $ref = strtoupper(Str::random(8));
        Log::error("[ERR-{$ref}] " . $e->getMessage(), ['exception' => get_class($e), 'at' => $e->getFile() . ':' . $e->getLine()]);
        return "kode ERR-{$ref}";
    }
}
