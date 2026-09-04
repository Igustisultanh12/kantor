<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jadwal Rutin Otomatis: Pengingat Pembayaran Cicilan Koperasi SINDEN Setiap Tanggal 1 Pukul 07:00 WIB
Schedule::command('koperasi:send-reminders')->monthlyOn(1, '07:00');
