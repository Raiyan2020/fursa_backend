<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            // BE-75 — how long after a session's scheduled end a self-scan
            // departure is still accepted, admin-tunable rather than hardcoded.
            if (! Schema::hasColumn('configs', 'self_check_out_grace_hours')) {
                $table->unsignedInteger('self_check_out_grace_hours')->default(2);
            }
        });
    }

    public function down(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            if (Schema::hasColumn('configs', 'self_check_out_grace_hours')) {
                $table->dropColumn('self_check_out_grace_hours');
            }
        });
    }
};
