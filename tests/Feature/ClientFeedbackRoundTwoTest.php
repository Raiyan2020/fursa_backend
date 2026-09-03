<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\VolunteerCategory;
use App\Models\Config;
use App\Models\Interest;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityAttendance;
use App\Models\VolunteerOpportunityRegistration;
use App\Models\VolunteerProfile;
use App\Services\Opportunity\AttendanceService;
use App\Services\Opportunity\OpportunityChangeNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AssertsDjangoApiEnvelope;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * Covers the second round of client feedback: emergency priority, listing
 * order, backdating, volunteer categories, beneficiaries, and the reworked
 * check-in window (manual + QR, editable hours, undo, admin reopen).
 */
class ClientFeedbackRoundTwoTest extends TestCase
{
    use AssertsDjangoApiEnvelope;
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed();
    }

    // ---------------------------------------------------------------
    // 1) Opportunities
    // ---------------------------------------------------------------

    public function test_started_opportunities_sort_below_ones_still_open_for_registration(): void
    {
        [$org] = $this->createOrganizationActor();

        $started = $this->makeOpportunity($org, [
            'title_en' => 'Already started',
            'opportunity_status' => OpportunityStatus::INPROGRESS,
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);

        $upcoming = $this->makeOpportunity($org, [
            'title_en' => 'Still upcoming',
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
        ]);

        $response = $this->getJson('/api/list-volunteer-opportunities/');
        $response->assertOk();

        $ids = array_column($response->json('data') ?? [], 'id');
        $this->assertContains($upcoming->id, $ids);
        $this->assertContains($started->id, $ids);

        $this->assertLessThan(
            array_search($started->id, $ids, true),
            array_search($upcoming->id, $ids, true),
            'An opportunity that already started must rank below one still open for registration.'
        );
    }

    public function test_emergency_opportunity_ranks_first(): void
    {
        [$org] = $this->createOrganizationActor();

        $this->makeOpportunity($org, [
            'title_en' => 'Ordinary upcoming',
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        $emergency = $this->makeOpportunity($org, [
            'title_en' => 'Emergency',
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'is_emergency' => true,
            'start_date' => now()->addDays(4)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
        ]);

        $ids = array_column($this->getJson('/api/list-volunteer-opportunities/')->json('data') ?? [], 'id');

        $this->assertSame($emergency->id, $ids[0] ?? null);
    }

    public function test_same_day_due_date_at_midnight_still_ranks_as_open(): void
    {
        [$org] = $this->createOrganizationActor();

        $stale = $this->makeOpportunity($org, [
            'title_en' => 'Long finished',
            'opportunity_status' => OpportunityStatus::COMPLETED,
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->subMonths(6)->addDay()->toDateString(),
            'due_date' => now()->subMonths(6)->toDateString(),
        ]);

        $dueToday = $this->makeOpportunity($org, [
            'title_en' => 'Due today at midnight',
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'due_date' => now()->startOfDay(),
        ]);

        $ids = array_column($this->getJson('/api/list-volunteer-opportunities/')->json('data') ?? [], 'id');

        $this->assertLessThan(
            array_search($stale->id, $ids, true),
            array_search($dueToday->id, $ids, true),
            'A due_date at midnight on the current day must still rank as open, not fall behind long-completed opportunities.'
        );
    }

    public function test_sort_by_newest_reverses_the_default_oldest_first_order(): void
    {
        [$org] = $this->createOrganizationActor();

        $earlier = $this->makeOpportunity($org, [
            'title_en' => 'Earlier',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ]);
        $later = $this->makeOpportunity($org, [
            'title_en' => 'Later',
            'start_date' => now()->addDays(9)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
        ]);

        $oldestFirst = array_column($this->getJson('/api/list-volunteer-opportunities/')->json('data'), 'id');
        $this->assertLessThan(
            array_search($later->id, $oldestFirst, true),
            array_search($earlier->id, $oldestFirst, true),
            'Default order (no sort_by) must rank the earlier start_date first.'
        );

        $newestFirst = array_column($this->getJson('/api/list-volunteer-opportunities/?sort_by=newest')->json('data'), 'id');
        $this->assertLessThan(
            array_search($earlier->id, $newestFirst, true),
            array_search($later->id, $newestFirst, true),
            'sort_by=newest must rank the later start_date first.'
        );
    }

    public function test_opportunity_details_includes_interest_display(): void
    {
        [$org, $token] = $this->createOrganizationActor();

        $interest = Interest::query()->create([
            'name_en' => 'Environment',
            'name_ar' => 'البيئة',
            'interest_type' => \App\Enums\InterestType::VOLUNTEER,
        ]);

        $opportunity = $this->makeOpportunity($org);
        $opportunity->interests()->sync([$interest->id]);

        $response = $this->api($token)->getJson("/api/opportunities/{$opportunity->id}/details/");
        $this->assertSuccessEnvelope($response);

        $response->assertJsonPath('data.interest_display', [[
            'id' => $interest->id,
            'choice_type' => 'volunteer_opportunity_interest',
            'value_en' => 'Environment',
            'value_ar' => 'البيئة',
        ]]);
    }

    public function test_event_details_and_write_responses_include_interests_shape(): void
    {
        [$org, $token] = $this->createOrganizationActor();

        $interest = Interest::query()->create([
            'name_en' => 'Charity Work',
            'name_ar' => 'أعمال خيرية',
            'interest_type' => \App\Enums\InterestType::VOLUNTEER,
        ]);

        $event = \App\Models\Event::query()->create([
            'created_by' => $org->organizationProfile->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'event_status' => 'upcoming',
            'title_en' => 'Ramadan Meal Distribution',
            'title_ar' => 'إفطار صائم',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'registration_required' => false,
            'participants_needed' => 10,
            'is_deleted' => false,
        ]);
        $event->interests()->sync([$interest->id]);

        // GET /events/{id}/ (the correct detail endpoint for events) exposes
        // the new `interests` shape, not just the legacy `interest_display`.
        $show = $this->getJson("/api/events/{$event->id}/");
        $this->assertSuccessEnvelope($show);
        $show->assertJsonPath('data.interests', [[
            'id' => $interest->id,
            'name_en' => 'Charity Work',
            'name_ar' => 'أعمال خيرية',
            'interest_type' => 'volunteer',
        ]]);
        $show->assertJsonPath('data.interest_display', [[
            'value_en' => 'Charity Work',
            'value_ar' => 'أعمال خيرية',
        ]]);

        // The wrong-but-easily-confused endpoint (VolunteerOpportunity-only)
        // must 404 for an event id rather than silently returning empty data.
        $this->getJson("/api/opportunities/{$event->id}/details/")->assertNotFound();

        // The write-path resource (create/update/close-registration) also
        // used to return `interests` as a bare array of ids with no
        // `interest_display` at all — now matches the read-path shape.
        $update = $this->api($token)->patchJson("/api/events/{$event->id}/", [
            'title_en' => 'Ramadan Meal Distribution (Updated)',
        ]);
        $update->assertSuccessful();
        $update->assertJsonPath('data.interests', [[
            'id' => $interest->id,
            'name_en' => 'Charity Work',
            'name_ar' => 'أعمال خيرية',
            'interest_type' => 'volunteer',
        ]]);
        $update->assertJsonPath('data.interest_display', [[
            'id' => $interest->id,
            'choice_type' => 'event_interest',
            'value_en' => 'Charity Work',
            'value_ar' => 'أعمال خيرية',
        ]]);
    }

    public function test_profile_activity_tag_shows_registered_to_owner_only(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        [, $strangerToken] = $this->createVolunteerActor('stranger@test.com');

        $opportunity = $this->makeOpportunity($org);
        $this->registerVolunteer($opportunity, $volunteer);

        $ownView = $this->api($volunteerToken)
            ->getJson('/api/list-user-opportunities/?user_id='.$volunteer->id.'&filter_type=registered');
        $ownItem = collect($ownView->json('data'))->firstWhere('id', $opportunity->id);
        $this->assertSame('registered', $ownItem['profile_activity_tag']);

        $publicView = $this->api($strangerToken)
            ->getJson('/api/list-user-opportunities/?user_id='.$volunteer->id.'&filter_type=registered');
        $publicItem = collect($publicView->json('data'))->firstWhere('id', $opportunity->id);
        $this->assertNull($publicItem['profile_activity_tag'], 'A public viewer must never see the "registered" tag.');
    }

    public function test_list_user_opportunities_applies_tags_date_range_and_pagination(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();

        $tagged = $this->makeOpportunity($org, [
            'title_en' => 'Tagged Opportunity',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(12)->toDateString(),
        ]);
        $interest = Interest::query()->create([
            'name_en' => 'rrr',
            'name_ar' => 'rrr',
            'interest_type' => \App\Enums\InterestType::VOLUNTEER,
        ]);
        $tagged->interests()->sync([$interest->id]);
        $this->registerVolunteer($tagged, $volunteer);

        $untagged = $this->makeOpportunity($org, [
            'title_en' => 'Untagged Opportunity',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(12)->toDateString(),
        ]);
        $this->registerVolunteer($untagged, $volunteer);

        $base = '/api/list-user-opportunities/?user_id='.$volunteer->id.'&filter_type=registered';

        // tags[]: only the tagged opportunity should come back.
        $byTag = $this->api($volunteerToken)->getJson($base.'&tags[]=rrr');
        $tagIds = collect($byTag->json('data'))->pluck('id');
        $this->assertTrue($tagIds->contains($tagged->id));
        $this->assertFalse($tagIds->contains($untagged->id));

        // start_date/end_date: a window that excludes both opportunities.
        $byDate = $this->api($volunteerToken)->getJson(
            $base.'&start_date='.now()->addDays(30)->toDateString().'&end_date='.now()->addDays(40)->toDateString()
        );
        $dateIds = collect($byDate->json('data'))->pluck('id');
        $this->assertFalse($dateIds->contains($tagged->id));
        $this->assertFalse($dateIds->contains($untagged->id));

        // page/limit: response is actually paginated, not the full unbounded list.
        $paged = $this->api($volunteerToken)->getJson($base.'&page=1&limit=1');
        $this->assertCount(1, $paged->json('data'));
        $this->assertSame(2, $paged->json('meta.pagination.total'));
    }

    public function test_profile_activity_tag_shows_attended_to_everyone(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        [, $strangerToken] = $this->createVolunteerActor('stranger2@test.com');

        $opportunity = $this->makeOpportunity($org);
        $registration = $this->registerVolunteer($opportunity, $volunteer);
        $registration->attendances()->create([
            'attended_date' => now()->toDateString(),
            'total_hours' => 2,
            'is_attended' => true,
        ]);

        $publicView = $this->api($strangerToken)
            ->getJson('/api/list-user-opportunities/?user_id='.$volunteer->id.'&filter_type=registered');
        $publicItem = collect($publicView->json('data'))->firstWhere('id', $opportunity->id);
        $this->assertSame('attended', $publicItem['profile_activity_tag']);
    }

    public function test_profile_activity_tag_uses_provider_and_participant_for_development_opportunities(): void
    {
        [$provider, $providerToken] = $this->createOrganizationActor('ls-provider@test.com');
        [$participant] = $this->createVolunteerActor('ls-participant@test.com');
        [, $strangerToken] = $this->createVolunteerActor('ls-stranger@test.com');

        $course = LearnServeOpportunity::query()->create([
            'title_en' => 'Leadership Course', 'title_ar' => 'دورة القيادة',
            'description_en' => 'd', 'description_ar' => 'و',
            'created_by' => $provider->id,
            'approval_status' => \App\Enums\ApprovalStatus::APPROVED,
            'opportunity_status' => \App\Enums\OpportunityStatus::UPCOMING,
            'participants_needed' => 5,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ]);
        LearnServeOpportunityRegistration::query()->create([
            'opportunity_id' => $course->id,
            'user_id' => $participant->id,
            'registration_date' => now(),
            'status' => \App\Enums\ApprovalStatus::APPROVED,
            'is_attended' => true,
        ]);

        // The provider isn't "registered" for their own course — they created
        // it — so `list-user-opportunities` (registration-based) won't surface
        // it; `list-all-opportunities` includes anything the profile owner
        // created too.
        $providerFeed = $this->api($strangerToken)
            ->getJson('/api/list-all-opportunities/?user_id='.$provider->id.'&opportunity_type=learn');
        $providerItem = collect($providerFeed->json('data'))->firstWhere('id', $course->id);
        $this->assertSame('provider', $providerItem['profile_activity_tag']);

        $participantView = $this->api($strangerToken)
            ->getJson('/api/list-user-opportunities/?user_id='.$participant->id.'&filter_type=registered&opportunity_type=learn');
        $participantItem = collect($participantView->json('data'))->firstWhere('id', $course->id);
        $this->assertSame('participant', $participantItem['profile_activity_tag']);
    }

    public function test_development_opportunities_counter_replaces_certificates_counter(): void
    {
        [$provider] = $this->createOrganizationActor('dev-counter-provider@test.com');
        [$volunteer, $volunteerToken] = $this->createVolunteerActor('dev-counter-vol@test.com');

        $course = LearnServeOpportunity::query()->create([
            'title_en' => 'Leadership Course', 'title_ar' => 'دورة القيادة',
            'description_en' => 'd', 'description_ar' => 'و',
            'created_by' => $provider->id,
            'approval_status' => \App\Enums\ApprovalStatus::APPROVED,
            'opportunity_status' => \App\Enums\OpportunityStatus::UPCOMING,
            'participants_needed' => 5,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ]);
        LearnServeOpportunityRegistration::query()->create([
            'opportunity_id' => $course->id,
            'user_id' => $volunteer->id,
            'registration_date' => now(),
            'status' => \App\Enums\ApprovalStatus::APPROVED,
            'is_attended' => true,
            'is_certified' => true,
        ]);

        $profile = $this->api($volunteerToken)->getJson('/api/volunteer-profile/');
        $this->assertSuccessEnvelope($profile);
        $profile->assertJsonPath('data.development_opportunities_count', 1);
        $profile->assertJsonPath('data.counter_visibility.certificates', false);
        $profile->assertJsonPath('data.counter_visibility.development', true);

        // The certificates tab itself is untouched by hiding the counter.
        $certificatesTab = $this->getJson('/api/user-certificates/?user_id='.$volunteer->id);
        $this->assertCount(1, $certificatesTab->json('data'));
    }

    public function test_organizer_can_reopen_closed_registration(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        $opportunity = $this->makeOpportunity($org, ['is_registration_closed' => true]);

        $response = $this->api($token)
            ->postJson("/api/volunteer-opportunities/{$opportunity->id}/reopen-registration/");

        $this->assertSuccessEnvelope($response, 200, 'Registration reopened successfully.');
        $this->assertFalse($opportunity->fresh()->is_registration_closed);
    }

    public function test_organizer_cannot_reopen_someone_elses_opportunity(): void
    {
        [$org] = $this->createOrganizationActor('owner.reg@test.com');
        [, $otherToken] = $this->createOrganizationActor('other.reg@test.com');
        $opportunity = $this->makeOpportunity($org, ['is_registration_closed' => true]);

        $this->api($otherToken)
            ->postJson("/api/volunteer-opportunities/{$opportunity->id}/reopen-registration/")
            ->assertNotFound();

        $this->assertTrue($opportunity->fresh()->is_registration_closed);
    }

    public function test_organizer_can_resubmit_a_rejected_opportunity(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        $opportunity = $this->makeOpportunity($org, [
            'approval_status' => ApprovalStatus::REJECTED,
            'rejected_reason' => 'Missing details',
        ]);

        $response = $this->api($token)
            ->postJson("/api/volunteer-opportunities/{$opportunity->id}/resubmit/");

        $this->assertSuccessEnvelope($response, 200, 'Opportunity resubmitted for approval.');

        $fresh = $opportunity->fresh();
        $this->assertSame(ApprovalStatus::PENDING, $fresh->approval_status);
        $this->assertNull($fresh->rejected_reason);
    }

    public function test_resubmit_rejects_an_opportunity_that_was_not_rejected(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        $opportunity = $this->makeOpportunity($org, ['approval_status' => ApprovalStatus::APPROVED]);

        $this->api($token)
            ->postJson("/api/volunteer-opportunities/{$opportunity->id}/resubmit/")
            ->assertStatus(400);

        $this->assertSame(ApprovalStatus::APPROVED, $opportunity->fresh()->approval_status);
    }

    public function test_organizer_can_add_and_remove_a_sponsor_on_a_volunteer_opportunity(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        [$sponsorOrg] = $this->createOrganizationActor('sponsor.eligible@test.com');
        $opportunity = $this->makeOpportunity($org);

        $add = $this->api($token)->postJson("/api/volunteer-opportunities/{$opportunity->id}/sponsors/", [
            'organization_id' => $sponsorOrg->organizationProfile->id,
        ]);
        $this->assertSuccessEnvelope($add, 201, 'Sponsor added successfully.');

        $show = $this->api($token)->getJson("/api/volunteer-opportunities/{$opportunity->id}/");
        $this->assertCount(1, $show->json('data.opportunity_sponsor_images'));

        $sponsorId = $add->json('data.id');
        $remove = $this->api($token)->deleteJson("/api/volunteer-opportunities/{$opportunity->id}/sponsors/{$sponsorId}/");
        $this->assertSuccessEnvelope($remove, 200, 'Sponsor removed successfully.');

        $show = $this->api($token)->getJson("/api/volunteer-opportunities/{$opportunity->id}/");
        $this->assertCount(0, $show->json('data.opportunity_sponsor_images'));
    }

    public function test_volunteer_team_cannot_be_added_as_a_sponsor(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        [$team] = $this->createVolunteerTeamActor();
        $opportunity = $this->makeOpportunity($org);

        $this->api($token)->postJson("/api/volunteer-opportunities/{$opportunity->id}/sponsors/", [
            'organization_id' => $team->organizationProfile->id,
        ])->assertStatus(422);
    }

    public function test_sponsoring_the_same_organization_twice_is_rejected(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        [$sponsorOrg] = $this->createOrganizationActor('sponsor.dup@test.com');
        $opportunity = $this->makeOpportunity($org);

        $this->api($token)->postJson("/api/volunteer-opportunities/{$opportunity->id}/sponsors/", [
            'organization_id' => $sponsorOrg->organizationProfile->id,
        ])->assertCreated();

        $this->api($token)->postJson("/api/volunteer-opportunities/{$opportunity->id}/sponsors/", [
            'organization_id' => $sponsorOrg->organizationProfile->id,
        ])->assertStatus(400);
    }

    public function test_organizer_can_add_a_sponsor_on_a_development_opportunity(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        [$sponsorOrg] = $this->createOrganizationActor('sponsor.learn@test.com');

        $create = $this->api($token)->postJson('/api/learn-serve-opportunities/', [
            'title_en' => 'Leadership Course',
            'title_ar' => 'دورة القيادة',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'participants_needed' => 8,
        ]);
        $opportunityId = (int) $create->json('data.id');

        $add = $this->api($token)->postJson("/api/learn-serve-opportunities/{$opportunityId}/sponsors/", [
            'organization_id' => $sponsorOrg->organizationProfile->id,
        ]);
        $this->assertSuccessEnvelope($add, 201, 'Sponsor added successfully.');

        $show = $this->api($token)->getJson("/api/learn-serve-opportunities/{$opportunityId}/");
        $this->assertCount(1, $show->json('data.opportunity_sponsor_images'));
    }

    public function test_beneficiaries_count_is_kept_only_for_charity_category(): void
    {
        [$org, $token] = $this->createOrganizationActor();

        $charity = $this->api($token)->postJson('/api/volunteer-opportunities/', $this->opportunityPayload([
            'volunteer_category' => VolunteerCategory::CHARITY->value,
            'beneficiaries_count' => 250,
        ]));
        $charity->assertSuccessful();
        $this->assertSame(250, $charity->json('data.beneficiaries_count'));
        $this->assertTrue($charity->json('data.supports_beneficiaries_count'));

        $environmental = $this->api($token)->postJson('/api/volunteer-opportunities/', $this->opportunityPayload([
            'volunteer_category' => VolunteerCategory::ENVIRONMENTAL->value,
            'beneficiaries_count' => 500,
        ]));
        $environmental->assertSuccessful();
        $this->assertNull($environmental->json('data.beneficiaries_count'));
        $this->assertFalse($environmental->json('data.supports_beneficiaries_count'));

        $this->assertNull(
            VolunteerOpportunity::query()->find($environmental->json('data.id'))->beneficiaries_count,
            'A non-charity opportunity must not persist a beneficiaries figure.'
        );
    }

    public function test_volunteer_category_rejects_unknown_values(): void
    {
        [, $token] = $this->createOrganizationActor();

        $this->api($token)
            ->postJson('/api/volunteer-opportunities/', $this->opportunityPayload([
                'volunteer_category' => 'not-a-category',
            ]))
            ->assertStatus(422);
    }

    // ---------------------------------------------------------------
    // 2) Stats
    // ---------------------------------------------------------------

    public function test_statistics_beneficiaries_sum_charity_opportunities_and_course_learners(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $this->makeOpportunity($org, [
            'volunteer_category' => VolunteerCategory::CHARITY->value,
            'beneficiaries_count' => 120,
        ]);
        // Environmental beneficiaries must be ignored even if a value slipped in.
        $this->makeOpportunity($org, [
            'volunteer_category' => VolunteerCategory::ENVIRONMENTAL->value,
            'beneficiaries_count' => 999,
        ]);

        $course = LearnServeOpportunity::query()->create([
            'title_en' => 'Course',
            'title_ar' => 'دورة',
            'description_en' => 'd',
            'description_ar' => 'و',
            'created_by' => $org->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
        ]);

        LearnServeOpportunityRegistration::query()->create([
            'opportunity_id' => $course->id,
            'user_id' => $volunteer->id,
            'is_attended' => true,
        ]);

        $response = $this->getJson('/api/statistics/');
        $response->assertOk();

        $this->assertSame(120, $response->json('data.beneficiaries_breakdown.volunteer_opportunities'));
        $this->assertSame(1, $response->json('data.beneficiaries_breakdown.course_learners'));
        $this->assertSame(121, $response->json('data.beneficiaries_count'));
    }

    // ---------------------------------------------------------------
    // 3) Check-in window
    // ---------------------------------------------------------------

    public function test_check_in_window_defaults_to_seventy_two_hours(): void
    {
        [$org] = $this->createOrganizationActor();

        Config::query()->update([
            'preparation_validity_hours' => 72,
        ]);

        $opportunity = $this->makeOpportunity($org, [
            'end_date' => now()->subDay()->toDateString(),
        ]);

        $until = $opportunity->fresh()->preparationValidUntil();

        $this->assertNotNull($until);
        $this->assertSame(
            now()->subDay()->endOfDay()->addHours(72)->toDateTimeString(),
            $until->toDateTimeString()
        );
    }

    public function test_manual_check_in_records_custom_hours_alongside_qr(): void
    {
        [$org, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = $this->makeOpportunity($org, [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $registration = $this->registerVolunteer($opportunity, $volunteer);

        $response = $this->api($orgToken)->postJson('/api/volunteer-attendance/manual/', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'total_hours' => 3.5,
        ]);

        $response->assertSuccessful();

        $attendance = VolunteerOpportunityAttendance::query()
            ->where('registration_id', $registration->id)
            ->firstOrFail();

        $this->assertSame(3.5, (float) $attendance->total_hours);
        $this->assertSame(AttendanceService::VIA_MANUAL, $attendance->recorded_via);
        $this->assertSame(3.5, (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours);
    }

    public function test_manual_check_in_falls_back_to_the_opportunity_length(): void
    {
        [$org, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = $this->makeOpportunity($org, [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);

        $this->registerVolunteer($opportunity, $volunteer);

        $this->api($orgToken)->postJson('/api/volunteer-attendance/manual/', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
        ])->assertSuccessful();

        $this->assertSame(4.0, (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours);
    }

    public function test_editing_attendance_hours_applies_only_the_difference(): void
    {
        [$org, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = $this->makeOpportunity($org, [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
        $registration = $this->registerVolunteer($opportunity, $volunteer);

        $attendance = AttendanceService::record(
            $registration,
            $opportunity,
            now()->toDateString(),
            4.0,
            AttendanceService::VIA_MANUAL,
            $org->id
        );

        $this->assertSame(4.0, (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours);

        $this->api($orgToken)
            ->patchJson("/api/volunteer-attendance/{$attendance->id}/hours/", ['total_hours' => 6.5])
            ->assertSuccessful();

        $this->assertSame(6.5, (float) $attendance->fresh()->total_hours);
        $this->assertSame(6.5, (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours);
    }

    public function test_undo_check_in_gives_back_the_hours(): void
    {
        [$org, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = $this->makeOpportunity($org, [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
        $registration = $this->registerVolunteer($opportunity, $volunteer);

        $attendance = AttendanceService::record(
            $registration,
            $opportunity,
            now()->toDateString(),
            5.0,
            AttendanceService::VIA_QR,
            $org->id
        );

        $this->assertSame(5.0, (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours);

        $this->api($orgToken)
            ->postJson("/api/volunteer-attendance/{$attendance->id}/undo/")
            ->assertSuccessful();

        $this->assertTrue((bool) $attendance->fresh()->is_deleted);
        $this->assertFalse((bool) $attendance->fresh()->is_attended);
        $this->assertSame(0.0, (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours);
    }

    public function test_undo_then_recheck_in_does_not_double_count(): void
    {
        [$org, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = $this->makeOpportunity($org, [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
        $registration = $this->registerVolunteer($opportunity, $volunteer);

        $attendance = AttendanceService::record(
            $registration,
            $opportunity,
            now()->toDateString(),
            5.0,
            AttendanceService::VIA_QR,
            $org->id
        );

        AttendanceService::undo($attendance);

        $this->api($orgToken)->postJson('/api/volunteer-attendance/manual/', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'total_hours' => 2.0,
        ])->assertSuccessful();

        $this->assertSame(2.0, (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours);
    }

    public function test_manual_check_in_is_rejected_once_the_window_closed(): void
    {
        [$org, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        Config::query()->update(['preparation_validity_hours' => 72]);

        $opportunity = $this->makeOpportunity($org, [
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date' => now()->subDays(15)->toDateString(),
        ]);
        $this->registerVolunteer($opportunity, $volunteer);

        $this->api($orgToken)->postJson('/api/volunteer-attendance/manual/', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
        ])->assertStatus(400);
    }

    public function test_admin_can_reopen_a_closed_check_in_window(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        [, $staffToken] = $this->createStaffActor();

        Config::query()->update(['preparation_validity_hours' => 72]);

        $opportunity = $this->makeOpportunity($org, [
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date' => now()->subDays(15)->toDateString(),
        ]);
        $this->registerVolunteer($opportunity, $volunteer);

        $this->assertTrue($opportunity->fresh()->isPreparationWindowClosed());

        $this->api($staffToken)
            ->postJson("/api/admin/volunteer-opportunities/{$opportunity->id}/reopen-check-in/", [
                'extra_hours' => 48,
            ])
            ->assertSuccessful();

        $this->assertFalse($opportunity->fresh()->isPreparationWindowClosed());
    }

    public function test_non_admin_cannot_reopen_a_check_in_window(): void
    {
        [$org, $orgToken] = $this->createOrganizationActor();

        $opportunity = $this->makeOpportunity($org, [
            'end_date' => now()->subDays(15)->toDateString(),
        ]);

        $this->api($orgToken)
            ->postJson("/api/admin/volunteer-opportunities/{$opportunity->id}/reopen-check-in/", [
                'extra_hours' => 24,
            ])
            ->assertStatus(403);
    }

    public function test_a_stranger_cannot_undo_someone_elses_attendance(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        [, $otherToken] = $this->createOrganizationActor();

        $opportunity = $this->makeOpportunity($org, [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
        $registration = $this->registerVolunteer($opportunity, $volunteer);

        $attendance = AttendanceService::record(
            $registration,
            $opportunity,
            now()->toDateString(),
            5.0,
            AttendanceService::VIA_QR,
            $org->id
        );

        $this->api($otherToken)
            ->postJson("/api/volunteer-attendance/{$attendance->id}/undo/")
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // 4) Update email diff
    // ---------------------------------------------------------------

    public function test_update_diff_reports_the_old_and_new_value(): void
    {
        $changes = OpportunityChangeNotifier::diff(
            ['start_date' => '2026-09-01', 'participants_needed' => 10],
            ['start_date' => '2026-09-08', 'participants_needed' => 10],
        );

        $this->assertCount(1, $changes);
        $this->assertSame('start_date', $changes[0]['field']);
        $this->assertSame('2026-09-01', $changes[0]['old']);
        $this->assertSame('2026-09-08', $changes[0]['new']);
        $this->assertStringContainsString('2026-09-01', $changes[0]['line_en']);
        $this->assertStringContainsString('2026-09-08', $changes[0]['line_en']);
        $this->assertStringContainsString('→', $changes[0]['line_en']);
    }

    public function test_update_diff_names_empty_values_instead_of_leaving_a_gap(): void
    {
        $changes = OpportunityChangeNotifier::diff(
            ['location_en' => null],
            ['location_en' => 'Kuwait City'],
        );

        $this->assertCount(1, $changes);
        $this->assertStringContainsString('(empty)', $changes[0]['line_en']);
        $this->assertStringContainsString('(فارغ)', $changes[0]['line_ar']);
    }

    public function test_update_diff_renders_booleans_readably(): void
    {
        $changes = OpportunityChangeNotifier::diff(
            ['is_registration_closed' => false],
            ['is_registration_closed' => true],
        );

        $this->assertCount(1, $changes);
        $this->assertStringContainsString('No', $changes[0]['line_en']);
        $this->assertStringContainsString('Yes', $changes[0]['line_en']);
        $this->assertStringContainsString('نعم', $changes[0]['line_ar']);
    }

    // ---------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeOpportunity(User $owner, array $overrides = []): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create(array_merge([
            'title_en' => 'Opportunity '.uniqid(),
            'title_ar' => 'فرصة',
            'description_en' => 'Description',
            'description_ar' => 'وصف',
            'created_by' => $owner->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'is_public' => true,
            'participants_needed' => 10,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ], $overrides));
    }

    protected function registerVolunteer(
        VolunteerOpportunity $opportunity,
        User $volunteer
    ): VolunteerOpportunityRegistration {
        return VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function opportunityPayload(array $overrides = []): array
    {
        return array_merge([
            'title_en' => 'Feedback Opportunity',
            'title_ar' => 'فرصة',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'participants_needed' => 8,
            'from_age' => 16,
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
