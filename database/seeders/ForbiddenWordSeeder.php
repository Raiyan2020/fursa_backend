<?php

namespace Database\Seeders;

use App\Models\ForbiddenWord;
use Illuminate\Database\Seeder;

class ForbiddenWordSeeder extends Seeder
{
    public function run(): void
    {
        $words = [
            ['hate', 'كراهية'],
            ['spam', 'إزعاج'],
            ['abuse', 'إساءة'],
            ['violence', 'عنف'],
        ];

        foreach ($words as [$en, $ar]) {
            ForbiddenWord::query()->firstOrCreate(
                ['word_en' => $en],
                ['word_ar' => $ar]
            );
        }
    }
}
