<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\UserNotification;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * A volunteer registering for an opportunity must get a confirmation email.
 * The template existed but nothing ever sent it.
 *
 * These assertions read the array transport rather than Mail::fake(), because
 * DynamicEmailService sends raw HTML via Mail::html() instead of a Mailable,
 * and the fake does not record raw sends as mailables.
 */
class RegistrationConfirmationEmailTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->transport()->flush();
    }

    public function test_registering_sends_a_confirmation_email(): void
    {
        [$org] = $this->createOrganizationActor();
        [, $token] = $this->createVolunteerActor();

        $opportunity = $this->openOpportunity($org->id);

        $this->api($token)
            ->postJson('/api/volunteer-opportunity-registrations/', [
                'opportunity_id' => $opportunity->id,
            ])
            ->assertSuccessful();

        $this->assertCount(1, $this->transport()->messages());
    }

    public function test_confirmation_email_goes_to_the_volunteer_with_the_details(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();

        $opportunity = $this->openOpportunity($org->id);

        $this->api($token)
            ->postJson('/api/volunteer-opportunity-registrations/', [
                'opportunity_id' => $opportunity->id,
            ])
            ->assertSuccessful();

        $messages = $this->transport()->messages();
        $this->assertNotEmpty($messages);

        // ArrayTransport stores SentMessage objects; unwrap to the raw email.
        $message = $messages->first()->getOriginalMessage();
        $recipients = array_map(
            fn ($address) => $address->getAddress(),
            $message->getTo()
        );

        $this->assertContains($volunteer->email, $recipients);

        $body = (string) $message->getHtmlBody().(string) $message->getTextBody();
        $this->assertStringContainsString('Beach cleanup', $body);
        $this->assertStringContainsString($opportunity->start_date->toDateString(), $body);
    }

    public function test_registering_also_raises_an_in_app_notification(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();

        $opportunity = $this->openOpportunity($org->id);

        $this->api($token)
            ->postJson('/api/volunteer-opportunity-registrations/', [
                'opportunity_id' => $opportunity->id,
            ])
            ->assertSuccessful();

        $this->assertTrue(
            UserNotification::query()->where('user_id', $volunteer->id)->exists(),
            'The volunteer should have a registration notification.'
        );
    }

    public function test_registration_still_succeeds_when_mail_fails(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();

        $opportunity = $this->openOpportunity($org->id);

        // Force the mailer to blow up; the registration must still be stored.
        Mail::shouldReceive('html')->andThrow(new \RuntimeException('smtp down'));

        $this->api($token)
            ->postJson('/api/volunteer-opportunity-registrations/', [
                'opportunity_id' => $opportunity->id,
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('volunteer_opportunity_registrations', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
        ]);
    }

    /**
     * The array transport configured for the test suite.
     */
    protected function transport()
    {
        return Mail::mailer()->getSymfonyTransport();
    }

    protected function openOpportunity(int $ownerId): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create([
            'title_en' => 'Beach cleanup',
            'title_ar' => 'تنظيف الشاطئ',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'created_by' => $ownerId,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'is_public' => true,
            'participants_needed' => 20,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'location_en' => 'Kuwait City',
            'location_ar' => 'مدينة الكويت',
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
