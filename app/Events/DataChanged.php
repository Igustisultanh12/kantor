<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Sinyal realtime "data modul X berubah". Hanya nama modul yang dikirim,
 * bukan isi data: klien memuat ulang lewat server yang tetap memeriksa hak akses.
 */
class DataChanged implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    /** @param list<string> $modules */
    public function __construct(public array $modules)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('sinden')];
    }

    public function broadcastAs(): string
    {
        return 'changed';
    }

    public function broadcastWith(): array
    {
        return ['modules' => array_values($this->modules), 'at' => now()->getTimestampMs()];
    }
}
