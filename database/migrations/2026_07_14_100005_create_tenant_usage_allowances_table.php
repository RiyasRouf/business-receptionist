<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_usage_allowances', function (Blueprint $table) {
            $table->uuid('allowance_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->string('allowance_type');
            $table->integer('limit');
            $table->integer('grace')->default(0);
            $table->unsignedTinyInteger('warning_threshold_pct')->default(80);
            $table->string('reset_period')->default('monthly');
            $table->unsignedTinyInteger('reset_day')->default(1);
            $table->timestampsTz();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_allowances');
    }
};
