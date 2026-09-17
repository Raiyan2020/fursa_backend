<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\VolunteerCategory;
use App\Models\Event;
use App\Models\LearnServeOpportunity;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-60 — `location_url` used to fall back to the WhatsApp contact link
 * (`link`, or `registration_link` for events) whenever it was empty, so a
 * field named for the venue could return a phone number instead. An empty
 * location must now come back empty, on every surface that serves it.
 */
class LocationUrlNoLinkFallbackTest extends TestCase
{
    use CreatesDomainFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_volunteer_opportunity_resources_do_not_fall_back_to_the_whatsapp_link(): void
    {
        [$org] = $this->createOrganizationActor();
        $opportunity = VolunteerOpportunity::create([
            'created_by' => $org->id,
            'title_en' => 'No location', 'title_ar' => 'بدون موقع',
            'description_en' => 'Desc', 'description_ar' => 'وصف',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'approval_status' => ApprovalStatus::APPROVED,
            'participants_needed' => 10,
            'is_public' => true,
            'volunteer_category' => VolunteerCategory::ENVIRONMENTAL,
            'location_url' => null,
            'link' => 'https://wa.me/96500000000',
        ]);

        $detail = $this->getJson("/api/opportunities/{$opportunity->id}/details/")->assertOk();
        $this->assertNull($detail->json('data.location_url'));

        $card = collect($this->getJson('/api/list-volunteer-opportunities/')->assertOk()->json('data'))
            ->firstWhere('id', $opportunity->id);
        $this->assertNull($card['location_url']);
    }

    public function test_learn_serve_opportunity_resources_do_not_fall_back_to_the_whatsapp_link(): void
    {
        $opportunity = LearnServeOpportunity::create([
            'created_by' => $this->createOrganizationActor()[0]->id,
            'title_en' => 'No location', 'title_ar' => 'بدون موقع',
            'description_en' => 'Desc', 'description_ar' => 'وصف',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'approval_status' => ApprovalStatus::APPROVED,
            'participants_needed' => 10,
            'location_url' => null,
            'link' => 'https://wa.me/96500000000',
        ]);

        $detail = $this->getJson("/api/learn-serve-opportunities/{$opportunity->id}/")->assertOk();
        $this->assertNull($detail->json('data.location_url'));
    }

    public function test_event_resources_do_not_fall_back_to_the_registration_link(): void
    {
        [$org] = $this->createOrganizationActor();
        $event = Event::create([
            'created_by' => $org->organizationProfile->id,
            'title_en' => 'No location', 'title_ar' => 'بدون موقع',
            'description_en' => 'Desc', 'description_ar' => 'وصف',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'approval_status' => ApprovalStatus::APPROVED,
            'participants_needed' => 10,
            'location_url' => null,
            'registration_link' => 'https://wa.me/96500000000',
        ]);

        $detail = $this->getJson("/api/events/{$event->id}/")->assertOk();
        $this->assertNull($detail->json('data.location_url'));
    }
}
