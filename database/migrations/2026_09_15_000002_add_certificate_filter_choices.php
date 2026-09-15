<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** BE-53: deploy the certificate filter vocabulary without requiring a reseed. */
return new class extends Migration
{
    private const VALUES = [
        ['Volunteer', 'تطوع'],
        ['Course', 'دورة'],
        ['Internship', 'تدريب'],
    ];

    public function up(): void
    {
        $typeId = DB::table('choice_types')->where('name', 'certificate_filter_type')->value('id');

        if (! $typeId) {
            $typeId = DB::table('choice_types')->insertGetId([
                'name' => 'certificate_filter_type',
                'is_deleted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('choice_types')->where('id', $typeId)->update([
                'is_deleted' => false,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
        }

        foreach (self::VALUES as [$valueEn, $valueAr]) {
            $choiceId = DB::table('master_choices')
                ->where('choice_type_id', $typeId)
                ->where('value_en', $valueEn)
                ->value('id');

            $attributes = [
                'value_ar' => $valueAr,
                'is_deleted' => false,
                'deleted_at' => null,
                'updated_at' => now(),
            ];

            if ($choiceId) {
                DB::table('master_choices')->where('id', $choiceId)->update($attributes);
            } else {
                DB::table('master_choices')->insert($attributes + [
                    'choice_type_id' => $typeId,
                    'value_en' => $valueEn,
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $typeId = DB::table('choice_types')->where('name', 'certificate_filter_type')->value('id');
        if (! $typeId) {
            return;
        }

        DB::table('master_choices')->where('choice_type_id', $typeId)->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);
        DB::table('choice_types')->where('id', $typeId)->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);
    }
};
