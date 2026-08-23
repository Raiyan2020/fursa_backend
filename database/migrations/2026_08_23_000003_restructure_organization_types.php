<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reshapes org_type to the classification the client asked for:
 *
 *   Institution     — ministry / government body
 *   Education       — university / school / institute
 *   Society         — cooperative society / community
 *   NGO             — charity / non-profit
 *   Volunteer Team  — volunteer team
 *   Commercial      — commercial company / brand
 *
 * Retired options are soft-deleted rather than dropped, and every organization
 * pointing at one is repointed at its replacement first, so no profile is left
 * referencing a hidden choice.
 */
return new class extends Migration
{
    /** old value_en => new value_en */
    private const MIGRATION_MAP = [
        'Government' => 'Institution',
        'Public' => 'Institution',
        'Private' => 'Commercial',
        'Company' => 'Commercial',
        'Community' => 'Society',
    ];

    private const NEW_TYPES = [
        ['Institution', 'وزارة / هيئة حكومية'],
        ['Education', 'جامعة / مدرسة / معهد'],
        ['Society', 'جمعية تعاونية / مجتمع'],
        ['NGO', 'جمعية خيرية / غير ربحية'],
        ['Volunteer Team', 'فريق تطوعي'],
        ['Commercial', 'شركة تجارية / براند'],
    ];

    public function up(): void
    {
        $typeId = $this->orgTypeId();
        if (! $typeId) {
            return;
        }

        foreach (self::NEW_TYPES as [$en, $ar]) {
            $existingId = $this->choiceId($typeId, $en);

            if ($existingId) {
                DB::table('master_choices')
                    ->where('id', $existingId)
                    ->update(['value_ar' => $ar, 'is_deleted' => false, 'deleted_at' => null]);

                continue;
            }

            DB::table('master_choices')->insert([
                'choice_type_id' => $typeId,
                'value_en' => $en,
                'value_ar' => $ar,
                'is_deleted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sponsorsHaveOrgType = Schema::hasTable('sponsors')
            && Schema::hasColumn('sponsors', 'org_type_id');

        foreach (self::MIGRATION_MAP as $from => $to) {
            $fromId = $this->choiceId($typeId, $from);
            $toId = $this->choiceId($typeId, $to);

            if (! $fromId || ! $toId) {
                continue;
            }

            // Repoint the data before hiding the old option.
            DB::table('organization_profiles')
                ->where('organizer_type_id', $fromId)
                ->update(['organizer_type_id' => $toId]);

            if ($sponsorsHaveOrgType) {
                DB::table('sponsors')
                    ->where('org_type_id', $fromId)
                    ->update(['org_type_id' => $toId]);
            }

            DB::table('master_choices')->where('id', $fromId)->update([
                'is_deleted' => true,
                'deleted_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $typeId = $this->orgTypeId();
        if (! $typeId) {
            return;
        }

        // Restore the retired options. Repointed profiles are not moved back:
        // the original per-row value is no longer recoverable.
        DB::table('master_choices')
            ->where('choice_type_id', $typeId)
            ->whereIn('value_en', array_keys(self::MIGRATION_MAP))
            ->update(['is_deleted' => false, 'deleted_at' => null]);
    }

    private function orgTypeId(): ?int
    {
        return DB::table('choice_types')->where('name', 'org_type')->value('id');
    }

    private function choiceId(int $typeId, string $valueEn): ?int
    {
        return DB::table('master_choices')
            ->where('choice_type_id', $typeId)
            ->where('value_en', $valueEn)
            ->value('id');
    }
};
