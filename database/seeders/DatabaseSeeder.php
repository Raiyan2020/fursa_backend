<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Core settings
            ConfigSeeder::class,
            ChoiceTypeSeeder::class,
            BadgeSeeder::class,
            UserTypeApprovalSeeder::class,
            UserRoleLicenseRequirementSeeder::class,

            // Content / lookups
            InterestSeeder::class,
            FaqSeeder::class,
            EmailTemplateSeeder::class,
            ForbiddenWordSeeder::class,
            CommunityTagSeeder::class,
            BannerImageSeeder::class,
            SponsorSeeder::class,

            // Demo accounts last (depends on choices/badges)
            AdminUserSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
