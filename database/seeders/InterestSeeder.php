<?php

namespace Database\Seeders;

use App\Enums\InterestType;
use App\Models\Interest;
use Illuminate\Database\Seeder;

class InterestSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['Sports', 'رياضة', InterestType::PERSONAL],
            ['Arts', 'فنون', InterestType::PERSONAL],
            ['Environment', 'بيئة', InterestType::VOLUNTEER],
            ['Education', 'تعليم', InterestType::VOLUNTEER],
            ['Health', 'صحة', InterestType::VOLUNTEER],
            ['Technology', 'تقنية', InterestType::LEARNSHARE],
            ['Community', 'مجتمع', InterestType::EVENT],
            ['Culture', 'ثقافة', InterestType::EVENT],
        ];

        foreach ($items as [$en, $ar, $type]) {
            Interest::query()->firstOrCreate(
                ['name_en' => $en, 'name_ar' => $ar],
                ['interest_type' => $type]
            );
        }
    }
}
