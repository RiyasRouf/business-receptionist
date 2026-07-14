<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * fields_json values are individually PII-encrypted (ADR-035) with a
     * random nonce per encrypt() call — the same phone number produces
     * different ciphertext every time, so it can't be queried directly
     * for deduplication (F-06/Sprint 3 AC). A SHA-256 hash of the
     * normalised phone number, stored alongside, gives an indexable
     * lookup without storing the plaintext number outside the
     * encrypted field. One-way hash, not reversible, but a phone number
     * has a small enough keyspace to be brute-forced by anyone with
     * this column — same accepted tradeoff as password reset tokens;
     * flagged, not silently assumed safe.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('phone_hash', 64)->nullable()->after('fields_json');
            $table->index(['tenant_id', 'phone_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'phone_hash']);
            $table->dropColumn('phone_hash');
        });
    }
};
