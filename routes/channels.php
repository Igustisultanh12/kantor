<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Kanal sinyal realtime "data berubah" (lihat App\Support\Realtime). Hanya akun aktif.
Broadcast::channel('sinden', function ($user) {
    return (bool) ($user->is_active ?? true);
});
