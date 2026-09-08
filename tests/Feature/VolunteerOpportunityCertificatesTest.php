<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityAttendance;
use App\Models\VolunteerOpportunityRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AssertsDjangoApiEnvelope;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class VolunteerOpportunityCertificatesTest extends TestCase
{
    use AssertsDjangoApiEnvelope;
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed();
    }

    protected function makeCompletedOpportunity($org): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create([
            'title_en' => 'Reeest', 'title_ar' => 'Reeest',
            'description_en' => 'Desc', 'description_ar' => 'وصف',
            'created_by' => $org->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::INPROGRESS,
            'is_public' => true,
            'participants_needed' => 10,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDays(1)->toDateString(),
        ]);
    }

    public function test_completing_an_opportunity_automatically_issues_certificates_for_already_attended_registrations(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = $this->makeCompletedOpportunity($org);
        $registration = VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
        ]);
        VolunteerOpportunityAttendance::query()->create([
            'registration_id' => $registration->id,
            'attended_date' => now()->subDays(2)->toDateString(),
            'total_hours' => 4,
            'is_attended' => true,
        ]);

        $this->artisan('fursa:advance-statuses')->assertSuccessful();

        $registration->refresh();
        $this->assertTrue($registration->is_certified);
        $this->assertNotNull($registration->certificate_image);
        $this->assertSame(OpportunityStatus::COMPLETED, $opportunity->fresh()->opportunity_status);

        $certificates = $this->getJson('/api/user-certificates/?user_id='.$volunteer->id);
        $this->assertSuccessEnvelope($certificates);
        $entry = collect($certificates->json('data'))->firstWhere('registration_id', $registration->id);
        $this->assertNotNull($entry);
        $this->assertSame('Reeest', $entry['opportunity__title_en']);
        $this->assertNotEmpty($entry['certificate_image']);

        $download = $this->getJson('/api/download-certificate/?registration_id='.$registration->id.'&registration_type=volunteer');
        $download->assertOk();

        $this->assertSame(1, $volunteer->volunteerProfile->fresh()->total_certificates);
    }

    public function test_organizer_can_manually_send_certificates_for_attendance_marked_after_completion(): void
    {
        [$org, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = $this->makeCompletedOpportunity($org);
        $registration = VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
        ]);

        // Opportunity completes with nobody attended yet.
        $this->artisan('fursa:advance-statuses')->assertSuccessful();
        $this->assertFalse($registration->fresh()->is_certified);

        // Attendance recorded only after the automatic pass already ran.
        VolunteerOpportunityAttendance::query()->create([
            'registration_id' => $registration->id,
            'attended_date' => now()->subDays(1)->toDateString(),
            'total_hours' => 6,
            'is_attended' => true,
        ]);
        $this->assertFalse($registration->fresh()->is_certified);

        $send = $this->api($orgToken)->postJson("/api/volunteer-opportunities/{$opportunity->id}/certificates/send/");
        $this->assertSuccessEnvelope($send);
        $this->assertSame(1, $send->json('data.certificates_sent'));

        $registration->refresh();
        $this->assertTrue($registration->is_certified);
        $this->assertNotNull($registration->certificate_image);

        // Idempotent: nothing new to send the second time.
        $again = $this->api($orgToken)->postJson("/api/volunteer-opportunities/{$opportunity->id}/certificates/send/");
        $this->assertSuccessEnvelope($again);
        $this->assertSame(0, $again->json('data.certificates_sent'));
    }

    public function test_manual_send_is_rejected_before_the_opportunity_completes(): void
    {
        [$org, $orgToken] = $this->createOrganizationActor();

        $opportunity = VolunteerOpportunity::query()->create([
            'title_en' => 'Still running', 'title_ar' => 'لا تزال جارية',
            'description_en' => 'Desc', 'description_ar' => 'وصف',
            'created_by' => $org->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::INPROGRESS,
            'is_public' => true,
            'participants_needed' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->api($orgToken)->postJson("/api/volunteer-opportunities/{$opportunity->id}/certificates/send/")
            ->assertStatus(400);
    }

    public function test_backfill_command_issues_certificates_for_pre_existing_attended_registrations(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = VolunteerOpportunity::query()->create([
            'title_en' => 'Already completed', 'title_ar' => 'مكتملة بالفعل',
            'description_en' => 'Desc', 'description_ar' => 'وصف',
            'created_by' => $org->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
            'is_public' => true,
            'participants_needed' => 10,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(8)->toDateString(),
        ]);
        $registration = VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
        ]);
        VolunteerOpportunityAttendance::query()->create([
            'registration_id' => $registration->id,
            'attended_date' => now()->subDays(9)->toDateString(),
            'total_hours' => 5,
            'is_attended' => true,
        ]);

        $this->artisan('fursa:backfill-missing-volunteer-certificates')->assertSuccessful();

        $this->assertTrue($registration->fresh()->is_certified);
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
