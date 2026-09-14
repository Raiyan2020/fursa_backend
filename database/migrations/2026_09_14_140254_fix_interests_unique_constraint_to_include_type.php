<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SyncsOpportunityInterests now looks up an existing legacy Interest
        // row scoped by interest_type, so a name reused across types (e.g.
        // "Kids" for both a volunteer opportunity and an event) needs its own
        // row per type. The old (name_en, name_ar)-only unique index rejected
        // that second insert with a 500 — widen it to match the new lookup.
        Schema::table('interests', function (Blueprint $table) {
            $table->dropUnique(['name_en', 'name_ar']);
            $table->unique(['name_en', 'name_ar', 'interest_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interests', function (Blueprint $table) {
            $table->dropUnique(['name_en', 'name_ar', 'interest_type']);
            $table->unique(['name_en', 'name_ar']);
        });
    }
};
