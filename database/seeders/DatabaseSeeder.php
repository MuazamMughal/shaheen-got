<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            PermissionSeeder::class,
            SuperAdminSeeder::class,
        ]);

        // Demo master data for local development only.
        if (app()->environment('local')) {
            $this->call(MasterDataSeeder::class);
        }
    }
}
