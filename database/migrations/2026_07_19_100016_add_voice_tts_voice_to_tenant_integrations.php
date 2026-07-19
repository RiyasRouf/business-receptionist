<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_integrations', function (Blueprint $table) {
            $table->string('voice_tts_voice')->default('Polly.Joanna');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_integrations', function (Blueprint $table) {
            $table->dropColumn('voice_tts_voice');
        });
    }
};
