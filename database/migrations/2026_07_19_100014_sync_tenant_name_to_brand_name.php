<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // brand_name is now the single display name; backfill the legacy
        // `name` column for tenants that customized branding before the
        // two were kept in sync (fixes "Demo School" vs "Indian School RAK").
        DB::statement("UPDATE tenants SET name = brand_name WHERE brand_name IS NOT NULL AND brand_name <> ''");
    }

    public function down(): void
    {
        // Non-reversible — prior divergent `name` values are not recoverable.
    }
};
