<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // BE-77 part B — needed to tell "non_kuwaiti_arabic" from
            // "non_arabic" apart. Nullable: unanswered (including every
            // existing account) passes the audience filter rather than
            // being blocked by a question nobody was asked.
            if (! Schema::hasColumn('users', 'speaks_arabic')) {
                $table->boolean('speaks_arabic')->nullable();
            }
        });

        // BE-77 part A — opportunity_nationality replaces is_kuwaitis as the
        // stored audience. Backfill is exact and lossless: is_kuwaitis=true
        // becomes 'kuwaitis', false becomes 'all' — nothing else was
        // expressible on the old boolean, so nothing is guessed. This
        // overwrites whatever the column already held (it was write-only
        // dead weight: nothing read it before this).
        foreach (['volunteer_opportunities', 'learn_serve_opportunities'] as $table) {
            DB::table($table)->where('is_kuwaitis', true)->update(['opportunity_nationality' => 'kuwaitis']);
            DB::table($table)->where('is_kuwaitis', false)->update(['opportunity_nationality' => 'all']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'speaks_arabic')) {
                $table->dropColumn('speaks_arabic');
            }
        });
    }
};
