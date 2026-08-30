<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\VolunteerCategory;
use App\Models\Config;
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
