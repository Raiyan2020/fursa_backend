<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityAttendance;
use App\Models\VolunteerOpportunityRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-61 Part A — self check-in QR for volunteering: two printed codes,
 * the volunteer scans themselves, and total_hours is the real elapsed time.
 */
class SelfCheckInQrTest extends TestCase
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
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'participants_needed' => 10,
            'is_public' => true,
        ]);
    }

    public function test_organizer_fetches_stable_codes_and_only_the_owner_may(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [, $strangerToken] = $this->createOrganizationActor();
        $opportunity = $this->opportunity($owner);

        $this->api($strangerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->assertNotFound();

        $first = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($first['check_in']['code']);
        $this->assertNotEmpty($first['check_out']['code']);
        $this->assertNotSame($first['check_in']['code'], $first['check_out']['code']);

        // Stable across calls, so a printed sheet never stops working.
        $second = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->assertOk()
            ->json('data');
        $this->assertSame($first, $second);
    }

    public function test_full_in_then_out_cycle_records_real_elapsed_hours(): void
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

        $this->travelTo(now()->setTime(9, 0));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();

        $this->api($volunteerToken)->getJson("/api/opportunities/{$opportunity->id}/details/")
            ->assertOk()
            ->assertJsonPath('data.self_attendance.next_action', 'out');

        $this->travelTo(now()->addHours(3));
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_out']['code'],
            'direction' => 'out',
        ])->assertOk()->assertJsonPath('data.total_hours', 3);

        $this->api($volunteerToken)->getJson("/api/opportunities/{$opportunity->id}/details/")
            ->assertOk()
            ->assertJsonPath('data.self_attendance.next_action', 'done');

        $this->assertSame(3.0, (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours);
        $this->travelBack();
    }

    public function test_out_without_a_prior_in_is_rejected_and_missing_out_credits_zero(): void
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

        // OUT with no IN yet.
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_out']['code'],
            'direction' => 'out',
        ])->assertStatus(400);

        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertOk();

        // A missing OUT credits zero hours, not the scheduled duration.
        $attendance = VolunteerOpportunityAttendance::query()->notDeleted()->firstOrFail();
        $this->assertSame(0.0, (float) $attendance->total_hours);
        $this->assertTrue($attendance->is_attended);

        // Neither direction can be recorded twice the same day.
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertStatus(409);
    }

    public function test_unregistered_volunteer_and_wrong_direction_code_are_rejected(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        $other = $this->opportunity($owner);

        $codes = $this->api($ownerToken)->getJson("/api/volunteer-opportunities/{$opportunity->id}/attendance-qr/")
            ->json('data');

        // Not registered for this opportunity at all.
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertStatus(400);

        VolunteerOpportunityRegistration::create([
            'opportunity_id' => $other->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);

        // Registered for $other, not for the opportunity the code belongs to.
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'in',
        ])->assertStatus(400);

        // The IN code submitted with direction "out" does not resolve to any
        // opportunity's OUT column, so it is rejected as an invalid code.
        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => $codes['check_in']['code'],
            'direction' => 'out',
        ])->assertStatus(400);

        $this->api($volunteerToken)->postJson('/api/volunteer-attendance/self-scan/', [
            'code' => 'not-a-real-code',
            'direction' => 'in',
        ])->assertStatus(400);
    }
}
