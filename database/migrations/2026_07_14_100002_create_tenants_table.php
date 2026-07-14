<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('tenant_id')->primary();
            $table->string('slug')->unique();
            $table->uuid('plan_id')->nullable();
            $table->string('status')->default('active');
            $table->jsonb('limits_json')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('plan_id')->references('plan_id')->on('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
