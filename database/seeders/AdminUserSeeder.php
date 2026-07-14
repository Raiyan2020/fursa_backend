<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\UserType;
use App\Models\ExpiringToken;
use App\Models\Notification;
use App\Models\OrganizationProfile;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VolunteerProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@fursa.local'],
            [
                'username' => 'admin',
                'first_name' => 'Fursa',
                'last_name' => 'Admin',
                'password' => 'Password1',
                'password_length' => 10,
                'user_type' => UserType::ADMIN,
                'is_active' => true,
                'is_staff' => true,
                'is_superuser' => true,
                'preferred_language' => 'en',
                'manual_id' => Str::random(22),
            ]
        );

        $volunteer = User::query()->firstOrCreate(
            ['email' => 'volunteer@fursa.local'],
            [
                'username' => 'volunteer',
                'first_name' => 'Demo',
                'last_name' => 'Volunteer',
                'password' => 'Password1',
                'password_length' => 10,
                'user_type' => UserType::VOLUNTEER,
                'civil_id' => '200000000001',
                'is_active' => true,
                'preferred_language' => 'en',
                'manual_id' => Str::random(22),
            ]
        );

        VolunteerProfile::query()->firstOrCreate(
            ['user_id' => $volunteer->id],
            [
                'nickname' => 'demo_volunteer',
                'uuid' => (string) Str::uuid(),
                'is_verified' => true,
                'is_public' => true,
                'occupation' => 'Student',
            ]
        );

        $organization = User::query()->firstOrCreate(
            ['email' => 'organization@fursa.local'],
            [
                'username' => 'organization',
                'first_name' => 'Demo',
                'last_name' => 'Organization',
                'password' => 'Password1',
                'password_length' => 10,
                'user_type' => UserType::ORGANIZATION,
                'is_active' => true,
                'preferred_language' => 'en',
                'manual_id' => Str::random(22),
            ]
        );

        OrganizationProfile::query()->firstOrCreate(
            ['user_id' => $organization->id],
            [
                'nickname' => 'demo_org',
                'company_name' => 'Demo Organization',
                'organization_status' => ApprovalStatus::APPROVED,
            ]
        );

        foreach ([$admin, $volunteer, $organization] as $user) {
            ExpiringToken::issueFor($user, 30);
        }

        $notification = Notification::query()->firstOrCreate(
            [
                'title_en' => 'Welcome to Fursa',
                'title_ar' => 'مرحبًا بك في فرصة',
            ],
            [
                'message_en' => 'Your demo account is ready.',
                'message_ar' => 'حسابك التجريبي جاهز.',
            ]
        );

        foreach ([$volunteer, $organization] as $user) {
            UserNotification::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_id' => $notification->id,
                ],
                ['is_read' => false]
            );
        }
    }
}
