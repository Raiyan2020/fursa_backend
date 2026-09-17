<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-61 Part B — self check-in QR for learn-&-serve.
 *
 * A single code issued on the opportunity's last day, valid two hours.
 * Unlike volunteering, no attendance schema change is needed: a scan just
 * flips the existing `is_attended` boolean on the registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learn_serve_opportunities', function (Blueprint $table) {
            if (! Schema::hasColumn('learn_serve_opportunities', 'attendance_code')) {
                $table->string('attendance_code', 64)->nullable()->unique();
            }
            if (! Schema::hasColumn('learn_serve_opportunities', 'attendance_code_expires_at')) {
                $table->dateTime('attendance_code_expires_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('learn_serve_opportunities', function (Blueprint $table) {
            foreach (['attendance_code', 'attendance_code_expires_at'] as $column) {
                if (Schema::hasColumn('learn_serve_opportunities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
