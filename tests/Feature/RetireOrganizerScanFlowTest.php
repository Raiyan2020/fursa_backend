<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\ScanPermission;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityAttendance;
use App\Models\VolunteerOpportunityRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-61 Part C — the organizer-scans-volunteer flow, gated behind
 * `fursa.organizer_scan_flow_enabled` rather than deleted outright.
 *
 * Defaults to "on" so nothing changes until a release date is set with the
 * frontend; these tests prove the "off" behaviour is correct and ready.
 */
class RetireOrganizerScanFlowTest extends TestCase
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

    private function opportunity($owner): VolunteerOpportunity
    {
        return VolunteerOpportunity::create([
            'created_by' => $owner->id,
            'title_en' => 'Beach cleanup', 'title_ar' => 'تنظيف الشاطئ',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'participants_needed' => 10,
            'is_public' => true,
        ]);
    }

    public function test_flag_defaults_on_so_nothing_changes_yet(): void
    {
        $this->assertTrue(config('fursa.organizer_scan_flow_enabled'));
    }

    public function test_disabling_the_flag_retires_scan_and_scan_permission_endpoints(): void
    {
        config(['fursa.organizer_scan_flow_enabled' => false]);

        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);

        $this->api($ownerToken)->postJson('/api/volunteer-attendance/scan/', [
            'opportunity_id' => $opportunity->id,
            'volunteer_uuid' => (string) $volunteer->volunteerProfile->uuid,
        ])->assertStatus(410);

        $this->api($ownerToken)->postJson('/api/scan-permissions/bulk-update/', [
            'opportunity_id' => $opportunity->id,
            'user_ids' => [$volunteer->id],
        ])->assertStatus(410);

        $this->api($ownerToken)->getJson("/api/scan-permissions/list/?opportunity_id={$opportunity->id}")
            ->assertStatus(410);

        $this->assertSame(0, VolunteerOpportunityAttendance::query()->count());
    }

    public function test_disabling_the_flag_drops_delegated_scan_permission_but_keeps_manual_attendance_for_the_owner(): void
    {
        config(['fursa.organizer_scan_flow_enabled' => false]);

        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$delegate, $delegateToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);

        // A pre-existing delegate grant no longer works once the flow is off.
        ScanPermission::create([
            'user_id' => $delegate->id,
            'opportunity_id' => $opportunity->id,
            'is_allowed' => true,
        ]);
        $this->api($delegateToken)->postJson('/api/volunteer-attendance/manual/', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
        ])->assertStatus(403);

        // The owner is unaffected — manual attendance stays available.
        $this->api($ownerToken)->postJson('/api/volunteer-attendance/manual/', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
        ])->assertOk();
    }

    public function test_self_scan_is_unaffected_by_the_flag(): void
    {
        config(['fursa.organizer_scan_flow_enabled' => false]);

        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);

        $codes = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->assertOk()->json('data');

        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();
    }
}
