<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BE-51: second client-approved org_type restructure. Four straight renames,
 * plus "Society" (جمعية تعاونية / مجتمع) splitting into "Association" (جمعية)
 * and "Community" (مجتمع).
 *
 * Renames are done in place (same row, new value_en/value_ar) so every
 * organizer_type_id foreign key stays valid with nothing to repoint — this is
 * also how "Society" is decided for existing organizations: renaming its row
 * to "Association" in place means every organization currently on Society
 * becomes Association by construction, per the client's confirmed default.
 * "Community" is a brand-new option; nothing points at it yet. Any
 * organization that is really a community group needs correcting by hand.
 */
return new class extends Migration
{
    /** old value_en => [new value_en, new value_ar] */
    private const RENAMES = [
        'Institution' => ['Governmental', 'حكومي'],
        'Commercial' => ['Commercial', 'تجاري'],
        'Education' => ['Educational', 'تعليمي'],
        'NGO' => ['NonProfit', 'غير ربحي'],
        'Society' => ['Association', 'جمعية'],
    ];

    private const NEW_COMMUNITY = ['Community', 'مجتمع'];

    public function up(): void
    {
        $typeId = $this->orgTypeId();
        if (! $typeId) {
            return;
        }

        foreach (self::RENAMES as $from => [$toEn, $toAr]) {
            $id = $this->choiceId($typeId, $from);
            if (! $id) {
                continue;
            }

            DB::table('master_choices')->where('id', $id)->update([
                'value_en' => $toEn,
                'value_ar' => $toAr,
                'updated_at' => now(),
            ]);
        }

        // Restore the soft-deleted row the first restructure retired, rather
        // than inserting a duplicate.
        [$communityEn, $communityAr] = self::NEW_COMMUNITY;
        $communityId = DB::table('master_choices')
            ->where('choice_type_id', $typeId)
            ->where('value_en', $communityEn)
            ->value('id');

        if ($communityId) {
            DB::table('master_choices')->where('id', $communityId)->update([
                'value_ar' => $communityAr,
                'is_deleted' => false,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('master_choices')->insert([
                'choice_type_id' => $typeId,
                'value_en' => $communityEn,
                'value_ar' => $communityAr,
                'is_deleted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // BE-51 note: sponsors.org_type_id points at the same rows renamed
        // above, so it needs no separate repointing — same reasoning as
        // organizer_type_id: the id is unchanged, only its label is.
    }

    public function down(): void
    {
        $typeId = $this->orgTypeId();
        if (! $typeId) {
            return;
        }

        foreach (self::RENAMES as $from => [$toEn]) {
            $id = $this->choiceId($typeId, $toEn);
            if (! $id) {
                continue;
            }

            DB::table('master_choices')->where('id', $id)->update([
                'value_en' => $from,
                'updated_at' => now(),
            ]);
        }

        // Reset value_ar for the renamed rows back to round-one labels.
        DB::table('master_choices')->where('choice_type_id', $typeId)->where('value_en', 'Institution')->update(['value_ar' => 'وزارة / هيئة حكومية']);
        DB::table('master_choices')->where('choice_type_id', $typeId)->where('value_en', 'Commercial')->update(['value_ar' => 'شركة تجارية / براند']);
        DB::table('master_choices')->where('choice_type_id', $typeId)->where('value_en', 'Education')->update(['value_ar' => 'جامعة / مدرسة / معهد']);
        DB::table('master_choices')->where('choice_type_id', $typeId)->where('value_en', 'NGO')->update(['value_ar' => 'جمعية خيرية / غير ربحية']);
        DB::table('master_choices')->where('choice_type_id', $typeId)->where('value_en', 'Society')->update(['value_ar' => 'جمعية تعاونية / مجتمع']);

        // Re-retire Community — it did not exist as a live option before this
        // migration.
        [$communityEn] = self::NEW_COMMUNITY;
        DB::table('master_choices')
            ->where('choice_type_id', $typeId)
            ->where('value_en', $communityEn)
            ->update(['is_deleted' => true, 'deleted_at' => now()]);
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
