<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /** Inserts defaults that are missing; never overwrites admin changes. */
    public function run(): void
    {
        foreach (SettingsService::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['group' => explode('.', $key)[0], 'value' => $value],
            );
        }

        app(SettingsService::class)->flush();
    }
}
