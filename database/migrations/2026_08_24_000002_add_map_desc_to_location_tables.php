<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client request: the free-text location input is replaced by a map picker, so
 * the location is now a description plus coordinates.
 *
 * `latitude` / `longitude` already existed; only the description is new.
 * `location_en` / `location_ar` are kept and backfilled so older records and
 * any client still reading them keep working.
 */
return new class extends Migration
{
    private const TABLES = ['volunteer_opportunities', 'learn_serve_opportunities', 'events'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'map_desc')) {
                    $blueprint->string('map_desc', 500)->nullable()->after('location_ar');
                }
            });

            // Seed the new column from whatever description already exists so the
            // map picker opens with the location the publisher previously typed.
            DB::table($table)
                ->whereNull('map_desc')
                ->update([
                    'map_desc' => DB::raw(
                        "COALESCE(NULLIF(location_ar, ''), NULLIF(location_en, ''))"
                    ),
                ]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'map_desc')) {
                    $blueprint->dropColumn('map_desc');
                }
            });
        }
    }
};
