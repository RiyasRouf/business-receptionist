<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('lead_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->uuid('session_id')->notNull();
            $table->string('status')->nullable();
            $table->jsonb('fields_json')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->cascadeOnDelete();
            $table->foreign('session_id')->references('session_id')->on('sessions')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
