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
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;
use ZipArchive;

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

    public function test_learn_serve_registrations_list_includes_contact_picture_gender_and_visibility(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $volunteer->update(['phone_number' => '55512345', 'country_code' => '+965', 'profile_pic' => 'profile-pics/test.jpg']);
        $volunteer->volunteerProfile->update(['is_public' => true]);

        $genderChoice = \App\Models\MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'gender'))
            ->firstOrFail();
        $volunteer->volunteerProfile->update(['gender_id' => $genderChoice->id]);

        $opportunity = LearnServeOpportunity::query()->create([
            'title_en' => 'Course', 'title_ar' => 'دورة', 'description_en' => 'Description', 'description_ar' => 'وصف',
            'created_by' => $owner->id, 'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING, 'participants_needed' => 10,
            'start_date' => now()->addDays(3), 'end_date' => now()->addDays(5),
        ]);
        LearnServeOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $volunteer->id, 'status' => ApprovalStatus::PENDING,
        ]);

        $response = $this->api($token)->getJson("/api/learn-serve-opportunities/{$opportunity->id}/registrations/");
        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('user_id', $volunteer->id);

        $this->assertSame('+96555512345', $row['user_contact_number']);
        $this->assertSame('55512345', $row['phone_number']);
        $this->assertNotNull($row['profile_pic']);
        $this->assertSame($genderChoice->id, $row['gender_display']['id']);
        $this->assertTrue($row['is_public']);
        $this->assertSame($row['user_name'], $row['full_name']);
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

    public function test_download_returns_real_xlsx_and_marks_only_approved_registrations(): void
    {
        Storage::fake('public');
        [$owner, $token] = $this->createOrganizationActor();
        [$approved] = $this->createVolunteerActor();
        [$rejected] = $this->createVolunteerActor();
        $opportunity = $this->volunteerOpportunity($owner, [
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);
        $approvedRegistration = VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $approved->id, 'status' => ApprovalStatus::APPROVED,
        ]);
        $rejectedRegistration = VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $rejected->id, 'status' => ApprovalStatus::REJECTED,
        ]);

        $response = $this->api($token)->getJson('/api/volunteer-opportunity-registrations/?'.http_build_query([
            'opportunity_id' => $opportunity->id,
            'download' => 'true',
            'mark_attendance' => 'true',
            'date' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonPath('data.file_format', 'xlsx')
            ->assertJsonPath('data.registrations_count', 2)
            ->assertJsonPath('data.attendance_marked_count', 1);
        $this->assertNotEmpty($response->json('data.downloadUrl'));
        $this->assertDatabaseHas('volunteer_opportunity_attendances', [
            'registration_id' => $approvedRegistration->id,
            'is_attended' => true,
        ]);
        $this->assertDatabaseMissing('volunteer_opportunity_attendances', [
            'registration_id' => $rejectedRegistration->id,
        ]);

        $files = Storage::disk('public')->allFiles('exports');
        $this->assertCount(1, $files);
        $temporary = tempnam(sys_get_temp_dir(), 'verify_xlsx_');
        file_put_contents($temporary, Storage::disk('public')->get($files[0]));
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($temporary) === true);
        $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        @unlink($temporary);

        $second = $this->api($token)->getJson('/api/volunteer-opportunity-registrations/?'.http_build_query([
            'opportunity_id' => $opportunity->id,
            'download' => 'true',
            'mark_attendance' => 'true',
            'date' => now()->toDateString(),
        ]));
        $second->assertOk()
            ->assertJsonPath('data.attendance_marked_count', 0)
            ->assertJsonPath('data.attendance_already_marked_count', 1);
    }

    public function test_download_export_includes_guardian_columns(): void
    {
        Storage::fake('public');
        [$owner, $token] = $this->createOrganizationActor();
        [$approved] = $this->createVolunteerActor();

        $relationship = \App\Models\MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'emergency_contact_relationship'))
            ->where('value_en', 'Mother')
            ->firstOrFail();

        $approved->update([
            'emergency_contact_name' => 'Jane Guardian',
            'emergency_contact_phone' => '99887766',
            'emergency_contact_country_code' => '+965',
            'emergency_contact_civil_id' => '281234567890',
            'emergency_contact_relationship_id' => $relationship->id,
        ]);

        $opportunity = $this->volunteerOpportunity($owner);
        VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $approved->id, 'status' => ApprovalStatus::APPROVED,
        ]);

        $response = $this->api($token)->getJson('/api/volunteer-opportunity-registrations/?'.http_build_query([
            'opportunity_id' => $opportunity->id,
            'download' => 'true',
        ]));

        $response->assertOk()->assertJsonPath('data.registrations_count', 1);

        $files = Storage::disk('public')->allFiles('exports');
        $this->assertCount(1, $files);
        $temporary = tempnam(sys_get_temp_dir(), 'verify_xlsx_');
        file_put_contents($temporary, Storage::disk('public')->get($files[0]));
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($temporary) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($temporary);

        $this->assertNotFalse($sheet);
        $this->assertStringContainsString('Guardian Name', $sheet);
        $this->assertStringContainsString('Guardian Phone', $sheet);
        $this->assertStringContainsString('Guardian Civil ID', $sheet);
        $this->assertStringContainsString('Guardian Relationship', $sheet);
        $this->assertStringContainsString('Jane Guardian', $sheet);
        $this->assertStringContainsString('99887766', $sheet);
        $this->assertStringContainsString('281234567890', $sheet);
        $this->assertStringContainsString($relationship->value_ar, $sheet);
    }

    public function test_download_export_no_longer_has_a_team_column(): void
    {
        Storage::fake('public');
        [$owner, $token] = $this->createOrganizationActor();
        [$approved] = $this->createVolunteerActor();
        $opportunity = $this->volunteerOpportunity($owner);

        $team = \App\Models\VolunteerOpportunityTeam::query()->create([
            'opportunity_id' => $opportunity->id,
            'team_name_en' => 'Distinctive Team Name',
            'team_name_ar' => 'فريق مميز',
        ]);
        $registration = VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $approved->id, 'status' => ApprovalStatus::APPROVED,
        ]);
        \App\Models\VolunteerOpportunityAssignment::query()->create([
            'registration_id' => $registration->id, 'team_id' => $team->id,
        ]);

        $response = $this->api($token)->getJson('/api/volunteer-opportunity-registrations/?'.http_build_query([
            'opportunity_id' => $opportunity->id,
            'download' => 'true',
        ]));
        $response->assertOk();

        $files = Storage::disk('public')->allFiles('exports');
        $temporary = tempnam(sys_get_temp_dir(), 'verify_xlsx_');
        file_put_contents($temporary, Storage::disk('public')->get($files[0]));
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($temporary) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($temporary);

        $this->assertNotFalse($sheet);
        $this->assertStringNotContainsString('Team', $sheet);
        $this->assertStringNotContainsString('Distinctive Team Name', $sheet);
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
