<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
    \App\Models\Setting::create([
        'key' => 'format_rahasia',
        'value' => 'R/{no}/{code}/{month}/{year}'
    ]);
    \App\Models\Setting::create([
        'key' => 'format_biasa',
        'value' => 'B/{no}/{code}/{month}/{year}'
    ]);
    }
}
