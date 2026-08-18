<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            if (! Schema::hasColumn('configs', 'economic_impact_rate_kwd')) {
                $table->decimal('economic_impact_rate_kwd', 8, 2)->default(6);
            }
            if (! Schema::hasColumn('configs', 'preparation_validity_days')) {
                $table->unsignedInteger('preparation_validity_days')->default(7);
            }
        });

        foreach (['volunteer_opportunities', 'learn_serve_opportunities', 'events'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'location_url')) {
                    $table->string('location_url', 500)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'is_registration_closed')) {
                    $table->boolean('is_registration_closed')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            if (Schema::hasColumn('configs', 'economic_impact_rate_kwd')) {
                $table->dropColumn('economic_impact_rate_kwd');
            }
            if (Schema::hasColumn('configs', 'preparation_validity_days')) {
                $table->dropColumn('preparation_validity_days');
            }
        });

        foreach (['volunteer_opportunities', 'learn_serve_opportunities', 'events'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'location_url')) {
                    $table->dropColumn('location_url');
                }
                if (Schema::hasColumn($tableName, 'is_registration_closed')) {
                    $table->dropColumn('is_registration_closed');
                }
            });
        }
    }
};
