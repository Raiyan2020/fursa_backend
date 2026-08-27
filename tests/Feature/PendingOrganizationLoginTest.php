<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\UserType;
use App\Models\OrganizationProfile;
use App\Models\User;
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
