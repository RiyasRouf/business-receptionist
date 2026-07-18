<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('brand_logo_path')->nullable()->after('brand_tagline');
        });

        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('tagline');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('brand_logo_path');
        });
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
