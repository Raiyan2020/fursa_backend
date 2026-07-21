<?php

namespace Tests\Support;

use App\Enums\ApprovalStatus;
use App\Enums\UserType;
use App\Models\Admin;
use App\Models\ExpiringToken;
use App\Models\OrganizationProfile;
use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Support\Str;

trait CreatesDomainFixtures
{
    /**
     * @return array{0: User, 1: string}
     */
    protected function createVolunteerActor(?string $email = null): array
    {
        /** @var User $user */
        $user = User::query()->create([
            'email' => $email ?? 'volunteer.'.Str::lower(Str::random(6)).'@test.com',
            'password' => 'Password1',
            'password_length' => 9,
            'user_type' => UserType::VOLUNTEER,
            'civil_id' => (string) random_int(100000000000, 999999999999),
            'first_name' => 'Test',
            'last_name' => 'Volunteer',
            'birth_year' => 1995,
            'is_active' => true,
            'preferred_language' => 'en',
            'manual_id' => Str::random(22),
        ]);

        VolunteerProfile::query()->create([
            'user_id' => $user->id,
            'is_verified' => true,
            'is_public' => true,
            'nickname' => 'vol_'.Str::lower(Str::random(6)),
            'uuid' => (string) Str::uuid(),
        ]);

        $token = ExpiringToken::issueFor($user, 30);

        return [$user->fresh('volunteerProfile'), $token->key];
    }

    /**
     * @return array{0: User, 1: string}
     */
    protected function createOrganizationActor(?string $email = null): array
    {
        /** @var User $user */
        $user = User::query()->create([
            'email' => $email ?? 'organization.'.Str::lower(Str::random(6)).'@test.com',
            'password' => 'Password1',
            'password_length' => 9,
            'user_type' => UserType::ORGANIZATION,
            'first_name' => 'Test',
            'last_name' => 'Organization',
            'is_active' => true,
            'preferred_language' => 'en',
            'manual_id' => Str::random(22),
        ]);

        OrganizationProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Test Organization '.Str::random(4),
            'nickname' => 'org_'.Str::lower(Str::random(6)),
            'organization_status' => ApprovalStatus::APPROVED,
        ]);

        $token = ExpiringToken::issueFor($user, 30);

        return [$user->fresh('organizationProfile'), $token->key];
    }

    protected function adminActor(): Admin
    {
        /** @var Admin $admin */
        $admin = Admin::query()->firstOrFail();

        return $admin;
    }

    /**
     * @return array{0: User, 1: string}
     */
    protected function createStaffActor(): array
    {
        /** @var User $user */
        $user = User::query()->create([
            'email' => 'staff.'.Str::lower(Str::random(6)).'@test.com',
            'password' => 'Password1',
            'password_length' => 9,
            'user_type' => UserType::ORGANIZATION,
            'first_name' => 'API',
            'last_name' => 'Staff',
            'is_active' => true,
            'is_staff' => true,
            'is_superuser' => true,
            'preferred_language' => 'en',
            'manual_id' => Str::random(22),
        ]);

        $token = ExpiringToken::issueFor($user, 30);

        return [$user, $token->key];
    }
}
