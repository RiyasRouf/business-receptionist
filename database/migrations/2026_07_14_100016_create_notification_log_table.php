<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_log', function (Blueprint $table) {
            $table->uuid('notification_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->string('channel')->notNull();
            $table->string('recipient')->nullable();
            $table->string('status')->default('pending');
            $table->jsonb('payload_json')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_log');
    }
};
