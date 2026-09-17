<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * BE-62 — rename Consultation to «مساحة», and give master_choices a stable key.
 *
 * The rename is Arabic-label-only (Option 1 from the issue): reversible,
 * breaks nothing, because nothing in either codebase matches on value_ar —
 * almost every behavioural rule matches on value_en, which stays
 * "Consultation" here. Renamed in place, not soft-deleted and re-inserted,
 * so learning_type_id stays a valid foreign key on every existing
 * learn-&-serve opportunity.
 *
 * The slug is the fix for the underlying problem this ticket is the third
 * instance of (BE-47 part 2's Class/Workshop merge, and the
 * isConsultationType() typo before that): every behavioural rule in both
 * codebases was matched against a user-editable display label instead of a
 * stable key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_choices', function (Blueprint $table) {
            if (! Schema::hasColumn('master_choices', 'slug')) {
                $table->string('slug', 191)->nullable()->after('value_ar');
                $table->index(['choice_type_id', 'slug']);
            }
        });

        $renamedTypeIds = DB::table('choice_types')
            ->whereIn('name', ['learning_type', 'filter-type'])
            ->pluck('id');

        DB::table('master_choices')
            ->where('value_en', 'Consultation')
            ->whereIn('choice_type_id', $renamedTypeIds)
            ->update(['value_ar' => 'مساحة']);

        $this->backfillSlugs();
    }

    public function down(): void
    {
        $renamedTypeIds = DB::table('choice_types')
            ->whereIn('name', ['learning_type', 'filter-type'])
            ->pluck('id');

        DB::table('master_choices')
            ->where('value_en', 'Consultation')
            ->whereIn('choice_type_id', $renamedTypeIds)
            ->update(['value_ar' => 'استشارة']);

        Schema::table('master_choices', function (Blueprint $table) {
            if (Schema::hasColumn('master_choices', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }

    /**
     * One slug per row, derived once from value_en at the time it is
     * assigned and never touched again by a later rename — that immutability
     * is the whole point. A few choice types keep intentional
     * lowercase-duplicate legacy rows (BE-47), so collisions get a numeric
     * suffix rather than failing the backfill.
     */
    private function backfillSlugs(): void
    {
        $seen = [];

        DB::table('master_choices')
            ->whereNull('slug')
            ->orderBy('id')
            ->select(['id', 'choice_type_id', 'value_en'])
            ->get()
            ->each(function ($row) use (&$seen) {
                $base = Str::slug((string) $row->value_en) ?: 'choice';
                $slug = $base;
                $i = 2;
                while (in_array($row->choice_type_id.'|'.$slug, $seen, true)) {
                    $slug = $base.'-'.$i++;
                }
                $seen[] = $row->choice_type_id.'|'.$slug;

                DB::table('master_choices')->where('id', $row->id)->update(['slug' => $slug]);
            });
    }
};
