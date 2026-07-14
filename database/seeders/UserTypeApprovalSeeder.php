<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\UserTypeApproval;
use Illuminate\Database\Seeder;

class UserTypeApprovalSeeder extends Seeder
{
    public function run(): void
    {
        UserTypeApproval::query()->firstOrCreate(
            ['user_type' => UserType::VOLUNTEER],
            ['requires_approval' => false]
        );

        UserTypeApproval::query()->firstOrCreate(
            ['user_type' => UserType::ORGANIZATION],
            ['requires_approval' => true]
        );
    }
}
