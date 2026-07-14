<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            ['Beginner', '0-20 hours', 0, 20, 1],
            ['Active', '20-50 hours', 20, 50, 2],
            ['Dedicated', '50-100 hours', 50, 100, 3],
            ['Champion', '100+ hours', 100, null, 4],
        ];

        foreach ($badges as [$name, $desc, $min, $max, $priority]) {
            Badge::query()->firstOrCreate(
                ['name' => $name],
                [
                    'description' => $desc,
                    'min_hours' => $min,
                    'max_hours' => $max,
                    'priority' => $priority,
                ]
            );
        }
    }
}
