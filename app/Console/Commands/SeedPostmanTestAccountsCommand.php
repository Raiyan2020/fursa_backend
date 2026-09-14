<?php

namespace App\Console\Commands;

use App\Enums\ApprovalStatus;
use App\Enums\UserType;
use App\Models\OrganizationProfile;
use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedPostmanTestAccountsCommand extends Command
{
    protected $signature = 'fursa:seed-postman-accounts';

    protected $description = 'Create (or reset) the fixed, pre-approved accounts the Postman "BE Fixes Verification" folder logs in as';

    private const PASSWORD = 'Password1';

    public function handle(): int
    {
        $org = $this->upsertUser('postman.org@fursa.local', UserType::ORGANIZATION, [
            'first_name' => 'Postman',
            'last_name' => 'Organization',
        ]);
        OrganizationProfile::query()->updateOrCreate(
            ['user_id' => $org->id],
            [
                'company_name' => 'Postman Test Org',
                'nickname' => 'postman_org',
                'organization_status' => ApprovalStatus::APPROVED,
            ]
        );
        $this->info("Organization ready: {$org->email} / ".self::PASSWORD.' (APPROVED)');

        $volunteer = $this->upsertUser('postman.volunteer@fursa.local', UserType::VOLUNTEER, [
            'first_name' => 'Postman',
            'last_name' => 'Volunteer',
            'birth_year' => 1995,
            'civil_id' => $this->stableCivilId('postman.volunteer@fursa.local'),
        ]);
        VolunteerProfile::query()->updateOrCreate(
            ['user_id' => $volunteer->id],
            ['is_verified' => true, 'is_public' => true, 'nickname' => 'postman_vol', 'uuid' => (string) Str::uuid()]
        );
        $this->info("Volunteer ready: {$volunteer->email} / ".self::PASSWORD);

        $stranger = $this->upsertUser('postman.stranger@fursa.local', UserType::VOLUNTEER, [
            'first_name' => 'Postman',
            'last_name' => 'Stranger',
            'birth_year' => 1995,
            'civil_id' => $this->stableCivilId('postman.stranger@fursa.local'),
        ]);
        VolunteerProfile::query()->updateOrCreate(
            ['user_id' => $stranger->id],
            ['is_verified' => true, 'is_public' => true, 'nickname' => 'postman_stranger', 'uuid' => (string) Str::uuid()]
        );
        $this->info("Stranger volunteer ready: {$stranger->email} / ".self::PASSWORD);

        // Starts APPROVED (so it can log in and hold a token) — the BE-36
        // folder itself flips it to REJECTED mid-run via _test/set-org-status
        // to reproduce "a token issued while approved, then rejected".
        $revocableOrg = $this->upsertUser('postman.revocable-org@fursa.local', UserType::ORGANIZATION, [
            'first_name' => 'Postman',
            'last_name' => 'Revocable Organization',
        ]);
        OrganizationProfile::query()->updateOrCreate(
            ['user_id' => $revocableOrg->id],
            [
                'company_name' => 'Postman Revocable Org',
                'nickname' => 'postman_revocable_org',
                'organization_status' => ApprovalStatus::APPROVED,
            ]
        );
        $this->info("Revocable organization ready: {$revocableOrg->email} / ".self::PASSWORD.' (APPROVED, for BE-36 checks)');

        return self::SUCCESS;
    }

    private function upsertUser(string $email, UserType $type, array $extra): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            array_merge([
                'password' => self::PASSWORD,
                'password_length' => strlen(self::PASSWORD),
                'user_type' => $type,
                'is_active' => true,
                'preferred_language' => 'en',
                'manual_id' => Str::random(22),
            ], $extra)
        );
    }

    private function stableCivilId(string $seed): string
    {
        return str_pad((string) (crc32($seed) % 900000000000), 12, '1', STR_PAD_LEFT);
    }
}
