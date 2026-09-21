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
 * PDF review bug: manual attendance hours accepted anything up to a flat 24,
 * regardless of the opportunity's actual scheduled hours for that day. The
 * manual check-in endpoint caps at the opportunity's real per-day duration.
 *
 * The hours-correction endpoint (`PATCH …/hours/`) no longer caps: BE-75
 * part C explicitly requires the organizer's manual override to stay above
 * the self-scan cap ("organizer PATCH …/hours/ with 6 | 6 is kept — the cap
 * is on self-scan only"), since it's the only way to record a genuinely
 * extended shift.
 */
class AttendanceHoursCapTest extends TestCase
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
            'start_time' => '08:00:00', 'end_time' => '12:00:00',
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'participants_needed' => 10,
            'is_public' => true,
        ]);
    }

    public function test_manual_check_in_rejects_hours_above_the_opportunitys_scheduled_hours(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($owner);
        $registration = VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);

        $this->api($ownerToken)->postJson('/api/volunteer-attendance/manual/', [
            'opportunity_id' => $opportunity->id,
            'registration_id' => $registration->id,
            'attendance_date' => now()->toDateString(),
            'total_hours' => 20,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('volunteer_opportunity_attendances', [
            'registration_id' => $registration->id,
        ]);

        $this->api($ownerToken)->postJson('/api/volunteer-attendance/manual/', [
            'opportunity_id' => $opportunity->id,
            'registration_id' => $registration->id,
            'attendance_date' => now()->toDateString(),
            'total_hours' => 4,
        ])->assertOk();
    }

    public function test_update_hours_allows_a_correction_above_the_opportunitys_scheduled_hours(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        // 08:00-12:00 is a 4-hour opportunity; the organizer records 6h of legitimate overtime.
        $opportunity = $this->opportunity($owner);
        $registration = VolunteerOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        $attendance = VolunteerOpportunityAttendance::create([
            'registration_id' => $registration->id,
            'attended_date' => now()->toDateString(),
            'total_hours' => 4,
            'is_attended' => true,
            'recorded_via' => 'manual',
        ]);

        $response = $this->api($ownerToken)->patchJson("/api/volunteer-attendance/{$attendance->id}/hours/", [
            'total_hours' => 6,
        ]);

        $response->assertOk()->assertJsonPath('data.total_hours', 6);
        $this->assertSame(6.0, (float) $attendance->fresh()->total_hours);

        // A normal in-range correction still works as before.
        $this->api($ownerToken)->patchJson("/api/volunteer-attendance/{$attendance->id}/hours/", [
            'total_hours' => 3.5,
        ])->assertOk();

        $this->assertSame(3.5, (float) $attendance->fresh()->total_hours);
    }
}
