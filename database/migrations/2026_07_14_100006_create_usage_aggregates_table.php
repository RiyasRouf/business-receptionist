<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_aggregates', function (Blueprint $table) {
            $table->uuid('aggregate_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->string('allowance_type');
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('consumed')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'allowance_type', 'period_start']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_aggregates');
    }
};
