<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table) {
            $table->uuid('usage_event_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->uuid('session_id')->nullable();
            $table->string('event_type')->notNull();
            $table->integer('quantity')->nullable();
            $table->string('unit')->nullable();
            $table->timestampTz('recorded_at')->useCurrent();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->cascadeOnDelete();
            $table->foreign('session_id')->references('session_id')->on('sessions')->nullOnDelete();
            $table->index(['tenant_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
