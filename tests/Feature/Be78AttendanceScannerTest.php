<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-75 Part B — self_check_out_closes_at on the opportunity detail page
 * (before either attendance resource exists), and the same across-midnight
 * next_action fix as selfScan(), now in selfAttendanceState() too.
 *
 * BE-78 — the navbar attendance scanner: GET /my-attendance-scans/ (Part A)
 * and an optional `direction` on the self-scan endpoint (Part B).
 */
class Be78AttendanceScannerTest extends TestCase
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

    public function test_self_check_out_closes_at_is_exposed_on_the_opportunity_detail_before_any_scan_resource_exists(): void
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

        $expected = now()->setTime(21, 0)->addHours(2)->toIso8601String();
        $this->api($volunteerToken)->getJson("/api/opportunities/{$opportunity->id}/details/")
            ->assertOk()
            ->assertJsonPath('data.self_attendance.next_action', 'out')
            ->assertJsonPath('data.self_attendance.self_check_out_closes_at', $expected);

        $this->travelBack();
    }

    public function test_self_check_out_closes_at_is_null_before_check_in(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);

        $this->api($volunteerToken)->getJson("/api/opportunities/{$opportunity->id}/details/")
            ->assertOk()
            ->assertJsonPath('data.self_attendance.next_action', 'in')
            ->assertJsonPath('data.self_attendance.self_check_out_closes_at', null);
    }

    public function test_next_action_resolves_across_midnight_on_the_opportunity_detail_too(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        // Session ends at 23:00, so the 2h grace window (until 01:00) crosses midnight.
        $opportunity = $this->opportunity($owner, ['start_time' => '21:00:00', 'end_time' => '23:00:00']);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        $codes = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->json('data');

        $this->travelTo(now()->setTime(21, 0));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();

        // Past midnight, still inside the grace window. Before the fix this
        // fell back to `next_action = 'in'` and offered the arrival button.
        $this->travelTo(now()->addDay()->setTime(0, 30));
        $expected = now()->subDay()->setTime(23, 0)->addHours(2)->toIso8601String();
        $this->api($volunteerToken)->getJson("/api/opportunities/{$opportunity->id}/details/")
            ->assertOk()
            ->assertJsonPath('data.self_attendance.next_action', 'out')
            ->assertJsonPath('data.self_attendance.self_check_out_closes_at', $expected);

        $this->travelBack();
    }

    public function test_my_attendance_scans_is_empty_outside_the_scan_window(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);

        // Session is 17:00-21:00; scan window opens 16:00 and closes 23:00 (2h grace).
        $this->travelTo(now()->setTime(10, 0));
        $this->api($volunteerToken)->getJson('/api/my-attendance-scans/')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->travelBack();
    }

    public function test_my_attendance_scans_lists_the_live_session_with_next_action(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);

        // Inside the 1h pre-start window (session starts 17:00).
        $this->travelTo(now()->setTime(16, 30));
        $response = $this->api($volunteerToken)->getJson('/api/my-attendance-scans/')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.opportunity_id', $opportunity->id)
            ->assertJsonPath('data.0.opportunity_type', 'volunteer_opportunity')
            ->assertJsonPath('data.0.next_action', 'in')
            ->assertJsonPath('data.0.scan_opens_at', now()->setTime(16, 0)->toIso8601String())
            ->assertJsonPath('data.0.scan_closes_at', now()->setTime(21, 0)->addHours(2)->toIso8601String());

        $codes = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->json('data');
        $this->travelTo(now()->setTime(17, 0));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();

        $this->api($volunteerToken)->getJson('/api/my-attendance-scans/')
            ->assertOk()
            ->assertJsonPath('data.0.next_action', 'out');

        $this->travelBack();
    }

    public function test_self_scan_without_direction_infers_it_from_the_code(): void
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
        ])->assertOk()->assertJsonPath('data.checked_out_at', null);

        $this->travelTo(now()->addHours(2));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_out']['code'],
        ])->assertOk()->assertJsonPath('data.total_hours', 2);

        $this->travelBack();
    }

    public function test_self_scan_without_direction_rejects_an_unrecognized_code(): void
    {
        [, $volunteerToken] = $this->createVolunteerActor();

        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => 'not-a-real-code',
        ])->assertStatus(400);
    }
}
