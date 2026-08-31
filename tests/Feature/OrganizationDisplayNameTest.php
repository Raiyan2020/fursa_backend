<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\UserType;
use App\Models\OrganizationProfile;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * Organizations store their name in organization_profiles.company_name and
 * usually leave users.first_name/last_name null, so full_name came back as an
 * empty string for the publisher of most opportunities (reported on account
 * #970, whose company_name is set but whose first/last name are null).
 */
class OrganizationDisplayNameTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed();
    }

    public function test_opportunity_creator_falls_back_to_the_company_name(): void
    {
        // Mirrors #970: company_name set, first/last name null.
        $org = $this->organization(companyName: 'Ahmed', firstName: null, lastName: null);
        $opportunity = $this->opportunity($org->id);

        $response = $this->getJson("/api/opportunities/{$opportunity->id}/details/");
        $response->assertOk();

        $this->assertSame('Ahmed', $response->json('data.created_by.full_name'));
    }

    public function test_creator_nickname_also_falls_back(): void
    {
        $org = $this->organization(companyName: 'Ahmed', firstName: null, lastName: null);
        $opportunity = $this->opportunity($org->id);

        $response = $this->getJson("/api/opportunities/{$opportunity->id}/details/");
        $response->assertOk();

        $this->assertSame('Ahmed', $response->json('data.created_by.nickname'));
    }

    public function test_an_explicit_first_name_still_wins(): void
    {
        // Mirrors #905, which has first_name filled in; it must not be
        // overridden by company_name.
        $org = $this->organization(companyName: 'Omniya Co', firstName: 'omniya', lastName: '');
        $opportunity = $this->opportunity($org->id);

        $response = $this->getJson("/api/opportunities/{$opportunity->id}/details/");
        $response->assertOk();

        $this->assertSame('omniya', $response->json('data.created_by.full_name'));
    }

    public function test_falls_back_to_nickname_when_company_name_is_blank(): void
    {
        $org = $this->organization(companyName: '', firstName: null, lastName: null, nickname: 'fahmy123');
        $opportunity = $this->opportunity($org->id);

        $response = $this->getJson("/api/opportunities/{$opportunity->id}/details/");
        $response->assertOk();

        $this->assertSame('fahmy123', $response->json('data.created_by.full_name'));
    }

    public function test_volunteers_are_unaffected(): void
    {
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($volunteer->id);

        $response = $this->getJson("/api/opportunities/{$opportunity->id}/details/");
        $response->assertOk();

        // The volunteer fixture uses Test / Volunteer.
        $this->assertSame('Test Volunteer', $response->json('data.created_by.full_name'));
    }

    public function test_an_organization_with_no_name_anywhere_returns_an_empty_string(): void
    {
        $org = $this->organization(companyName: '', firstName: null, lastName: null, nickname: '');
        $opportunity = $this->opportunity($org->id);

        $response = $this->getJson("/api/opportunities/{$opportunity->id}/details/");
        $response->assertOk();

        // Still a string, never null — the contract does not change shape.
        $this->assertSame('', $response->json('data.created_by.full_name'));
    }

    protected function organization(
        string $companyName,
        ?string $firstName,
        ?string $lastName,
        string $nickname = 'org_nick',
    ): User {
        $user = User::query()->create([
            'email' => 'org.'.Str::lower(Str::random(6)).'@test.com',
            'password' => 'Password1',
            'password_length' => 9,
            'user_type' => UserType::ORGANIZATION,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_active' => true,
            'preferred_language' => 'en',
            'manual_id' => Str::random(22),
        ]);

        OrganizationProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => $companyName,
            'nickname' => $nickname,
            'organization_status' => ApprovalStatus::APPROVED,
        ]);

        return $user->fresh();
    }

    protected function opportunity(int $ownerId): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create([
            'title_en' => 'Beach cleanup',
            'title_ar' => 'تنظيف الشاطئ',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'created_by' => $ownerId,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'is_public' => true,
            'participants_needed' => 10,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ]);
    }
}
