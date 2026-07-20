<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // api_key was stored plaintext (only 'hidden', no encryption).
        // Encrypt existing values in place so the new 'encrypted' cast can
        // read them, and widen the column since ciphertext exceeds 255.
        DB::statement('ALTER TABLE ai_providers ALTER COLUMN api_key TYPE text');

        DB::table('ai_providers')->whereNotNull('api_key')->get()->each(function ($row) {
            // Skip values that already look encrypted (idempotent re-run).
            $looksEncrypted = str_starts_with((string) $row->api_key, 'eyJ');
            if (! $looksEncrypted && $row->api_key !== '') {
                DB::table('ai_providers')->where('provider_id', $row->provider_id)
                    ->update(['api_key' => Crypt::encryptString($row->api_key)]);
            }
        });
    }

    public function down(): void
    {
        // Not reversible — plaintext originals are gone by design.
    }
};
