<?php

namespace Database\Seeders;

use App\Models\UserRoleLicenseRequirement;
use Illuminate\Database\Seeder;

class UserRoleLicenseRequirementSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['volunteer', 'organization', 'volunteer_team'] as $role) {
            UserRoleLicenseRequirement::query()->firstOrCreate(
                ['user_role' => $role],
                ['license_required' => $role === 'organization']
            );
        }
    }
}
