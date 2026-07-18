<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->uuid('provider_id')->primary();
            $table->string('name');
            $table->string('api_key')->nullable();
            $table->string('base_url')->nullable();
            $table->string('status')->default('active');
            $table->timestampsTz();
        });

        Schema::create('ai_provider_models', function (Blueprint $table) {
            $table->uuid('model_id')->primary();
            $table->uuid('provider_id');
            $table->string('name');
            $table->decimal('input_cost_per_1m', 10, 4)->default(0);
            $table->decimal('output_cost_per_1m', 10, 4)->default(0);
            $table->timestampsTz();

            $table->foreign('provider_id')->references('provider_id')->on('ai_providers')->cascadeOnDelete();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->uuid('ai_provider_model_id')->nullable()->after('country');
            $table->foreign('ai_provider_model_id')->references('model_id')->on('ai_provider_models')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['ai_provider_model_id']);
            $table->dropColumn('ai_provider_model_id');
        });
        Schema::dropIfExists('ai_provider_models');
        Schema::dropIfExists('ai_providers');
    }
};
