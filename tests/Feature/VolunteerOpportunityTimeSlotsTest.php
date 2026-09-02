<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\VolunteerCategory;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * Per-day scheduling for volunteer opportunities: non-consecutive days, and
 * hours that differ from one day to the next.
 *
 * Previously an opportunity had one start_time/end_time applied to every day
 * between start_date and end_date, so neither was expressible.
 */
class VolunteerOpportunityTimeSlotsTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed();
    }

    public function test_opportunity_can_be_created_with_non_consecutive_days(): void
    {
        [, $token] = $this->createOrganizationActor();

        $response = $this->api($token)->postJson('/api/volunteer-opportunities/', [
            'title_en' => 'Split schedule',
            'title_ar' => 'جدول متفرق',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'participants_needed' => 10,
            'volunteer_category' => VolunteerCategory::ENVIRONMENTAL->value,
            'time_slots' => [
                ['date' => now()->addDays(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '12:00'],
                // Deliberate gap, then a longer day.
                ['date' => now()->addDays(8)->toDateString(), 'start_time' => '14:00', 'end_time' => '20:00'],
            ],
        ]);

        $response->assertSuccessful();
        $this->assertTrue($response->json('data.has_custom_schedule'));
        $this->assertCount(2, $response->json('data.time_slots'));
    }

    public function test_each_day_reports_its_own_hours(): void
    {
        [, $token] = $this->createOrganizationActor();

        $response = $this->api($token)->postJson('/api/volunteer-opportunities/', [
            'title_en' => 'Varying hours',
            'title_ar' => 'ساعات مختلفة',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'participants_needed' => 10,
            'volunteer_category' => VolunteerCategory::ENVIRONMENTAL->value,
            'time_slots' => [
                ['date' => now()->addDays(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '12:00'],
                ['date' => now()->addDays(3)->toDateString(), 'start_time' => '14:00', 'end_time' => '20:00'],
            ],
        ]);

        $response->assertSuccessful();

        $hours = array_column($response->json('data.time_slots'), 'hours');
        $this->assertSame([3.0, 6.0], array_map('floatval', $hours));
    }

    public function test_attendance_credits_the_hours_of_that_specific_day(): void
    {
        [$org, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $longDay = now()->subDay()->toDateString();

        $opportunity = $this->opportunity($org->id, [
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            // The opportunity-wide range is 2 hours; the day's slot is 6.
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);

        $opportunity->timeSlots()->create([
            'date' => $longDay,
            'start_time' => '14:00:00',
            'end_time' => '20:00:00',
        ]);

        $this->register($opportunity, $volunteer);

        $this->api($orgToken)->postJson('/api/volunteer-attendance/manual/', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'attendance_date' => $longDay,
        ])->assertSuccessful();

        // The slot wins over the opportunity-wide times.
        $this->assertSame(
            6.0,
            (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours
        );
    }

    public function test_check_in_is_rejected_on_a_gap_day(): void
    {
        [$org, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $scheduled = now()->subDays(2)->toDateString();
        $gapDay = now()->subDay()->toDateString();

        $opportunity = $this->opportunity($org->id, [
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);

        $opportunity->timeSlots()->create([
            'date' => $scheduled,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        $this->register($opportunity, $volunteer);

        // The gap day sits inside start..end but is not a scheduled day.
        $this->api($orgToken)->postJson('/api/volunteer-attendance/manual/', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'attendance_date' => $gapDay,
        ])->assertStatus(400);

        $this->assertSame(0.0, (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours);
    }

    public function test_check_in_still_works_on_a_scheduled_day(): void
    {
        [$org, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $scheduled = now()->subDay()->toDateString();

        $opportunity = $this->opportunity($org->id, [
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);

        $opportunity->timeSlots()->create([
            'date' => $scheduled,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        $this->register($opportunity, $volunteer);

        $this->api($orgToken)->postJson('/api/volunteer-attendance/manual/', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'attendance_date' => $scheduled,
        ])->assertSuccessful();

        $this->assertSame(3.0, (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours);
    }

    public function test_opportunities_without_slots_keep_the_old_behaviour(): void
    {
        [$org, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id, [
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);

        $this->register($opportunity, $volunteer);

        $this->assertFalse($opportunity->hasCustomSchedule());

        // Any day in the range is still valid, using the opportunity-wide times.
        $this->api($orgToken)->postJson('/api/volunteer-attendance/manual/', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'attendance_date' => now()->subDay()->toDateString(),
        ])->assertSuccessful();

        $this->assertSame(4.0, (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours);
    }

    public function test_updating_the_schedule_replaces_the_days(): void
    {
        [$org, $token] = $this->createOrganizationActor();

        $opportunity = $this->opportunity($org->id, [
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
        ]);

        $opportunity->timeSlots()->create([
            'date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        $newDate = now()->addDays(9)->toDateString();

        $this->api($token)->postJson("/api/volunteer-opportunities/{$opportunity->id}/", [
            'time_slots' => [
                ['date' => $newDate, 'start_time' => '10:00', 'end_time' => '15:00'],
            ],
        ])->assertSuccessful();

        $active = $opportunity->timeSlots()->notDeleted()->get();

        $this->assertCount(1, $active);
        $this->assertSame($newDate, $active->first()->date->toDateString());
    }

    public function test_a_slot_crossing_midnight_counts_correctly(): void
    {
        [$org] = $this->createOrganizationActor();

        $opportunity = $this->opportunity($org->id, [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $slot = $opportunity->timeSlots()->create([
            'date' => now()->toDateString(),
            'start_time' => '22:00:00',
            'end_time' => '02:00:00',
        ]);

        $this->assertSame(4.0, $slot->durationInHours());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function opportunity(int $ownerId, array $attributes = []): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create(array_merge([
            'title_en' => 'Scheduled work',
            'title_ar' => 'عمل مجدول',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'created_by' => $ownerId,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::INPROGRESS,
            'is_public' => true,
            'participants_needed' => 10,
        ], $attributes));
    }

    protected function register(
        VolunteerOpportunity $opportunity,
        User $volunteer
    ): VolunteerOpportunityRegistration {
        return VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
        ]);
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
