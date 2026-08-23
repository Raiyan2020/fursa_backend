<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            // Client asked to extend the default check-in window from 48h to 72h.
            // Stored in hours so the admin can tune it without a redeploy.
            if (! Schema::hasColumn('configs', 'preparation_validity_hours')) {
                $table->unsignedInteger('preparation_validity_hours')->default(72);
            }
            if (! Schema::hasColumn('configs', 'preparation_reminder_hours_before')) {
                $table->unsignedInteger('preparation_reminder_hours_before')->default(12);
            }
        });

        Schema::table('volunteer_opportunities', function (Blueprint $table) {
            // "إغاثة" is retired as a category: it stays under the "outside Kuwait"
            // classification (is_relief) and gains a separate emergency priority.
            if (! Schema::hasColumn('volunteer_opportunities', 'is_emergency')) {
                $table->boolean('is_emergency')->default(false);
            }
            // بيئي / خيري / تنظيمي
            if (! Schema::hasColumn('volunteer_opportunities', 'volunteer_category')) {
                $table->string('volunteer_category', 32)->nullable();
            }
            // Only meaningful for the "charity" category.
            if (! Schema::hasColumn('volunteer_opportunities', 'beneficiaries_count')) {
                $table->unsignedInteger('beneficiaries_count')->nullable();
            }
            // Set by an admin to reopen a check-in window that already closed.
            if (! Schema::hasColumn('volunteer_opportunities', 'preparation_reopened_until')) {
                $table->dateTime('preparation_reopened_until')->nullable();
            }
            if (! Schema::hasColumn('volunteer_opportunities', 'preparation_reminder_sent_at')) {
                $table->dateTime('preparation_reminder_sent_at')->nullable();
            }
            // Guards the emergency/backup alert so it goes out exactly once.
            if (! Schema::hasColumn('volunteer_opportunities', 'backup_alert_sent_at')) {
                $table->dateTime('backup_alert_sent_at')->nullable();
            }
        });

        Schema::table('volunteer_opportunity_attendances', function (Blueprint $table) {
            // Manual and QR check-in now coexist; record which path was used.
            if (! Schema::hasColumn('volunteer_opportunity_attendances', 'recorded_via')) {
                $table->string('recorded_via', 16)->nullable();
            }
            if (! Schema::hasColumn('volunteer_opportunity_attendances', 'recorded_by')) {
                $table->unsignedBigInteger('recorded_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            foreach (['preparation_validity_hours', 'preparation_reminder_hours_before'] as $column) {
                if (Schema::hasColumn('configs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('volunteer_opportunities', function (Blueprint $table) {
            foreach ([
                'is_emergency',
                'volunteer_category',
                'beneficiaries_count',
                'preparation_reopened_until',
                'preparation_reminder_sent_at',
                'backup_alert_sent_at',
            ] as $column) {
                if (Schema::hasColumn('volunteer_opportunities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('volunteer_opportunity_attendances', function (Blueprint $table) {
            foreach (['recorded_via', 'recorded_by'] as $column) {
                if (Schema::hasColumn('volunteer_opportunity_attendances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
