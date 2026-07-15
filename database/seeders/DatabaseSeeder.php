<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Sprint 6 UAT seed data — idempotent (firstOrCreate) so re-running on
 * every deploy is safe. No production seeder exists yet; this is
 * staging-only until Module 19 (go-live) defines what, if anything,
 * ships to production.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'platform-admin@aiwa.test'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_PLATFORM_ADMIN,
                'tenant_id' => null,
                'email_verified_at' => now(),
            ]
        );

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo-school'],
            ['status' => 'active']
        );

        User::firstOrCreate(
            ['email' => 'school-admin@aiwa.test'],
            [
                'name' => 'Demo School Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_TENANT_ADMIN,
                'tenant_id' => $tenant->tenant_id,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'staff@aiwa.test'],
            [
                'name' => 'Demo Staff',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STAFF,
                'tenant_id' => $tenant->tenant_id,
                'email_verified_at' => now(),
            ]
        );
    }
}
