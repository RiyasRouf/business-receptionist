<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DatabaseSeeder's firstOrCreate only sets attributes when CREATING a
 * row — staff@aiwa.test predates custom_role_id existing at all, so
 * every later seeder change to give it one was silently a no-op
 * (matched on email, row already existed, attributes ignored). Found
 * by actually logging in as it and checking /team access instead of
 * just trusting the seeder diff. Direct data fix here, not seeder-
 * dependent, so it isn't lost to the same gotcha again.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tenant = DB::table('tenants')->where('slug', 'demo-school')->first();

        if ($tenant === null) {
            return;
        }

        $role = DB::table('tenant_roles')
            ->where('tenant_id', $tenant->tenant_id)
            ->where('name', 'Staff')
            ->first();

        if ($role === null) {
            $roleId = (string) Str::orderedUuid();
            DB::table('tenant_roles')->insert([
                'role_id' => $roleId,
                'tenant_id' => $tenant->tenant_id,
                'name' => 'Staff',
                'permissions_json' => json_encode(['leads', 'transcripts']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $roleId = $role->role_id;
        }

        DB::table('users')
            ->where('email', 'staff@aiwa.test')
            ->whereNull('custom_role_id')
            ->update(['custom_role_id' => $roleId]);
    }

    public function down(): void
    {
        // Not reversible — don't know what custom_role_id, if any, was
        // there before.
    }
};
