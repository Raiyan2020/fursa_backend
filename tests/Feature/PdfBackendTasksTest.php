<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\MasterChoice;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Services\Certificate\CertificateRenderer;
use App\Services\Mail\DynamicEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class PdfBackendTasksTest extends TestCase
{
    use CreatesDomainFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed();
    }

    private function api(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function choice(string $type, ?string $value = null): int
    {
        return MasterChoice::whereHas('choiceType', fn ($q) => $q->where('name', $type))
            ->when($value, fn ($q) => $q->where('value_en', $value))->firstOrFail()->id;
    }

    public function test_expired_volunteer_registration_cannot_be_reopened(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        $opportunity = VolunteerOpportunity::create([
            'created_by' => $owner->id,
            'title_en' => 'Expired', 'title_ar' => 'منتهية',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->subDays(4), 'end_date' => now()->subDays(2),
            'participants_needed' => 5, 'is_registration_closed' => true,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
        ]);

        $this->api($token)
            ->postJson("/api/volunteer-opportunities/{$opportunity->id}/reopen-registration/")
            ->assertStatus(422);

        $this->assertTrue((bool) $opportunity->fresh()->is_registration_closed);
    }

    public function test_expired_event_registration_cannot_be_reopened(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        $event = Event::create([
            'created_by' => $owner->organizationProfile->id,
            'title_en' => 'Expired event', 'title_ar' => 'فعالية منتهية',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->subDays(4), 'end_date' => now()->subDays(2),
            'participants_needed' => 5, 'is_registration_closed' => true,
            'approval_status' => ApprovalStatus::APPROVED,
            'event_status' => OpportunityStatus::COMPLETED,
        ]);

        $this->api($token)
            ->postJson("/api/events/{$event->id}/close-registration/", ['is_registration_closed' => false])
            ->assertStatus(422);
    }

    public function test_ended_event_can_still_be_republished(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        $event = Event::create([
            'created_by' => $owner->organizationProfile->id,
            'title_en' => 'Expired event', 'title_ar' => 'فعالية منتهية',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->subDays(4), 'end_date' => now()->subDays(2),
            'participants_needed' => 5, 'is_registration_closed' => true,
            'approval_status' => ApprovalStatus::APPROVED,
            'event_status' => OpportunityStatus::COMPLETED,
        ]);

        $payload = [
            'title_en' => 'Reposted event', 'title_ar' => 'فعالية معاد نشرها',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->addDays(6)->toDateString(), 'end_date' => now()->addDays(7)->toDateString(),
            'participants_needed' => 5,
            'event_type_id' => $this->choice('event_type'),
        ];

        $this->api($token)
            ->postJson("/api/event/republish/{$event->id}", $payload)
            ->assertCreated();
    }

    public function test_organizer_can_set_a_custom_learn_serve_certificate_name(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        [$user] = $this->createVolunteerActor();
        $opportunity = LearnServeOpportunity::create([
            'created_by' => $owner->id,
            'title_en' => 'Course', 'title_ar' => 'دورة',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->subDay(), 'end_date' => now(),
            'participants_needed' => 5,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
        ]);
        $registration = LearnServeOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
            'status' => ApprovalStatus::APPROVED,
            'is_certified' => true,
            'certificate_image' => 'certificates/old.html',
        ]);

        $response = $this->api($token)->patchJson(
            "/api/learn-serve-opportunities/{$opportunity->id}/registrations/{$registration->id}/certificate-name/",
            ['certificate_name' => 'Corrected Certificate Name']
        );

        $response->assertOk()->assertJsonPath('data.certificate_name', 'Corrected Certificate Name');
        $fresh = $registration->fresh();
        $this->assertSame('Corrected Certificate Name', $fresh->certificate_name);
        $this->assertFalse((bool) $fresh->is_certified);
        $this->assertNull($fresh->certificate_image);
    }

    public function test_certificate_renderer_prefers_custom_name(): void
    {
        [$owner] = $this->createOrganizationActor();
        [$user] = $this->createVolunteerActor();
        $opportunity = LearnServeOpportunity::create([
            'created_by' => $owner->id,
            'title_en' => 'Course', 'title_ar' => 'دورة',
            'start_date' => now()->subDay(), 'end_date' => now(),
        ]);
        $registration = LearnServeOpportunityRegistration::create([
            'opportunity_id' => $opportunity->id, 'user_id' => $user->id,
            'certificate_name' => 'Printed Name',
        ]);

        $this->assertSame('Printed Name', CertificateRenderer::data($registration)['name']);
    }

    public function test_reminder_preference_skips_reminder_but_not_otp_email(): void
    {
        /** @var User $user */
        [$user] = $this->createVolunteerActor();
        $user->update(['receive_reminder_emails' => false]);
        EmailTemplate::query()->updateOrCreate(
            ['name' => 'volunteer_three_day_reminder', 'language' => 'en'],
            ['subject' => 'Reminder', 'content' => 'Reminder']
        );
        EmailTemplate::query()->updateOrCreate(
            ['name' => 'account_activation_email', 'language' => 'en'],
            ['subject' => 'OTP', 'content' => '{{ otp }}']
        );

        $this->assertFalse(DynamicEmailService::send('volunteer_three_day_reminder', $user->fresh()));
        Mail::assertNothingSent();

        $this->assertTrue(DynamicEmailService::send('account_activation_email', $user->fresh(), ['otp' => '1234']));
    }
}
