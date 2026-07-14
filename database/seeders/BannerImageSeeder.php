<?php

namespace Database\Seeders;

use App\Models\BannerImage;
use Illuminate\Database\Seeder;

class BannerImageSeeder extends Seeder
{
    public function run(): void
    {
        BannerImage::query()->firstOrCreate(
            ['name' => 'Home Hero'],
            [
                'image' => 'banners/home-hero-placeholder.jpg',
                'banner_url' => 'https://fursa.local',
            ]
        );

        BannerImage::query()->firstOrCreate(
            ['name' => 'Volunteer CTA'],
            [
                'image' => 'banners/volunteer-cta-placeholder.jpg',
                'banner_url' => 'https://fursa.local/volunteer',
            ]
        );
    }
}
