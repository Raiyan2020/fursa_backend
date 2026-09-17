<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\VolunteerCategory;
use App\Models\VolunteerOpportunity;
use App\Support\Opportunity\VolunteerOpportunitySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-58 — every listing endpoint (unlike the detail page) served only
 * start_date/end_date, so a 4-day non-consecutive opportunity read as an
 * unbroken multi-week range on every card.
 */
class WebsiteVolunteerOpportunityScheduleTest extends TestCase
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

    private function opportunityWithScatteredDays($owner): VolunteerOpportunity
    {
        $opportunity = VolunteerOpportunity::create([
            'created_by' => $owner->id,
            'title_en' => 'Scattered clinic', 'title_ar' => 'عيادة متفرقة',
            'description_en' => 'Desc', 'description_ar' => 'وصف',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'approval_status' => ApprovalStatus::APPROVED,
            'participants_needed' => 10,
            'is_public' => true,
            'volunteer_category' => VolunteerCategory::ENVIRONMENTAL,
        ]);

        VolunteerOpportunitySchedule::sync($opportunity, [
            ['date' => now()->addDays(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '12:00'],
            ['date' => now()->addDays(9)->toDateString(), 'start_time' => '09:00', 'end_time' => '12:00'],
            ['date' => now()->addDays(20)->toDateString(), 'start_time' => '09:00', 'end_time' => '12:00'],
        ]);

        return $opportunity->fresh();
    }

    public function test_list_volunteer_opportunities_reports_the_real_scattered_days(): void
    {
        [$org] = $this->createOrganizationActor();
        $opportunity = $this->opportunityWithScatteredDays($org);

        $card = collect($this->getJson('/api/list-volunteer-opportunities/')->assertOk()->json('data'))
            ->firstWhere('id', $opportunity->id);

        $this->assertNotNull($card);
        $this->assertTrue($card['has_custom_schedule']);
        $this->assertCount(3, $card['time_slots']);
    }

    public function test_list_all_opportunities_reports_the_real_scattered_days(): void
    {
        [$org] = $this->createOrganizationActor();
        [$viewer] = $this->createVolunteerActor();
        $opportunity = $this->opportunityWithScatteredDays($org);

        $card = collect($this->getJson("/api/list-all-opportunities/?user_id={$viewer->id}")->assertOk()->json('data'))
            ->firstWhere(fn ($item) => ($item['id'] ?? null) === $opportunity->id && $item['opportunity_type'] === 'volunteer_opportunity');

        $this->assertNotNull($card);
        $this->assertTrue($card['has_custom_schedule']);
        $this->assertCount(3, $card['time_slots']);
    }

    public function test_list_user_opportunities_reports_the_real_scattered_days(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        $opportunity = $this->opportunityWithScatteredDays($org);

        $card = collect($this->api($token)->getJson('/api/list-user-opportunities/?filter_type=organized')->assertOk()->json('data'))
            ->firstWhere('id', $opportunity->id);

        // "organized" here means "attended"; use the owner's own listing
        // (no filter) instead so the just-created opportunity is present
        // regardless of attendance state.
        if ($card === null) {
            $card = collect($this->api($token)->getJson('/api/list-user-opportunities/')->assertOk()->json('data'))
                ->firstWhere('id', $opportunity->id);
        }

        $this->assertNotNull($card);
        $this->assertTrue($card['has_custom_schedule']);
        $this->assertCount(3, $card['time_slots']);
    }

    public function test_a_plain_range_opportunity_reports_no_custom_schedule(): void
    {
        [$org] = $this->createOrganizationActor();
        $opportunity = VolunteerOpportunity::create([
            'created_by' => $org->id,
            'title_en' => 'Plain range', 'title_ar' => 'مدى عادي',
            'description_en' => 'Desc', 'description_ar' => 'وصف',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'approval_status' => ApprovalStatus::APPROVED,
            'participants_needed' => 10,
            'is_public' => true,
            'volunteer_category' => VolunteerCategory::ENVIRONMENTAL,
        ]);

        $card = collect($this->getJson('/api/list-volunteer-opportunities/')->assertOk()->json('data'))
            ->firstWhere('id', $opportunity->id);

        $this->assertNotNull($card);
        $this->assertFalse($card['has_custom_schedule']);
        $this->assertSame([], $card['time_slots']);
    }
}
