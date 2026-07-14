<?php

namespace Database\Seeders;

use App\Models\CommunityTag;
use Illuminate\Database\Seeder;

class CommunityTagSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['volunteering', 'education', 'health', 'environment', 'technology', 'kids', 'sports'] as $tag) {
            CommunityTag::query()->firstOrCreate(['name' => $tag]);
        }
    }
}
