<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\UserType;
use App\Enums\VolunteerCategory;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\MasterChoice;
use App\Models\OrganizationStatistic;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use App\Services\Opportunity\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class BackendIssuesFinalRoundTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_be44_api_accepts_dashboard_fields_and_after_images(): void
    {
        Storage::fake('public');
        [, $token] = $this->createOrganizationActor();

        $response = $this->api($token)->postJson('/api/volunteer-opportunities/', [
            ...$this->volunteerOpportunityPayload(),
            'is_calendar' => true,
            'opportunity_nationality' => 'all',
            'after_images' => [UploadedFile::fake()->image('after.jpg')],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_calendar', true)
            ->assertJsonPath('data.opportunity_nationality', 'all');

        $opportunity = VolunteerOpportunity::query()->latest('id')->firstOrFail();
        $this->assertTrue($opportunity->is_calendar);
        $this->assertTrue($opportunity->images()->where('is_after_completed', true)->exists());
    }

    public function test_be44_dashboard_can_display_and_replace_an_api_schedule(): void
    {
        [$organization] = $this->createOrganizationActor();
        $opportunity = VolunteerOpportunity::query()->create($this->volunteerOpportunityPayload([
            'created_by' => $organization->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
        ]));
        $oldDate = now()->addDays(2)->toDateString();
        $newDate = now()->addDays(4)->toDateString();
        $opportunity->timeSlots()->create(['date' => $oldDate, 'start_time' => '09:00:00', 'end_time' => '12:00:00']);

        $this->actingAs($this->adminActor(), 'admin')
            ->get("/dashboard/volunteer-opportunities/{$opportunity->id}/edit")
            ->assertOk()
            ->assertSee($oldDate);

        $this->put("/dashboard/volunteer-opportunities/{$opportunity->id}", [
            ...$this->volunteerOpportunityPayload([
                'created_by' => $organization->id,
                'approval_status' => ApprovalStatus::APPROVED->value,
                'opportunity_status' => OpportunityStatus::UPCOMING->value,
            ]),
            'time_slots' => [['date' => $newDate, 'start_time' => '10:00', 'end_time' => '14:00']],
        ])->assertRedirect();

        $active = $opportunity->timeSlots()->notDeleted()->get();
        $this->assertCount(1, $active);
        $this->assertSame($newDate, $active->first()->date->toDateString());
    }

    public function test_be44_event_created_by_api_can_be_saved_unchanged_in_admin(): void
    {
        [$organization, $token] = $this->createOrganizationActor();
        $eventType = MasterChoice::query()->whereHas('choiceType', fn ($q) => $q->where('name', 'event_type'))->value('id');

        $event = $this->api($token)->postJson('/api/events/', [
            'title_en' => 'Incomplete event',
            'event_type_id' => $eventType,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ])->assertCreated();

        $this->actingAs($this->adminActor(), 'admin')->put('/dashboard/events/'.$event->json('data.id'), [
            'created_by' => $organization->organizationProfile->id,
            'title_en' => 'Incomplete event',
            'title_ar' => null,
            'description_en' => null,
            'description_ar' => null,
            'event_type_id' => $eventType,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'approval_status' => ApprovalStatus::PENDING->value,
            'event_status' => OpportunityStatus::UPCOMING->value,
        ])->assertRedirect();
    }

    public function test_be50_non_resident_can_update_profile_and_account_enforces_passport(): void
    {
        [$user, $token] = $this->createVolunteerActor();
        $user->update([
            'nationality' => 'other',
            'residency_status' => 'non_resident',
            'civil_id' => null,
            'passport_number' => 'PASS-500',
        ]);

        $this->api($token)->patchJson('/api/volunteer-profile/', ['nickname' => 'passport_holder'])
            ->assertOk()
            ->assertJsonPath('data.passport_number', 'PASS-500')
            ->assertJsonPath('data.residency_status', 'non_resident');

        $this->api($token)->postJson('/api/account/', ['passport_number' => null])
            ->assertStatus(422)
            ->assertJsonStructure(['response_status' => ['validation_errors' => ['passport_number']]]);
    }

    public function test_be50_non_resident_social_signup_accepts_passport_and_all_is_rejected(): void
    {
        $this->postJson('/api/social-auth/', [
            'email' => 'passport.social@test.com',
            'social_media_provider' => 'google',
            'social_media_id' => 'google-passport-1',
            'first_name' => 'Passport',
            'last_name' => 'Social',
            'user_type' => UserType::VOLUNTEER->value,
            'nationality' => 'other',
            'residency_status' => 'non_resident',
            'passport_number' => 'SOCIAL-PASS-1',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'passport.social@test.com',
            'residency_status' => 'non_resident',
            'passport_number' => 'SOCIAL-PASS-1',
            'civil_id' => null,
        ]);

        $this->postJson('/api/register/', [
            'email' => 'invalid.nationality@test.com',
            'password' => 'Password1',
            'user_type' => 'volunteer',
            'nationality' => 'all',
            'civil_id' => '445566778899',
        ])->assertStatus(422)
            ->assertJsonStructure(['response_status' => ['validation_errors' => ['nationality']]]);
    }

    public function test_be51_and_be53_choice_endpoints_return_exact_public_vocabularies(): void
    {
        $this->getJson('/api/choices/org_type/')
            ->assertOk()
            ->assertJsonPath('data.0.value_en', 'Governmental')
            ->assertJsonMissing(['value_en' => 'Volunteer Team']);

        $certificateValues = array_column(
            $this->getJson('/api/choices/certificate_filter_type/')->assertOk()->json('data'),
            'value_en'
        );
        $this->assertSame(['Volunteer', 'Course', 'Internship'], $certificateValues);
    }

    public function test_be53_choice_migration_can_restore_the_vocabulary_without_reseeding(): void
    {
        $migration = require database_path('migrations/2026_09_15_000002_add_certificate_filter_choices.php');
        $migration->down();
        $this->getJson('/api/choices/certificate_filter_type/')->assertNotFound();

        $migration->up();

        $values = array_column(
            $this->getJson('/api/choices/certificate_filter_type/')->assertOk()->json('data'),
            'value_en'
        );
        $this->assertSame(['Volunteer', 'Course', 'Internship'], $values);
    }

    public function test_be52_volunteer_real_name_is_neither_returned_nor_searchable(): void
    {
        [$volunteer] = $this->createVolunteerActor('private.name@test.com');
        $volunteer->update(['first_name' => 'SecretUniqueFirst', 'last_name' => 'SecretUniqueLast']);
        $nickname = $volunteer->volunteerProfile->nickname;

        $list = $this->getJson('/api/profiles/volunteers/')->assertOk();
        $payload = json_encode($list->json('data'), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString($nickname, $payload);
        $this->assertStringNotContainsString('SecretUniqueFirst', $payload);
        $this->assertStringNotContainsString('SecretUniqueLast', $payload);

        $searched = $this->getJson('/api/profiles/volunteers/?name=SecretUniqueFirst')->assertOk();
        $this->assertSame(0, $searched->json('meta.pagination.total'));
    }

    public function test_be53_certificate_filter_scopes_both_certificate_sources_and_rejects_unknown_values(): void
    {
        [$volunteer, $token] = $this->createVolunteerActor();
        [$organization] = $this->createOrganizationActor();
        $course = $this->learnOpportunity($organization->id, 'Course');
        $internship = $this->learnOpportunity($organization->id, 'Internship');
        $volunteering = VolunteerOpportunity::query()->create($this->volunteerOpportunityPayload([
            'created_by' => $organization->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
        ]));

        foreach ([$course, $internship] as $opportunity) {
            LearnServeOpportunityRegistration::query()->create([
                'opportunity_id' => $opportunity->id,
                'user_id' => $volunteer->id,
                'is_certified' => true,
            ]);
        }
        VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $volunteering->id,
            'user_id' => $volunteer->id,
            'is_certified' => true,
        ]);

        foreach (['Course' => 'learn_serve', 'Internship' => 'learn_serve', 'Volunteer' => 'volunteer'] as $filter => $registrationType) {
            $response = $this->api($token)->getJson('/api/user-certificates/?certificate_type='.$filter)->assertOk();
            $this->assertCount(1, $response->json('data'));
            $this->assertSame($registrationType, $response->json('data.0.registration_type'));
        }

        $this->api($token)->getJson('/api/user-certificates/?certificate_type=Class')
            ->assertStatus(422)
            ->assertJsonStructure(['response_status' => ['validation_errors' => ['certificate_type']]]);
    }

    public function test_be54_activity_role_filters_and_combined_counter_match_sync(): void
    {
        [$volunteer] = $this->createVolunteerActor();
        [$organization] = $this->createOrganizationActor();
        $provided = $this->learnOpportunity($volunteer->id, 'Course');
        $attended = $this->learnOpportunity($organization->id, 'Course');
        LearnServeOpportunityRegistration::query()->create([
            'opportunity_id' => $attended->id,
            'user_id' => $volunteer->id,
            'is_attended' => true,
        ]);

        foreach (['Participant' => $attended->id, 'provider' => $provided->id] as $tag => $expectedId) {
            $response = $this->getJson('/api/list-user-opportunities/?user_id='.$volunteer->id.'&opportunity_type=learn_serve_opportunity&profile_activity_tag='.$tag)->assertOk();
            $this->assertCount(1, $response->json('data'));
            $this->assertSame($expectedId, $response->json('data.0.id'));
        }

        $this->getJson('/api/list-user-opportunities/?user_id='.$volunteer->id.'&opportunity_type=learn_serve_opportunity&profile_activity_tag=wrong')
            ->assertStatus(422)
            ->assertJsonStructure(['response_status' => ['validation_errors' => ['profile_activity_tag']]]);

        $this->assertTrue(SyncService::syncVolunteer($volunteer->id));
        $profile = $volunteer->volunteerProfile->fresh();
        $this->assertSame(1, $profile->total_opportunities);
        $this->assertSame(1, $profile->opportunities_organized);

        $this->getJson('/api/public-profile/'.$volunteer->id.'/')
            ->assertOk()
            ->assertJsonPath('data.profile_data.development_opportunities_count', 2);
    }

    public function test_be55_public_and_owner_profiles_return_all_time_organization_rollups(): void
    {
        [$organization, $token] = $this->createOrganizationActor();
        OrganizationStatistic::query()->create([
            'user_id' => $organization->id,
            'year' => 2025,
            'month' => null,
            'organization_hours' => 11,
            'vol_opportunity_organized' => 2,
            'learn_opportunity_organized' => 3,
            'sponsored' => 4,
        ]);
        OrganizationStatistic::query()->create([
            'user_id' => $organization->id,
            'year' => 2026,
            'month' => null,
            'organization_hours' => 7,
            'vol_opportunity_organized' => 1,
            'learn_opportunity_organized' => 2,
            'sponsored' => 1,
        ]);

        $this->getJson('/api/public-profile/'.$organization->id.'/')
            ->assertOk()
            ->assertJsonPath('data.profile_data.organization_hours', 18)
            ->assertJsonPath('data.profile_data.vol_opportunity_organized', 3)
            ->assertJsonPath('data.profile_data.learn_opportunity_organized', 5)
            ->assertJsonPath('data.profile_data.sponsored', 5);

        $this->api($token)->getJson('/api/organization-profile/')
            ->assertOk()
            ->assertJsonPath('data.organization_hours', 18)
            ->assertJsonPath('data.vol_opportunity_organized', 3)
            ->assertJsonPath('data.learn_opportunity_organized', 5)
            ->assertJsonPath('data.sponsored_count', 5);
    }

    protected function learnOpportunity(int $creatorId, string $learningType): LearnServeOpportunity
    {
        $typeId = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'learning_type'))
            ->where('value_en', $learningType)
            ->value('id');

        return LearnServeOpportunity::query()->create([
            'created_by' => $creatorId,
            'title_en' => $learningType,
            'title_ar' => $learningType,
            'description_en' => 'Description',
            'description_ar' => 'Description',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'participants_needed' => 10,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
            'learning_type_id' => $typeId,
        ]);
    }

    /** @return array<string, mixed> */
    protected function volunteerOpportunityPayload(array $overrides = []): array
    {
        return array_merge([
            'title_en' => 'Completion test opportunity',
            'title_ar' => 'فرصة اختبار',
            'description_en' => 'Description',
            'description_ar' => 'وصف',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'participants_needed' => 5,
            'volunteer_category' => VolunteerCategory::ENVIRONMENTAL->value,
        ], $overrides);
    }

    protected function api(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders([
            'Authorization' => 'Token '.$token,
            'Accept' => 'application/json',
            'Lang' => 'en',
        ]);
    }
}
