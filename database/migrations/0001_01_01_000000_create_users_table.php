<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role');
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->timestampTz('locked_until')->nullable();
            $table->string('refresh_token_hash')->nullable();
            $table->timestampTz('refresh_token_expires_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
