<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'vector'");

        if ($exists) {
            return;
        }

        // Requires superuser in most managed Postgres setups. Staging/production
        // provision this once via an admin connection; the app's runtime DB role
        // intentionally has no CREATE EXTENSION privilege (least privilege).
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS vector');
    }
};
