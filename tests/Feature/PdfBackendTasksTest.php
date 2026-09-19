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

    public function test_volunteer_registration_toggle_closes_a_day_before_end_date(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        $opportunity = VolunteerOpportunity::create([
            'created_by' => $owner->id,
            'title_en' => 'Still running', 'title_ar' => 'شغالة',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->subDay(), 'end_date' => now()->addDay(),
            'participants_needed' => 5,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::INPROGRESS,
        ]);

        $this->api($token)
            ->postJson("/api/volunteer-opportunities/{$opportunity->id}/close-registration/")
            ->assertOk();

        $opportunity->update(['end_date' => now()->subDay()]);

        $this->api($token)
            ->postJson("/api/volunteer-opportunities/{$opportunity->id}/reopen-registration/")
            ->assertStatus(422);
    }

    public function test_learn_serve_registration_can_be_closed_and_reopened_within_the_window(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        $opportunity = LearnServeOpportunity::create([
            'created_by' => $owner->id,
            'title_en' => 'Course', 'title_ar' => 'دورة',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->subDay(), 'end_date' => now()->addDay(),
            'participants_needed' => 5,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::INPROGRESS,
        ]);

        $this->api($token)
            ->postJson("/api/learn-serve-opportunities/{$opportunity->id}/close-registration/")
            ->assertOk();
        $this->assertTrue((bool) $opportunity->fresh()->is_registration_closed);

        $this->api($token)
            ->postJson("/api/learn-serve-opportunities/{$opportunity->id}/reopen-registration/")
            ->assertOk();
        $this->assertFalse((bool) $opportunity->fresh()->is_registration_closed);
    }

    public function test_learn_serve_registration_toggle_is_refused_after_the_window(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        $opportunity = LearnServeOpportunity::create([
            'created_by' => $owner->id,
            'title_en' => 'Ended course', 'title_ar' => 'دورة منتهية',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->subDays(4), 'end_date' => now()->subDays(2),
            'participants_needed' => 5,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
        ]);

        $this->api($token)
            ->postJson("/api/learn-serve-opportunities/{$opportunity->id}/close-registration/")
            ->assertStatus(422);

        $opportunity->update(['is_registration_closed' => true]);

        $this->api($token)
            ->postJson("/api/learn-serve-opportunities/{$opportunity->id}/reopen-registration/")
            ->assertStatus(422);
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

    public function test_learn_serve_opportunity_can_carry_an_ungated_whatsapp_link(): void
    {
        [, $token] = $this->createOrganizationActor();
        $payload = [
            'title_en' => 'Workshop', 'title_ar' => 'ورشة',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(4)->toDateString(),
            'participants_needed' => 5,
            'learning_type_id' => $this->choice('learning_type', 'Class/Workshop'),
            'format_id' => $this->choice('learn_serve_format'),
            'link' => 'https://zoom.us/j/123456',
            'whatsapp_link' => 'https://wa.me/96500000000',
        ];

        $id = $this->api($token)->postJson('/api/learn-serve-opportunities/', $payload)
            ->assertCreated()
            ->assertJsonPath('data.whatsapp_link', 'https://wa.me/96500000000')
            ->assertJsonPath('data.link', 'https://zoom.us/j/123456')
            ->json('data.id');

        $opportunity = LearnServeOpportunity::findOrFail($id);
        $this->assertSame('https://wa.me/96500000000', $opportunity->whatsapp_link);
        $opportunity->update(['approval_status' => ApprovalStatus::APPROVED]);

        // Ungated: a stranger who is not registered still sees it on the public listing.
        [, $strangerToken] = $this->createVolunteerActor();
        $this->api($strangerToken)->getJson('/api/list-all-opportunities/')
            ->assertJsonFragment(['whatsapp_link' => 'https://wa.me/96500000000']);
    }
}
