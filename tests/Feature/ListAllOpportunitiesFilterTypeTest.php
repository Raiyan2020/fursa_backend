<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MasterChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-18 — `filter_type=myevents` fell through to the whole catalogue.
 */
class ListAllOpportunitiesFilterTypeTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function makeEvent(int $orgProfileId, string $title): Event
    {
        return Event::query()->create([
            'title_en' => $title,
            'title_ar' => $title,
            'event_type_id' => MasterChoice::query()
                ->whereHas('choiceType', fn ($q) => $q->where('name', 'event_type'))
                ->value('id'),
            'start_date' => now()->addDays(3),
            'end_date' => now()->addDays(4),
            'created_by' => $orgProfileId,
            'approval_status' => ApprovalStatus::APPROVED,
            'event_status' => OpportunityStatus::UPCOMING,
        ]);
    }

    public function test_myevents_returns_only_the_events_this_user_registered_for(): void
    {
        [$volunteer, $token] = $this->createVolunteerActor();
        [$org] = $this->createOrganizationActor();
        $orgProfileId = $org->organizationProfile->id;

        $mine = $this->makeEvent($orgProfileId, 'Mine');
        $someoneElses = $this->makeEvent($orgProfileId, 'Not mine');

        EventRegistration::query()->create([
            'event_id' => $mine->id,
            'user_id' => $volunteer->id,
        ]);

        $ids = array_column(
            $this->withToken($token)
                ->getJson('/api/list-all-opportunities/?filter_type=myevents')
                ->assertStatus(200)
                ->json('data') ?? [],
            'id'
        );

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($someoneElses->id, $ids, 'must not leak an event the user never registered for');
    }

    public function test_an_unknown_filter_type_is_rejected_instead_of_returning_everything(): void
    {
        [, $token] = $this->createVolunteerActor();
        [$org] = $this->createOrganizationActor();
        $this->makeEvent($org->organizationProfile->id, 'Public catalogue event');

        $response = $this->withToken($token)
            ->getJson('/api/list-all-opportunities/?filter_type=totally_made_up');

        $response->assertStatus(422);
        $this->assertArrayHasKey('filter_type', $response->json('response_status.validation_errors'));
    }

    public function test_the_documented_values_still_work(): void
    {
        [, $token] = $this->createVolunteerActor();

        foreach (['organized', 'organized_events', 'events', 'sponsored', 'sponsored_events', 'volunteer', 'attendee', 'myevents'] as $value) {
            $this->withToken($token)
                ->getJson("/api/list-all-opportunities/?filter_type={$value}")
                ->assertStatus(200, "filter_type={$value} must stay accepted");
        }
    }

    public function test_omitting_filter_type_still_returns_the_catalogue(): void
    {
        [, $token] = $this->createVolunteerActor();

        $this->withToken($token)
            ->getJson('/api/list-all-opportunities/')
            ->assertStatus(200);
    }
}
