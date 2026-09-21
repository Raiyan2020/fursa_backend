<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\Config;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityAttendance;
use App\Models\VolunteerOpportunityRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-75 — the departure scan: a grace period after the scheduled session
 * ends, hours capped to the overlap with that schedule, and a cross-midnight
 * lookup fix for sessions ending inside the grace window of midnight.
 */
class SelfCheckOutGraceWindowTest extends TestCase
{
    use CreatesDomainFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function api(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function opportunity($owner, array $extra = []): VolunteerOpportunity
    {
        return VolunteerOpportunity::create($extra + [
            'created_by' => $owner->id,
            'title_en' => 'Beach cleanup', 'title_ar' => 'تنظيف الشاطئ',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'start_time' => '17:00:00', 'end_time' => '21:00:00',
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'participants_needed' => 10,
            'is_public' => true,
        ]);
    }

    public function test_check_out_within_grace_period_is_accepted_and_capped_to_the_schedule(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        $codes = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->json('data');

        // Arrives early (16:00) and leaves within the 2h grace after 21:00 (22:30).
        $this->travelTo(now()->setTime(16, 0));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();

        $this->travelTo(now()->setTime(22, 30));
        $response = $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_out']['code'],
            'direction' => 'out',
        ])->assertOk();

        // Credited only for 17:00..21:00 (4h), not the raw 16:00..22:30 span.
        $response->assertJsonPath('data.total_hours', 4);
        $this->assertSame(4.0, (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours);
        $this->travelBack();
    }

    public function test_check_out_after_the_grace_period_is_refused(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        $codes = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->json('data');

        $this->travelTo(now()->setTime(17, 30));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();

        // 21:00 + 2h grace = 23:00 deadline; this is past it.
        $this->travelTo(now()->setTime(23, 1));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_out']['code'],
            'direction' => 'out',
        ])->assertStatus(400);

        $attendance = VolunteerOpportunityAttendance::query()->notDeleted()->firstOrFail();
        $this->assertNull($attendance->checked_out_at);
        $this->travelBack();
    }

    public function test_grace_period_is_configurable(): void
    {
        Config::query()->update(['self_check_out_grace_hours' => 1]);

        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        $codes = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->json('data');

        $this->travelTo(now()->setTime(17, 30));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();

        // 21:00 + 1h grace = 22:00 deadline with the configured value.
        $this->travelTo(now()->setTime(22, 1));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_out']['code'],
            'direction' => 'out',
        ])->assertStatus(400);
        $this->travelBack();
    }

    public function test_self_check_out_closes_at_is_exposed_while_pending(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        $codes = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->json('data');

        $this->travelTo(now()->setTime(17, 30));
        $response = $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();

        $expected = now()->setTime(21, 0)->addHours(2)->toIso8601String();
        $response->assertJsonPath('data.self_check_out_closes_at', $expected);
        $this->travelBack();
    }

    public function test_check_out_resolves_across_midnight_when_todays_date_lookup_finds_nothing(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        // Session ends at 23:30, so the 2h grace window (until 01:30) crosses midnight.
        $opportunity = $this->opportunity($owner, ['start_time' => '22:00:00', 'end_time' => '23:30:00']);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        $codes = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->json('data');

        $this->travelTo(now()->setTime(22, 0));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();

        // Past midnight now, but still inside the grace window.
        $this->travelTo(now()->addDay()->setTime(0, 30));
        $response = $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_out']['code'],
            'direction' => 'out',
        ])->assertOk();

        $response->assertJsonPath('data.total_hours', 1.5);
        $this->travelBack();
    }

    public function test_check_out_across_midnight_past_the_grace_period_is_refused(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner, ['start_time' => '22:00:00', 'end_time' => '23:00:00']);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        $codes = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->json('data');

        $this->travelTo(now()->setTime(22, 0));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();

        // 23:00 + 2h grace = 01:00 next day; this is past it.
        $this->travelTo(now()->addDay()->setTime(1, 30));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_out']['code'],
            'direction' => 'out',
        ])->assertStatus(400);

        $attendance = VolunteerOpportunityAttendance::query()->notDeleted()->firstOrFail();
        $this->assertNull($attendance->checked_out_at);
        $this->travelBack();
    }

    /**
     * Acceptance table row: in 4:30, out 9:00 ⇒ 4.0 — an early arrival is not
     * paid extra, the overlap floor is the session start, not the check-in.
     */
    public function test_early_arrival_is_not_paid(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        $codes = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->json('data');

        $this->travelTo(now()->setTime(16, 30));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();

        $this->travelTo(now()->setTime(21, 0));
        $response = $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_out']['code'],
            'direction' => 'out',
        ])->assertOk();

        $response->assertJsonPath('data.total_hours', 4);
        $this->travelBack();
    }

    /**
     * Acceptance table row: in 5:00, out 10:59 ⇒ accepted, total_hours 4.0,
     * checked_out_at still 10:59 — right at the edge of the grace window.
     */
    public function test_departure_at_the_edge_of_the_grace_period_is_accepted_and_capped(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        $codes = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->json('data');

        $this->travelTo(now()->setTime(17, 0));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();

        $this->travelTo(now()->setTime(22, 59));
        $response = $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_out']['code'],
            'direction' => 'out',
        ])->assertOk();

        $response->assertJsonPath('data.total_hours', 4);
        $this->assertSame(
            now()->toIso8601String(),
            VolunteerOpportunityAttendance::query()->notDeleted()->firstOrFail()->checked_out_at->toIso8601String()
        );
        $this->travelBack();
    }

    /**
     * The client's own worked example, verbatim: a 5→9 opportunity, in 5:30,
     * out 10:50 ⇒ 3.5 hours — and checked_out_at still records the real scan.
     */
    public function test_the_clients_worked_example_5_30_to_10_50_is_3_5_hours(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        $codes = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->json('data');

        $this->travelTo(now()->setTime(17, 30));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();

        $this->travelTo(now()->setTime(22, 50));
        $response = $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_out']['code'],
            'direction' => 'out',
        ])->assertOk();

        $response->assertJsonPath('data.total_hours', 3.5);
        $this->assertSame(
            now()->toIso8601String(),
            VolunteerOpportunityAttendance::query()->notDeleted()->firstOrFail()->checked_out_at->toIso8601String()
        );
        $this->travelBack();
    }

    public function test_organizer_manual_override_is_not_subject_to_the_self_scan_cap(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        // 5 -> 9 is a 4-hour opportunity; the organizer records 6h of overtime.
        $opportunity = $this->opportunity($owner);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        $codes = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->json('data');

        $this->travelTo(now()->setTime(17, 0));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();
        $this->travelTo(now()->addHours(1));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_out']['code'],
            'direction' => 'out',
        ])->assertOk();

        $attendance = VolunteerOpportunityAttendance::query()->notDeleted()->firstOrFail();

        $response = $this->api($ownerToken)->patchJson("/api/volunteer-attendance/{$attendance->id}/hours/", [
            'total_hours' => 6,
        ]);

        $response->assertOk()->assertJsonPath('data.total_hours', 6);
        $this->travelBack();
    }
}
