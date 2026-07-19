<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // resource_id was uuid-only, but non-tenant-scoped resources
        // (platform_settings, ai_provider_models, ...) use integer/string
        // ids — every audit() call for those threw "invalid input syntax
        // for type uuid" and 500'd the whole request (branding save/logo
        // upload included). Widen to text so any resource id fits.
        if (Schema::hasColumn('audit_log', 'resource_id')) {
            DB::statement('ALTER TABLE audit_log ALTER COLUMN resource_id TYPE varchar(255) USING resource_id::text');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE audit_log ALTER COLUMN resource_id TYPE uuid USING resource_id::uuid');
    }
};
