<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Display-only label (e.g. "Admissions Officer") — decorative in
            // Phase 1, does not affect permissions. Real custom role/permission
            // system is a later phase (see Milestone 6 UI/UX spec, ba-roles).
            $table->string('job_title')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('job_title');
        });
    }
};
