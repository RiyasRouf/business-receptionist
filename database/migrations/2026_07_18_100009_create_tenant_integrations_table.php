<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_integrations', function (Blueprint $table) {
            $table->uuid('integration_id')->primary();
            $table->uuid('tenant_id')->unique();

            $table->string('voice_provider')->nullable();
            $table->string('voice_account_sid')->nullable();
            $table->string('voice_auth_token')->nullable();
            $table->string('voice_phone_number')->nullable();
            $table->string('voice_status')->default('not_configured');
            $table->string('call_forwarding_type')->nullable();
            $table->string('business_hours')->nullable();
            $table->text('fallback_message')->nullable();

            $table->string('whatsapp_number')->nullable();
            $table->string('whatsapp_display_name')->nullable();
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->string('whatsapp_token')->nullable();
            $table->text('whatsapp_greeting')->nullable();
            $table->string('whatsapp_status')->default('not_configured');

            $table->timestampsTz();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_integrations');
    }
};
