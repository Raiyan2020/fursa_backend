<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\UserType;
use App\Models\ExpiringToken;
use App\Models\OrganizationProfile;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AssertsDjangoApiEnvelope;
use Tests\TestCase;

class PendingOrganizationLoginTest extends TestCase
{
    use AssertsDjangoApiEnvelope;
    use RefreshDatabase;

    public function test_pending_organization_login_is_rejected_without_token(): void
    {
        $user = $this->createOrganization(ApprovalStatus::PENDING);

        $login = $this->postJson('/api/login/', [
            'email' => 'pending-org@test.com',
            'password' => 'Password1',
        ]);

        $this->assertErrorEnvelope(
            $login,
            403,
            'Your organization account has not been approved by the admin yet.'
        );
        // No token may be issued for a pending organization.
        $this->assertNull($login->json('data'));
    }

    public function test_rejected_organization_login_is_rejected(): void
    {
        $this->createOrganization(ApprovalStatus::REJECTED);

        $login = $this->postJson('/api/login/', [
            'email' => 'pending-org@test.com',
            'password' => 'Password1',
        ]);

        $this->assertErrorEnvelope(
            $login,
            403,
            'Your organization account was rejected by the admin.'
        );
        $this->assertNull($login->json('data'));
    }

    public function test_approved_organization_login_receives_token(): void
    {
        $this->createOrganization(ApprovalStatus::APPROVED);

        $login = $this->postJson('/api/login/', [
            'email' => 'pending-org@test.com',
            'password' => 'Password1',
        ]);

        $this->assertSuccessEnvelope($login, 200, 'Login successful.');
        $this->assertNotEmpty($login->json('data.data.auth_token'));
    }

    public function test_withdrawn_approval_blocks_existing_token_on_privileged_writes(): void
    {
        $user = $this->createOrganization(ApprovalStatus::APPROVED);
        $token = ExpiringToken::issueFor($user, 30);
        $opportunity = VolunteerOpportunity::query()->create([
            'created_by' => $user->id,
            'title_en' => 'Owned opportunity',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'is_public' => true,
        ]);

        $user->organizationProfile->update(['organization_status' => ApprovalStatus::PENDING]);

        foreach ([
            ['PATCH', "/api/volunteer-opportunities/$opportunity->id/"],
            ['POST', "/api/volunteer-opportunities/$opportunity->id/resubmit/"],
            ['DELETE', "/api/volunteer-opportunities/$opportunity->id/"],
        ] as [$method, $url]) {
            $this->withToken($token->key)->json($method, $url, ['title_en' => 'Blocked'])
                ->assertForbidden()
                ->assertJsonPath('msg', 'Your organization account has not been approved by the admin yet.');
        }
    }

    protected function createOrganization(ApprovalStatus $status): User
    {
        $user = User::query()->create([
            'email' => 'pending-org@test.com',
            'password' => 'Password1',
            'password_length' => 9,
            'user_type' => UserType::ORGANIZATION,
            'first_name' => 'Org',
            'last_name' => 'Admin',
            'is_active' => true,
            'preferred_language' => 'en',
            'manual_id' => Str::random(22),
        ]);

        OrganizationProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Fursa Org',
            'organization_status' => $status,
            'nickname' => 'org_'.Str::random(4),
        ]);

        return $user;
    }
}
