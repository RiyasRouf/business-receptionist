<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('summaries', function (Blueprint $table) {
            $table->string('caller_intent')->nullable();
            $table->string('sentiment', 16)->nullable();
            $table->string('outcome', 32)->nullable();
            $table->boolean('callback_requested')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('summaries', function (Blueprint $table) {
            $table->dropColumn(['caller_intent', 'sentiment', 'outcome', 'callback_requested']);
        });
    }
};
