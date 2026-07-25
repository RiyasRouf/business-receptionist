<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Infobip reuses voice_account_sid (as callsConfigurationId) and
// voice_api_key (as the Infobip API key) from the Twilio-shaped columns —
// only the account base URL and a webhook auth secret are Infobip-specific.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_integrations', function (Blueprint $table) {
            $table->string('voice_base_url')->nullable()->after('voice_region');
            $table->string('voice_webhook_secret')->nullable()->after('voice_base_url');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_integrations', function (Blueprint $table) {
            $table->dropColumn(['voice_base_url', 'voice_webhook_secret']);
        });
    }
};
