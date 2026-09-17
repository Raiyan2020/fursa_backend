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
 * regardless of the opportunity's actual scheduled hours for that day. Both
 * the manual check-in endpoint and the hours-correction endpoint must cap at
 * the opportunity's real per-day duration instead.
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

    public function test_update_hours_rejects_a_correction_above_the_opportunitys_scheduled_hours(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
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

        $this->api($ownerToken)->patchJson("/api/volunteer-attendance/{$attendance->id}/hours/", [
            'total_hours' => 15,
        ])->assertStatus(422);

        $this->assertSame(4.0, (float) $attendance->fresh()->total_hours);

        $this->api($ownerToken)->patchJson("/api/volunteer-attendance/{$attendance->id}/hours/", [
            'total_hours' => 3.5,
        ])->assertOk();

        $this->assertSame(3.5, (float) $attendance->fresh()->total_hours);
    }
}
