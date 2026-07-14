<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox', function (Blueprint $table) {
            $table->uuid('outbox_id')->primary();
            $table->uuid('event_id')->notNull()->unique();
            $table->uuid('tenant_id')->notNull();
            $table->string('event_type')->notNull();
            $table->string('event_version')->default('1.0');
            $table->jsonb('payload')->notNull();
            $table->string('status')->default('pending');
            $table->integer('retry_count')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('published_at')->nullable();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox');
    }
};
