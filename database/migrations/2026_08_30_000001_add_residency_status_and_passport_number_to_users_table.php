<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client requirement: a non-Kuwaiti volunteer must declare residency status,
 * which then decides which identifier is mandatory —
 *   resident      -> civil_id (already required, unchanged)
 *   non_resident  -> passport_number (new)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('residency_status', 20)->nullable()->after('nationality');
            $table->string('passport_number', 20)->nullable()->unique()->after('civil_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['residency_status', 'passport_number']);
        });
    }
};
