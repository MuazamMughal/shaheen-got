<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('shaheen.super_admin');

        if (blank($config['email']) || blank($config['password'])) {
            throw new RuntimeException('Set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD in .env before seeding.');
        }

        $user = User::firstOrNew(['email' => $config['email']]);
        $user->fill(['name' => $config['name']]);

        // Only set the password on first creation; never overwrite a changed one.
        if (! $user->exists) {
            $user->password = $config['password'];
        }

        $user->forceFill([
            'type' => UserType::SuperAdmin,
            'status' => UserStatus::Approved,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'approved_at' => $user->approved_at ?? now(),
        ])->save();
    }
}
