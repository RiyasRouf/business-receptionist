<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_roles', function (Blueprint $table) {
            $table->uuid('role_id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->jsonb('permissions_json')->default('[]');
            $table->timestampsTz();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('custom_role_id')->nullable()->after('job_title');
            $table->foreign('custom_role_id')->references('role_id')->on('tenant_roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['custom_role_id']);
            $table->dropColumn('custom_role_id');
        });
        Schema::dropIfExists('tenant_roles');
    }
};
