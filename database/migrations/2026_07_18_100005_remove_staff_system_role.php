<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Only 2 system roles remain: platform_admin, tenant_admin. "staff"
 * was never a real Postgres enum (role is a plain string column), so
 * this is a data migration, not a schema change — every existing
 * staff row becomes tenant_admin. Their actual access is now governed
 * entirely by custom_role_id + tenant_roles.permissions_json
 * (business_admin-managed), enforced by EnforcePermission middleware.
 * A tenant_admin row with custom_role_id = null is the unrestricted
 * admin; one with it set is permission-limited.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'staff')->update(['role' => 'tenant_admin']);
    }

    public function down(): void
    {
        // Not reversible — original staff/tenant_admin split is lost.
    }
};
