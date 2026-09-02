<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class RegistrationManagementTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed();
    }

    public function test_owner_can_approve_selected_volunteer_registrations_and_stranger_cannot(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [, $strangerToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $opportunity = $this->volunteerOpportunity($owner);
        $registration = VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'status' => ApprovalStatus::PENDING,
        ]);

        $this->api($strangerToken)->patchJson("/api/volunteer-opportunities/{$opportunity->id}/registrations/status/", [
            'registration_ids' => [$registration->id],
            'status' => 'approved',
        ])->assertForbidden();

        $this->api($ownerToken)->patchJson("/api/volunteer-opportunities/{$opportunity->id}/registrations/status/", [
            'registration_ids' => [$registration->id],
            'status' => 'approved',
        ])->assertOk()->assertJsonPath('data.updated_count', 1)->assertJsonPath('data.status', 'approved');

        $this->assertSame(ApprovalStatus::APPROVED, $registration->fresh()->status);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $volunteer->id]);
    }

    public function test_owner_can_message_selected_registrants(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        [$first] = $this->createVolunteerActor();
        [$second] = $this->createVolunteerActor();
        $opportunity = $this->volunteerOpportunity($owner);
        $firstRegistration = VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $first->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $second->id,
            'status' => ApprovalStatus::APPROVED,
        ]);

        $this->api($token)->postJson("/api/volunteer-opportunities/{$opportunity->id}/registrations/message/", [
            'registration_ids' => [$firstRegistration->id],
            'subject' => 'Meeting update',
            'message' => 'Please arrive at 8 AM.',
        ])->assertOk()->assertJsonPath('data.sent_count', 1);

        $this->assertDatabaseHas('user_notifications', ['user_id' => $first->id]);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $second->id]);
    }

    public function test_owner_can_manage_learn_and_serve_registration_status(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $opportunity = LearnServeOpportunity::query()->create([
            'title_en' => 'Course', 'title_ar' => 'دورة', 'description_en' => 'Description', 'description_ar' => 'وصف',
            'created_by' => $owner->id, 'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING, 'participants_needed' => 10,
            'start_date' => now()->addDays(3), 'end_date' => now()->addDays(5),
        ]);
        $registration = LearnServeOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $volunteer->id, 'status' => ApprovalStatus::PENDING,
        ]);

        $this->api($token)->patchJson("/api/learn-serve-opportunities/{$opportunity->id}/registrations/status/", [
            'registration_ids' => [$registration->id], 'status' => 'rejected',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertSame(ApprovalStatus::REJECTED, $registration->fresh()->status);
    }

    public function test_day_of_reminder_is_sent_only_to_approved_registrations(): void
    {
        [$owner] = $this->createOrganizationActor();
        [$approved] = $this->createVolunteerActor();
        [$rejected] = $this->createVolunteerActor();
        $opportunity = $this->volunteerOpportunity($owner, [
            'opportunity_status' => OpportunityStatus::INPROGRESS,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
        VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $approved->id, 'status' => ApprovalStatus::APPROVED,
        ]);
        VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $rejected->id, 'status' => ApprovalStatus::REJECTED,
        ]);

        $this->artisan('fursa:send-day-of-notification')->assertSuccessful();

        $this->assertTrue(UserNotification::query()->where('user_id', $approved->id)->exists());
        $this->assertFalse(UserNotification::query()->where('user_id', $rejected->id)->exists());
    }

    public function test_three_day_reminder_skips_cancelled_or_rejected_registrations(): void
    {
        [$owner] = $this->createOrganizationActor();
        [$approved] = $this->createVolunteerActor();
        [$rejected] = $this->createVolunteerActor();
        [$cancelled] = $this->createVolunteerActor();
        $opportunity = $this->volunteerOpportunity($owner, [
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ]);

        VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $approved->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $rejected->id,
            'status' => ApprovalStatus::REJECTED,
        ]);
        VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $cancelled->id,
            'status' => ApprovalStatus::APPROVED,
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);

        $this->artisan('fursa:send-three-day-reminder')->assertSuccessful();

        $this->assertTrue(UserNotification::query()->where('user_id', $approved->id)->exists());
        $this->assertFalse(UserNotification::query()->where('user_id', $rejected->id)->exists());
        $this->assertFalse(UserNotification::query()->where('user_id', $cancelled->id)->exists());
    }

    protected function volunteerOpportunity(User $owner, array $overrides = []): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create(array_merge([
            'title_en' => 'Managed opportunity', 'title_ar' => 'فرصة', 'description_en' => 'Description', 'description_ar' => 'وصف',
            'created_by' => $owner->id, 'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING, 'is_public' => true, 'participants_needed' => 10,
            'start_date' => now()->addDays(3), 'end_date' => now()->addDays(5),
        ], $overrides));
    }

    protected function api(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['Authorization' => 'Token '.$token, 'Accept' => 'application/json', 'Lang' => 'en']);
    }
}
