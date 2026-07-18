<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('brand_name')->nullable()->after('name');
            $table->string('brand_color', 7)->nullable()->after('brand_name');
            $table->string('brand_tagline')->nullable()->after('brand_color');
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Business AI');
            $table->string('color', 7)->default('#6366F1');
            $table->string('tagline')->default('AI Business Receptionist Platform');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['brand_name', 'brand_color', 'brand_tagline']);
        });
        Schema::dropIfExists('platform_settings');
    }
};
