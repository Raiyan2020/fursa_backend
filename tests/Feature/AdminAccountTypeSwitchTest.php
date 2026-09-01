<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\UserType;
use App\Models\Admin;
use App\Models\MasterChoice;
use App\Models\OrganizationProfile;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use App\Models\VolunteerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Switching an existing account between volunteer and entity is not supported.
 *
 * Beyond stranding the volunteer's history behind the organization profile
 * template, the old behaviour set organization_status to APPROVED, which skipped
 * licence verification for the new entity.
 */
class AdminAccountTypeSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_volunteer_cannot_be_switched_to_an_entity(): void
    {
        $volunteer = $this->volunteer();

        $this->actingAs($this->admin(), 'admin')
            ->put("/dashboard/users/{$volunteer->id}", $this->payload($volunteer, [
                'account_type' => 'organization',
                'company_name' => 'Sneaky Org',
            ]))
            ->assertSessionHasErrors('account_type');

        $this->assertSame(UserType::VOLUNTEER, $volunteer->fresh()->user_type);
    }

    public function test_volunteer_cannot_be_switched_to_a_volunteer_team(): void
    {
        $volunteer = $this->volunteer();

        $this->actingAs($this->admin(), 'admin')
            ->put("/dashboard/users/{$volunteer->id}", $this->payload($volunteer, [
                'account_type' => 'volunteer_team',
            ]))
            ->assertSessionHasErrors('account_type');

        $this->assertSame(UserType::VOLUNTEER, $volunteer->fresh()->user_type);
    }

    public function test_entity_cannot_be_switched_to_a_volunteer(): void
    {
        $org = $this->organization();

        $this->actingAs($this->admin(), 'admin')
            ->put("/dashboard/users/{$org->id}", $this->payload($org, [
                'account_type' => 'volunteer',
            ]))
            ->assertSessionHasErrors('account_type');

        $this->assertSame(UserType::ORGANIZATION, $org->fresh()->user_type);
    }

    public function test_blocked_switch_does_not_create_an_auto_approved_organization(): void
    {
        $volunteer = $this->volunteer();

        $this->actingAs($this->admin(), 'admin')
            ->put("/dashboard/users/{$volunteer->id}", $this->payload($volunteer, [
                'account_type' => 'organization',
                'company_name' => 'Unlicensed Org',
            ]));

        // The real risk: an entity profile that skipped licence verification.
        $this->assertDatabaseMissing('organization_profiles', ['user_id' => $volunteer->id]);
    }

    public function test_blocked_switch_leaves_the_volunteer_history_intact(): void
    {
        $volunteer = $this->volunteer();
        $owner = $this->organization();

        $opportunity = VolunteerOpportunity::query()->create([
            'title_en' => 'Beach cleanup',
            'title_ar' => 'تنظيف الشاطئ',
            'description_en' => 'd',
            'description_ar' => 'و',
            'created_by' => $owner->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
            'is_public' => true,
            'participants_needed' => 5,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDays(4)->toDateString(),
        ]);

        VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->put("/dashboard/users/{$volunteer->id}", $this->payload($volunteer, [
                'account_type' => 'organization',
            ]));

        $this->assertDatabaseHas('volunteer_opportunity_registrations', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
        ]);
        $this->assertDatabaseHas('volunteer_profiles', ['user_id' => $volunteer->id]);
    }

    public function test_editing_a_volunteer_without_changing_type_still_works(): void
    {
        $volunteer = $this->volunteer();

        $this->actingAs($this->admin(), 'admin')
            ->put("/dashboard/users/{$volunteer->id}", $this->payload($volunteer, [
                'account_type' => 'volunteer',
                'first_name' => 'Renamed',
            ]))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $volunteer->fresh()->first_name);
    }

    public function test_entity_can_still_become_a_volunteer_team(): void
    {
        // Both sit on the organization side, so this reshuffle stays allowed.
        $org = $this->organization();

        $this->actingAs($this->admin(), 'admin')
            ->put("/dashboard/users/{$org->id}", $this->payload($org, [
                'account_type' => 'volunteer_team',
                'company_name' => 'Team Forsa',
            ]))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $teamTypeId = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->where('value_en', 'Volunteer Team')
            ->value('id');

        $this->assertSame(
            (int) $teamTypeId,
            (int) $org->fresh()->organizationProfile->organizer_type_id
        );
    }

    public function test_creating_a_new_entity_is_unaffected(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post('/dashboard/users', [
                'first_name' => 'New',
                'last_name' => 'Entity',
                'email' => 'new.entity@test.com',
                'password' => 'Password1',
                'password_confirmation' => 'Password1',
                'account_type' => 'organization',
                'preferred_language' => 'en',
                'is_active' => 1,
                'company_name' => 'Brand New Org',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'new.entity@test.com',
            'user_type' => UserType::ORGANIZATION->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(User $user, array $overrides = []): array
    {
        return array_merge([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'preferred_language' => 'en',
            'is_active' => 1,
        ], $overrides);
    }

    protected function admin(): Admin
    {
        return Admin::query()->firstOrFail();
    }

    protected function volunteer(): User
    {
        $user = User::query()->create([
            'email' => 'vol.'.Str::lower(Str::random(6)).'@test.com',
            'password' => 'Password1',
            'password_length' => 9,
            'user_type' => UserType::VOLUNTEER,
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
            'nickname' => 'vol_'.Str::lower(Str::random(5)),
            'uuid' => (string) Str::uuid(),
        ]);

        return $user->fresh();
    }

    protected function organization(): User
    {
        $user = User::query()->create([
            'email' => 'org.'.Str::lower(Str::random(6)).'@test.com',
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
            'company_name' => 'Test Org '.Str::random(4),
            'nickname' => 'org_'.Str::lower(Str::random(5)),
            'organization_status' => ApprovalStatus::APPROVED,
        ]);

        return $user->fresh();
    }
}
