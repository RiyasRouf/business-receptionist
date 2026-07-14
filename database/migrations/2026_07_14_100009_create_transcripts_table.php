<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcripts', function (Blueprint $table) {
            $table->uuid('transcript_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->uuid('session_id')->notNull();
            $table->text('content')->nullable();
            $table->string('storage_path')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->cascadeOnDelete();
            $table->foreign('session_id')->references('session_id')->on('sessions')->cascadeOnDelete();
            $table->index(['tenant_id', 'session_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripts');
    }
};
