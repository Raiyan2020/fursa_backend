<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-61 Part A — self check-in QR for volunteering.
 *
 * Two permanent, printable codes live on the opportunity itself (the printed
 * sheet), while the two timestamps that make "real hours" possible live on
 * the attendance row already keyed by (registration_id, attended_date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteer_opportunities', function (Blueprint $table) {
            if (! Schema::hasColumn('volunteer_opportunities', 'attendance_code_in')) {
                $table->string('attendance_code_in', 64)->nullable()->unique();
            }
            if (! Schema::hasColumn('volunteer_opportunities', 'attendance_code_out')) {
                $table->string('attendance_code_out', 64)->nullable()->unique();
            }
        });

        Schema::table('volunteer_opportunity_attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('volunteer_opportunity_attendances', 'checked_in_at')) {
                $table->dateTime('checked_in_at')->nullable();
            }
            if (! Schema::hasColumn('volunteer_opportunity_attendances', 'checked_out_at')) {
                $table->dateTime('checked_out_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('volunteer_opportunities', function (Blueprint $table) {
            foreach (['attendance_code_in', 'attendance_code_out'] as $column) {
                if (Schema::hasColumn('volunteer_opportunities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('volunteer_opportunity_attendances', function (Blueprint $table) {
            foreach (['checked_in_at', 'checked_out_at'] as $column) {
                if (Schema::hasColumn('volunteer_opportunity_attendances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
