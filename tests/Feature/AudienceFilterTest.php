<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\MasterChoice;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class AudienceFilterTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_gender_filter_includes_both_and_null(): void
    {
        [$orgUser] = $this->createOrganizationActor('filter-org@fursa.test');

        $male = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'opportunity_gender'))
            ->where('value_en', 'Male')
            ->firstOrFail();
        $female = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'opportunity_gender'))
            ->where('value_en', 'Female')
            ->firstOrFail();
        $both = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'opportunity_gender'))
            ->where('value_en', 'Both')
            ->firstOrFail();

        $maleOpp = $this->makeOpportunity($orgUser->id, 'Male Only', $male->id, 18, 30);
        $femaleOpp = $this->makeOpportunity($orgUser->id, 'Female Only', $female->id, 18, 30);
        $bothOpp = $this->makeOpportunity($orgUser->id, 'Both Genders', $both->id, 18, 30);
        $openOpp = $this->makeOpportunity($orgUser->id, 'Open Gender', null, 18, 30);

        $ids = collect($this->getJson('/api/list-volunteer-opportunities/?gender='.$male->id)
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($maleOpp->id));
        $this->assertTrue($ids->contains($bothOpp->id));
        $this->assertTrue($ids->contains($openOpp->id));
        $this->assertFalse($ids->contains($femaleOpp->id));
    }

    public function test_age_filter_uses_overlap_for_volunteer_age(): void
    {
        [$orgUser] = $this->createOrganizationActor('age-org@fursa.test');

        $forTeens = $this->makeOpportunity($orgUser->id, 'Teens', null, 13, 17);
        $forAdults = $this->makeOpportunity($orgUser->id, 'Adults', null, 18, 40);
        $openAge = $this->makeOpportunity($orgUser->id, 'Open Age', null, null, null);

        $ids = collect($this->getJson('/api/list-volunteer-opportunities/?age=25')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($forTeens->id));
        $this->assertTrue($ids->contains($forAdults->id));
        $this->assertTrue($ids->contains($openAge->id));
    }

    public function test_status_filter_uses_dates_not_stale_db_column(): void
    {
        [$orgUser] = $this->createOrganizationActor('status-org@fursa.test');

        $future = VolunteerOpportunity::query()->create([
            'created_by' => $orgUser->id,
            'title_en' => 'Future Opportunity',
            'title_ar' => 'فرصة مستقبلية',
            'description_en' => 'desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(12)->toDateString(),
            'participants_needed' => 10,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
            'is_public' => true,
        ]);

        $past = VolunteerOpportunity::query()->create([
            'created_by' => $orgUser->id,
            'title_en' => 'Past Opportunity',
            'title_ar' => 'فرصة منتهية',
            'description_en' => 'desc',
            'description_ar' => 'وصف',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
            'participants_needed' => 10,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'is_public' => true,
        ]);

        $upcomingIds = collect($this->getJson('/api/list-volunteer-opportunities/?status=upcoming')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($upcomingIds->contains($future->id));
        $this->assertFalse($upcomingIds->contains($past->id));

        $closedIds = collect($this->getJson('/api/list-volunteer-opportunities/?status=closed')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertFalse($closedIds->contains($future->id));
        $this->assertTrue($closedIds->contains($past->id));
    }

    public function test_status_filter_returns_empty_when_no_matches(): void
    {
        [$orgUser] = $this->createOrganizationActor('empty-status@fursa.test');

        VolunteerOpportunity::query()->create([
            'created_by' => $orgUser->id,
            'title_en' => 'Only Past',
            'title_ar' => 'منتهية فقط',
            'description_en' => 'desc',
            'description_ar' => 'وصف',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
            'participants_needed' => 10,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
            'is_public' => true,
        ]);

        $response = $this->getJson('/api/list-volunteer-opportunities/?status=upcoming');

        $response->assertOk()->assertJsonPath('key', 'success');
        $this->assertSame([], $response->json('data'));
    }

    protected function makeOpportunity(
        int $createdBy,
        string $title,
        ?int $genderId,
        ?int $fromAge,
        ?int $toAge
    ): VolunteerOpportunity {
        return VolunteerOpportunity::query()->create([
            'created_by' => $createdBy,
            'title_en' => $title,
            'title_ar' => $title,
            'description_en' => 'desc',
            'description_ar' => 'وصف',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
            'participants_needed' => 10,
            'gender_id' => $genderId,
            'from_age' => $fromAge,
            'to_age' => $toAge,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'is_public' => true,
        ]);
    }
}
