<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_documents', function (Blueprint $table) {
            $table->uuid('document_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->string('title');
            $table->string('storage_path');
            $table->string('status')->default('pending');
            $table->jsonb('metadata_json')->nullable();
            $table->timestampsTz();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_documents');
    }
};
