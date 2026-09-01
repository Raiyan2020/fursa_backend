<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\MasterChoice;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerStatistic;
use App\Services\Opportunity\OpportunityChangeNotifier;
use App\Services\Opportunity\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AssertsDjangoApiEnvelope;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class OwnerReportBackendTest extends TestCase
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

    public function test_quarter_tops_only_include_current_quarter_hours(): void
    {
        [$currentVolunteer] = $this->createVolunteerActor('quarter.current@test.com');
        [$oldVolunteer] = $this->createVolunteerActor('quarter.old@test.com');

        $year = (int) now()->year;
        $currentMonth = now()->month;
        $previousQuarterMonth = $currentMonth <= 3 ? 12 : $currentMonth - 3;
        $previousYear = $currentMonth <= 3 ? $year - 1 : $year;

        VolunteerStatistic::query()->create([
            'user_id' => $currentVolunteer->id,
            'year' => $year,
            'month' => $currentMonth,
            'volunteer_hours' => 40,
        ]);
        VolunteerStatistic::query()->create([
            'user_id' => $oldVolunteer->id,
            'year' => $previousYear,
            'month' => $previousQuarterMonth,
            'volunteer_hours' => 200,
        ]);

        $response = $this->getJson('/api/statistics/top/');
        $this->assertSuccessEnvelope($response, 200, 'Top statistics retrieved successfully.');
        $this->assertSame('quarterly', $response->json('data.cycle_type'));
        $this->assertSame('current', $response->json('data.cycle_scope'));
        $this->assertNotEmpty($response->json('data.start_date'));
        $this->assertNotEmpty($response->json('data.end_date'));

        $ids = collect($response->json('data.top_individuals'))->pluck('user_id')->all();
        $this->assertContains($currentVolunteer->id, $ids);
        $this->assertNotContains($oldVolunteer->id, $ids);

        $first = collect($response->json('data.top_individuals'))->firstWhere('user_id', $currentVolunteer->id);
        $this->assertSame($currentVolunteer->id, $first['user_id']);
        $this->assertArrayHasKey('volunteer_hours', $first);
        $this->assertArrayHasKey('organizing_hours', $first);
        $this->assertArrayHasKey('gender_display', $first);
        $this->assertArrayHasKey('is_public', $first);
        $this->assertSame('volunteer', $first['user_type']);
        $this->assertIsArray($response->json('data.top_volunteer_teams'));
        $this->assertIsArray($response->json('data.top_companies_and_government'));
    }

    public function test_statistics_include_economic_impact(): void
    {
        $response = $this->getJson('/api/statistics/');
        $this->assertSuccessEnvelope($response);
        $this->assertEquals(6, (float) $response->json('data.economic_impact_rate_kwd'));
        $this->assertArrayHasKey('economic_impact_kwd', $response->json('data'));
        $this->assertArrayHasKey('development_opportunities_completed', $response->json('data'));
        $this->assertArrayHasKey('outside_kuwait_trips', $response->json('data'));
    }

    public function test_certificate_preview_returns_arabic_course_title(): void
    {
        [, $organizationToken] = $this->createOrganizationActor('cert.org@test.com');
        [$volunteer] = $this->createVolunteerActor('cert.vol@test.com');

        $create = $this->api($organizationToken)->postJson('/api/learn-serve-opportunities/', [
            'title_en' => 'Leadership Course',
            'title_ar' => 'دورة القيادة',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'participants_needed' => 5,
        ]);
        $opportunityId = (int) $create->json('data.id');

        $registration = LearnServeOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunityId,
            'user_id' => $volunteer->id,
            'registration_date' => now(),
            'status' => ApprovalStatus::APPROVED,
            'is_certified' => true,
        ]);

        $preview = $this->withHeaders(['Lang' => 'ar', 'Accept' => 'application/json'])
            ->getJson('/api/certificate/preview/'.$registration->id.'/');
        $this->assertSuccessEnvelope($preview);
        $preview->assertJsonPath('data.course_ar', 'دورة القيادة')
            ->assertJsonPath('data.course', 'دورة القيادة');
    }

    public function test_certificates_tab_includes_the_organizing_entity_name(): void
    {
        [$org, $organizationToken] = $this->createOrganizationActor('certname.org@test.com');
        $org->organizationProfile->update(['company_name' => 'Fursa Academy']);
        [$volunteer, $volunteerToken] = $this->createVolunteerActor('certname.vol@test.com');

        $create = $this->api($organizationToken)->postJson('/api/learn-serve-opportunities/', [
            'title_en' => 'Leadership Course',
            'title_ar' => 'دورة القيادة',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'participants_needed' => 5,
        ]);
        $opportunityId = (int) $create->json('data.id');

        LearnServeOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunityId,
            'user_id' => $volunteer->id,
            'registration_date' => now(),
            'status' => ApprovalStatus::APPROVED,
            'is_certified' => true,
        ]);

        $tab = $this->getJson('/api/user-certificates/?user_id='.$volunteer->id);
        $this->assertSuccessEnvelope($tab);
        $tab->assertJsonPath('data.0.organizer_name', 'Fursa Academy');

        $detail = $this->api($volunteerToken)->getJson('/api/volunteer-detail/');
        $this->assertSuccessEnvelope($detail);
        $detail->assertJsonPath('data.opportunities.data.0.organizer_name', 'Fursa Academy');
    }

    public function test_volunteer_report_includes_civil_id(): void
    {
        [, $organizationToken] = $this->createOrganizationActor('civil.org@test.com');
        [$volunteer, $volunteerToken] = $this->createVolunteerActor('civil.vol@test.com');
        $volunteer->update(['civil_id' => '292929292929']);

        $create = $this->api($organizationToken)->postJson('/api/volunteer-opportunities/', $this->opportunityPayload());
        $opportunityId = (int) $create->json('data.id');
        VolunteerOpportunity::query()->whereKey($opportunityId)->update([
            'approval_status' => ApprovalStatus::APPROVED,
        ]);

        $this->api($volunteerToken)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => $opportunityId,
        ])->assertSuccessful();

        $list = $this->api($organizationToken)
            ->getJson('/api/volunteer-opportunity-registrations/?opportunity_id='.$opportunityId);
        $this->assertSuccessEnvelope($list);
        $this->assertSame('292929292929', $list->json('data.0.civil_id'));
    }

    public function test_learn_serve_close_registration_blocks_new_signups(): void
    {
        [, $organizationToken] = $this->createOrganizationActor('close.org@test.com');
        [, $volunteerToken] = $this->createVolunteerActor('close.vol@test.com');

        $create = $this->api($organizationToken)->postJson('/api/learn-serve-opportunities/', $this->opportunityPayload([
            'title_en' => 'Open Development',
            'due_date' => null,
        ]));
        $opportunityId = (int) $create->json('data.id');

        $closed = $this->api($organizationToken)
            ->postJson('/api/learn-serve-opportunities/'.$opportunityId.'/close-registration/');
        $this->assertSuccessEnvelope($closed);
        $closed->assertJsonPath('data.is_registration_closed', true)
            ->assertJsonPath('data.is_registration_open', false);

        $register = $this->api($volunteerToken)->postJson('/api/learn-serve-opportunity-registrations/', [
            'opportunity_id' => $opportunityId,
        ]);
        $this->assertErrorEnvelope($register, 400);
    }

    public function test_opportunity_change_diff_lists_updated_fields(): void
    {
        $changes = OpportunityChangeNotifier::diff(
            ['title_en' => 'Old', 'location_url' => null],
            ['title_en' => 'New', 'location_url' => 'https://maps.google.com/test']
        );

        $labels = collect($changes)->pluck('en')->all();
        $this->assertContains('English title', $labels);
        $this->assertContains('Location link', $labels);
    }

    public function test_community_org_type_exists_and_relief_label_updated(): void
    {
        // The client's later feedback renamed "Community" to "Society" as part
        // of the six-option org_type classification, so that is what the
        // signup flow must now offer.
        $society = MasterChoice::query()
            ->notDeleted()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->where('value_en', 'Society')
            ->first();
        $this->assertNotNull($society);

        $retiredCommunity = MasterChoice::query()
            ->notDeleted()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->where('value_en', 'Community')
            ->first();
        $this->assertNull($retiredCommunity);

        $relief = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'volunteer_opportunity_interest'))
            ->where('value_en', 'Relief')
            ->first();
        $this->assertSame('خارج الكويت', $relief?->value_ar);
    }

    public function test_sync_counts_class_without_attendance(): void
    {
        [$org] = $this->createOrganizationActor('class.org@test.com');
        $classType = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'learning_type'))
            ->where('value_en', 'Class')
            ->firstOrFail();

        LearnServeOpportunity::query()->create([
            'created_by' => $org->id,
            'title_en' => 'Class without attendance',
            'title_ar' => 'درس',
            'description_en' => 'd',
            'description_ar' => 'و',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
            'participants_needed' => 5,
            'opportunity_status' => OpportunityStatus::COMPLETED,
            'approval_status' => ApprovalStatus::APPROVED,
            'learning_type_id' => $classType->id,
        ]);

        $this->assertTrue(SyncService::syncOrganization($org->id));

        $this->assertDatabaseHas('organization_statistics', [
            'user_id' => $org->id,
            'learn_opportunity_organized' => 1,
        ]);
    }

    public function test_sync_excludes_paid_development_opportunities_from_sponsorship_count(): void
    {
        [$sponsor] = $this->createOrganizationActor('sponsor.counter@test.com');
        [$creator] = $this->createOrganizationActor('ls.creator@test.com');

        $paid = LearnServeOpportunity::query()->create([
            'created_by' => $creator->id,
            'title_en' => 'Paid Course',
            'title_ar' => 'دورة مدفوعة',
            'description_en' => 'd',
            'description_ar' => 'و',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
            'participants_needed' => 5,
            'opportunity_status' => OpportunityStatus::COMPLETED,
            'approval_status' => ApprovalStatus::APPROVED,
            'is_paid' => true,
        ]);

        $free = LearnServeOpportunity::query()->create([
            'created_by' => $creator->id,
            'title_en' => 'Free Course',
            'title_ar' => 'دورة مجانية',
            'description_en' => 'd',
            'description_ar' => 'و',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
            'participants_needed' => 5,
            'opportunity_status' => OpportunityStatus::COMPLETED,
            'approval_status' => ApprovalStatus::APPROVED,
            'is_paid' => false,
        ]);

        $paid->sponsorImages()->create(['organization_id' => $sponsor->organizationProfile->id]);
        $free->sponsorImages()->create(['organization_id' => $sponsor->organizationProfile->id]);

        $this->assertTrue(SyncService::syncOrganization($sponsor->id));

        $this->assertDatabaseHas('organization_statistics', [
            'user_id' => $sponsor->id,
            'year' => (int) $free->end_date->year,
            'month' => (int) $free->end_date->month,
            'sponsored' => 1,
        ]);
    }

    public function test_volunteer_opportunity_exposes_both_attendance_methods_and_location_url(): void
    {
        [, $organizationToken] = $this->createOrganizationActor('methods.org@test.com');
        $create = $this->api($organizationToken)->postJson('/api/volunteer-opportunities/', $this->opportunityPayload([
            'location_url' => 'https://maps.google.com/?q=kuwait',
        ]));
        $this->assertSuccessEnvelope($create, 201);
        $create->assertJsonPath('data.qr_attendance_enabled', true)
            ->assertJsonPath('data.manual_attendance_enabled', true)
            ->assertJsonPath('data.location_url', 'https://maps.google.com/?q=kuwait');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function opportunityPayload(array $overrides = []): array
    {
        return array_merge([
            'title_en' => 'Owner Report Opportunity',
            'title_ar' => 'فرصة تقرير',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'participants_needed' => 8,
            'from_age' => 16,
            'volunteer_category' => 'environmental',
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
