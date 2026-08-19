<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Models\Event;
use App\Models\EventFeedback;
use App\Models\OrganizationProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsDjangoApiEnvelope;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class EventFeedbackTest extends TestCase
{
    use AssertsDjangoApiEnvelope;
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_volunteer_can_submit_event_feedback_using_frontend_payload(): void
    {
        [$orgUser] = $this->createOrganizationActor('event-feedback-org@test.com');
        [, $volunteerToken] = $this->createVolunteerActor('event-feedback-vol@test.com');

        $event = $this->createApprovedEvent($orgUser->organizationProfile);

        $response = $this->api($volunteerToken)->postJson('/api/event-feedback/', [
            'event' => $event->id,
            'rating' => 4,
            'comment' => 'ليست اول مره',
        ], ['Lang' => 'ar']);

        $this->assertSuccessEnvelope($response, 201, 'تم إنشاء الملاحظات بنجاح.');
        $response->assertJsonPath('data.event_id', $event->id)
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.comment_ar', 'ليست اول مره');

        $this->assertDatabaseHas('event_feedbacks', [
            'event_id' => $event->id,
            'rating' => 4,
            'comment_ar' => 'ليست اول مره',
            'is_deleted' => 0,
        ]);
    }

    public function test_submitting_feedback_again_updates_existing_entry(): void
    {
        [$orgUser] = $this->createOrganizationActor('event-feedback-update-org@test.com');
        [, $volunteerToken] = $this->createVolunteerActor('event-feedback-update-vol@test.com');

        $event = $this->createApprovedEvent($orgUser->organizationProfile);

        $this->api($volunteerToken)->postJson('/api/event-feedback/', [
            'event' => $event->id,
            'rating' => 3,
            'comment' => 'اول تعليق',
        ], ['Lang' => 'ar'])->assertCreated();

        $response = $this->api($volunteerToken)->postJson('/api/event-feedback/', [
            'event' => $event->id,
            'rating' => 5,
            'comment' => 'تعليق محدث',
        ], ['Lang' => 'ar']);

        $this->assertSuccessEnvelope($response, 200, 'تم تحديث الملاحظات بنجاح.');
        $this->assertSame(1, EventFeedback::query()->notDeleted()->where('event_id', $event->id)->count());
        $this->assertDatabaseHas('event_feedbacks', [
            'event_id' => $event->id,
            'rating' => 5,
            'comment_ar' => 'تعليق محدث',
        ]);
    }

    public function test_event_feedback_can_be_listed_by_event(): void
    {
        [$orgUser] = $this->createOrganizationActor('event-feedback-list-org@test.com');
        [, $volunteerToken] = $this->createVolunteerActor('event-feedback-list-vol@test.com');

        $event = $this->createApprovedEvent($orgUser->organizationProfile);

        $this->api($volunteerToken)->postJson('/api/event-feedback/', [
            'event' => $event->id,
            'rating' => 5,
            'comment' => 'رائع',
        ], ['Lang' => 'ar'])->assertCreated();

        $response = $this->getJson('/api/event-feedback/?event='.$event->id);

        $this->assertSuccessEnvelope($response);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($event->id, $response->json('data.0.event_id'));
    }

    protected function createApprovedEvent(OrganizationProfile $organization): Event
    {
        return Event::query()->create([
            'created_by' => $organization->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'event_status' => 'upcoming',
            'title_en' => 'Feedback Test Event',
            'title_ar' => 'فعالية اختبار التقييم',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'registration_required' => false,
            'participants_needed' => 10,
            'is_deleted' => false,
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
