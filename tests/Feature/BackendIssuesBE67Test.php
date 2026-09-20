<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\VolunteerCategory;
use App\Models\UserNotification;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use App\Models\VolunteerOpportunityRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class BackendIssuesBE67Test extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_be67_1_registration_resource_returns_full_user_and_guardian_data(): void
    {
        [$organization, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $volunteer->update([
            'emergency_contact_name' => 'Guardian Name',
            'emergency_contact_phone' => '99998888',
            'emergency_contact_civil_id' => '111222333',
        ]);
        $opportunity = $this->opportunity($organization->id);
        $registration = VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'registration_date' => now(),
            'status' => ApprovalStatus::APPROVED,
        ]);

        $response = $this->api($orgToken)
            ->getJson('/api/volunteer-opportunity-registrations/?opportunity_id='.$opportunity->id)
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $registration->id);
        $this->assertIsArray($row['user']);
        $this->assertSame($volunteer->id, $row['user']['id']);
        $this->assertSame('Guardian Name', $row['user']['emergency_contact_name']);
        $this->assertSame($volunteer->id, $row['user_id']);
    }

    public function test_be67_2_attendance_rows_carry_their_own_id_for_edit_and_undo(): void
    {
        [$organization, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($organization->id);
        $registration = VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'registration_date' => now(),
            'status' => ApprovalStatus::APPROVED,
        ]);
        $attendance = $registration->attendances()->create([
            'attended_date' => now()->toDateString(),
            'is_attended' => true,
            'total_hours' => 3,
        ]);

        $response = $this->api($orgToken)
            ->getJson('/api/volunteer-opportunity-registrations/?opportunity_id='.$opportunity->id)
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $registration->id);
        $this->assertCount(1, $row['attendances']);
        $this->assertSame($attendance->id, $row['attendances'][0]['id']);
    }

    public function test_be67_3_direct_register_sends_a_confirmation_notification(): void
    {
        [$organization, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $opportunity = $this->opportunity($organization->id);

        $this->api($orgToken)->postJson('/api/volunteer-opportunity-registrations/direct-register/', [
            'opportunity_id' => $opportunity->id,
            'user_ids' => [$volunteer->id],
        ])->assertStatus(201);

        $this->assertTrue(
            UserNotification::query()->where('user_id', $volunteer->id)->exists(),
            'A manually added volunteer should receive a registration confirmation notification.'
        );
    }

    public function test_be67_4_search_matches_civil_id_and_passport_number(): void
    {
        [$organization, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $volunteer->update(['civil_id' => '287654321098', 'passport_number' => 'PP998877']);
        $opportunity = $this->opportunity($organization->id);
        VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'registration_date' => now(),
            'status' => ApprovalStatus::APPROVED,
        ]);

        foreach (['287654321098', 'PP998877'] as $term) {
            $response = $this->api($orgToken)
                ->getJson('/api/volunteer-opportunity-registrations/?opportunity_id='.$opportunity->id.'&search='.$term)
                ->assertOk();
            $this->assertCount(1, $response->json('data'));
            $this->assertSame($volunteer->id, $response->json('data.0.user_id'));
        }
    }

    public function test_be67_5_role_assignment_is_rejected_once_participants_needed_is_reached(): void
    {
        [$organization, $orgToken] = $this->createOrganizationActor();
        $opportunity = $this->opportunity($organization->id);
        $role = VolunteerOpportunityRole::query()->create([
            'opportunity_id' => $opportunity->id,
            'role_name_en' => 'Registration Desk',
            'role_name_ar' => 'مكتب التسجيل',
            'participants_needed' => 1,
        ]);

        [$firstVolunteer] = $this->createVolunteerActor();
        [$secondVolunteer] = $this->createVolunteerActor();
        $firstRegistration = VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $firstVolunteer->id,
            'registration_date' => now(),
            'status' => ApprovalStatus::APPROVED,
        ]);
        $secondRegistration = VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $secondVolunteer->id,
            'registration_date' => now(),
            'status' => ApprovalStatus::APPROVED,
        ]);

        $this->api($orgToken)->patchJson('/api/volunteer-opportunity-registrations/', [
            'registration' => $firstRegistration->id,
            'role' => $role->id,
        ])->assertOk();

        $this->api($orgToken)->patchJson('/api/volunteer-opportunity-registrations/', [
            'registration' => $secondRegistration->id,
            'role' => $role->id,
        ])->assertStatus(422)
            ->assertJsonStructure(['response_status' => ['validation_errors' => ['role']]]);

        // Re-saving the already-assigned volunteer on the same (now full) role must not be blocked.
        $this->api($orgToken)->patchJson('/api/volunteer-opportunity-registrations/', [
            'registration' => $firstRegistration->id,
            'role' => $role->id,
        ])->assertOk();
    }

    protected function opportunity(int $creatorId): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create([
            'created_by' => $creatorId,
            'title_en' => 'BE-67 test opportunity',
            'title_ar' => 'فرصة اختبار',
            'description_en' => 'Description',
            'description_ar' => 'وصف',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'participants_needed' => 5,
            'volunteer_category' => VolunteerCategory::ENVIRONMENTAL->value,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
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
