<?php

namespace App\Services;

/** Kesalahan alur minta akses penyimpanan yang aman ditampilkan ke pengguna. */
class PcAccessException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
