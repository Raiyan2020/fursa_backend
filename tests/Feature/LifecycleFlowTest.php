<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AssertsDjangoApiEnvelope;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class LifecycleFlowTest extends TestCase
{
    use AssertsDjangoApiEnvelope;
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        $this->seed();
    }

    public function test_complete_content_approval_registration_attendance_and_statistics_cycle(): void
    {
        [, $organizationToken] = $this->createOrganizationActor('cycle.org@test.com');
        [$volunteer, $volunteerToken] = $this->createVolunteerActor('cycle.volunteer@test.com');

        $payload = [
            'title_en' => 'Cycle Volunteer Opportunity',
            'title_ar' => 'فرصة دورة اختبار',
            'description_en' => 'Full cycle test',
            'description_ar' => 'اختبار الدورة الكاملة',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
            'participants_needed' => 10,
            'from_age' => 18,
            'is_public' => true,
        ];

        $volunteerCreate = $this->api($organizationToken)
            ->postJson('/api/volunteer-opportunities/', $payload);
        $this->assertSuccessEnvelope($volunteerCreate, 201);
        $volunteerOpportunityId = (int) $volunteerCreate->json('data.id');

        $learnCreate = $this->api($organizationToken)
            ->postJson('/api/learn-serve-opportunities/', array_merge($payload, [
                'title_en' => 'Cycle Learn & Serve',
                'title_ar' => 'دورة تعلم وخدمة',
            ]));
        $this->assertSuccessEnvelope($learnCreate, 201);
        $learnOpportunityId = (int) $learnCreate->json('data.id');

        $eventCreate = $this->api($organizationToken)->postJson('/api/events/', [
            'title_en' => 'Cycle Event',
            'title_ar' => 'فعالية دورة اختبار',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'due_date' => now()->addDays(4)->toDateTimeString(),
            'registration_required' => true,
            'participants_needed' => 10,
        ]);
        $this->assertSuccessEnvelope($eventCreate, 201);
        $eventId = (int) $eventCreate->json('data.id');

        $this->actingAs($this->adminActor(), 'admin');
        $this->post('/dashboard/volunteer-opportunities/'.$volunteerOpportunityId.'/approve')
            ->assertRedirect();
        $this->post('/dashboard/learn-serve-opportunities/'.$learnOpportunityId.'/approve')
            ->assertRedirect();
        $this->post('/dashboard/events/'.$eventId.'/approve')
            ->assertRedirect();

        $this->assertSame(ApprovalStatus::APPROVED, VolunteerOpportunity::findOrFail($volunteerOpportunityId)->approval_status);
        $this->assertSame(ApprovalStatus::APPROVED, LearnServeOpportunity::findOrFail($learnOpportunityId)->approval_status);
        $this->assertSame(ApprovalStatus::APPROVED, Event::findOrFail($eventId)->approval_status);

        $this->getJson('/api/list-volunteer-opportunities/')
            ->assertOk()
            ->assertJsonPath('key', 'success');
        $this->getJson('/api/learn-serve-opportunities/')
            ->assertOk()
            ->assertJsonPath('key', 'success');
        $this->getJson('/api/events/')
            ->assertOk()
            ->assertJsonPath('key', 'success');

        $volunteerRegistration = $this->api($volunteerToken)
            ->postJson('/api/volunteer-opportunity-registrations/', [
                'opportunity_id' => $volunteerOpportunityId,
            ]);
        $this->assertSuccessEnvelope($volunteerRegistration, 201);

        $learnRegistration = $this->api($volunteerToken)
            ->postJson('/api/learn-serve-opportunity-registrations/', [
                'opportunity_id' => $learnOpportunityId,
            ]);
        $this->assertSuccessEnvelope($learnRegistration, 201);

        $eventRegistration = $this->api($volunteerToken)
            ->postJson('/api/event-registrations/', ['event' => $eventId]);
        $this->assertSuccessEnvelope($eventRegistration, 201);

        $learnRegistrationId = LearnServeOpportunityRegistration::query()
            ->where('opportunity_id', $learnOpportunityId)
            ->where('user_id', $volunteer->id)
            ->value('id');

        $attendance = $this->api($organizationToken)
            ->patchJson('/api/learn-serve-opportunities/'.$learnOpportunityId.'/update-attendance/', [
                'is_attended' => true,
                'registration_ids' => [$learnRegistrationId],
            ]);
        $this->assertSuccessEnvelope($attendance);
        $this->assertTrue(LearnServeOpportunityRegistration::findOrFail($learnRegistrationId)->is_attended);

        $eventRegistrationId = EventRegistration::query()
            ->where('event_id', $eventId)
            ->where('user_id', $volunteer->id)
            ->value('id');

        $this->api($organizationToken)
            ->patchJson('/api/event-registrations/'.$eventRegistrationId.'/', ['is_attended' => true])
            ->assertOk()
            ->assertJsonPath('key', 'success');

        $this->api($volunteerToken)
            ->postJson('/api/sync-statistics/')
            ->assertOk()
            ->assertJsonPath('key', 'success');

        $this->assertDatabaseHas('volunteer_opportunity_registrations', [
            'opportunity_id' => $volunteerOpportunityId,
            'user_id' => $volunteer->id,
            'is_deleted' => 0,
        ]);
        $this->assertDatabaseHas('learn_serve_opportunity_registrations', [
            'opportunity_id' => $learnOpportunityId,
            'user_id' => $volunteer->id,
            'is_attended' => 1,
        ]);
        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $eventId,
            'user_id' => $volunteer->id,
            'is_attended' => 1,
        ]);
        $this->assertDatabaseHas('volunteer_statistics', [
            'user_id' => $volunteer->id,
            'year' => now()->year,
            'month' => now()->month,
        ]);

        $this->api($organizationToken)
            ->getJson('/api/event-registrations/by-event/'.$eventId.'/')
            ->assertOk()
            ->assertJsonPath('key', 'success');
        $this->api($volunteerToken)
            ->getJson('/api/event-registrations/'.$eventRegistrationId.'/')
            ->assertOk()
            ->assertJsonPath('key', 'success');
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
