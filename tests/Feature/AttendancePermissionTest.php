<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\AttendancePermission;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityAttendance;
use App\Models\VolunteerOpportunityRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-69 — «إذن تحضير»: one permission letting a volunteer both add other
 * volunteers to an opportunity and record/edit their attendance hours. Not
 * QR scanning, and deliberately not built on ScanPermission (BE-61 Part C is
 * retiring that flow).
 */
class AttendancePermissionTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_owner_can_grant_and_revoke_via_the_permissions_array_shape(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $opportunity = $this->volunteerOpportunity($owner);

        $grant = $this->api($token)->postJson('/api/attendance-permissions/bulk-update/', [
            'opportunity_id' => $opportunity->id,
            'permissions' => [
                ['user_id' => $volunteer->id, 'is_allowed' => true],
            ],
        ]);
        $grant->assertOk()->assertJsonPath('data.0.is_allowed', true);
        $this->assertTrue(AttendancePermission::grants($opportunity->id, $volunteer->id));

        $revoke = $this->api($token)->postJson('/api/attendance-permissions/bulk-update/', [
            'opportunity_id' => $opportunity->id,
            'permissions' => [
                ['user_id' => $volunteer->id, 'is_allowed' => false],
            ],
        ]);
        $revoke->assertOk()->assertJsonPath('data.0.is_allowed', false);
        $this->assertFalse(AttendancePermission::grants($opportunity->id, $volunteer->id));
    }

    public function test_owner_can_grant_via_the_flat_user_ids_shape(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        [$first] = $this->createVolunteerActor();
        [$second] = $this->createVolunteerActor();
        $opportunity = $this->volunteerOpportunity($owner);

        $this->api($token)->postJson('/api/attendance-permissions/bulk-update/', [
            'opportunity_id' => $opportunity->id,
            'user_ids' => [$first->id, $second->id],
        ])->assertOk();

        $this->assertTrue(AttendancePermission::grants($opportunity->id, $first->id));
        $this->assertTrue(AttendancePermission::grants($opportunity->id, $second->id));
    }

    public function test_a_stranger_cannot_grant_permissions(): void
    {
        [$owner] = $this->createOrganizationActor();
        [, $strangerToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $opportunity = $this->volunteerOpportunity($owner);

        $this->api($strangerToken)->postJson('/api/attendance-permissions/bulk-update/', [
            'opportunity_id' => $opportunity->id,
            'permissions' => [['user_id' => $volunteer->id, 'is_allowed' => true]],
        ])->assertForbidden();

        $this->assertFalse(AttendancePermission::grants($opportunity->id, $volunteer->id));
    }

    public function test_owner_can_list_current_holders_and_search_them(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        [$holder] = $this->createVolunteerActor();
        [$nonHolder] = $this->createVolunteerActor();
        $opportunity = $this->volunteerOpportunity($owner);

        AttendancePermission::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $holder->id, 'is_allowed' => true,
        ]);
        AttendancePermission::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $nonHolder->id, 'is_allowed' => false,
        ]);

        $list = $this->api($token)->getJson('/api/attendance-permissions/list/?opportunity_id='.$opportunity->id);
        $list->assertOk();
        $ids = collect($list->json('data'))->pluck('id')->all();
        $this->assertContains($holder->id, $ids);
        $this->assertNotContains($nonHolder->id, $ids);

        $search = $this->api($token)->getJson('/api/attendance-permissions/list/?'.http_build_query([
            'opportunity_id' => $opportunity->id,
            'search' => $holder->civil_id,
        ]));
        $this->assertEqualsCanonicalizing([$holder->id], collect($search->json('data'))->pluck('id')->all());
    }

    public function test_a_permission_holder_can_directly_register_volunteers_but_a_plain_volunteer_cannot(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$holder, $holderToken] = $this->createVolunteerActor();
        [$stranger, $strangerToken] = $this->createVolunteerActor();
        [$newVolunteer] = $this->createVolunteerActor();
        $opportunity = $this->volunteerOpportunity($owner);

        AttendancePermission::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $holder->id, 'is_allowed' => true,
        ]);

        $this->api($strangerToken)->postJson('/api/volunteer-opportunity-registrations/direct-register/', [
            'opportunity_id' => $opportunity->id,
            'user_ids' => [$newVolunteer->id],
        ])->assertForbidden();

        $this->api($holderToken)->postJson('/api/volunteer-opportunity-registrations/direct-register/', [
            'opportunity_id' => $opportunity->id,
            'user_ids' => [$newVolunteer->id],
        ])->assertCreated();

        $this->assertDatabaseHas('volunteer_opportunity_registrations', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $newVolunteer->id,
        ]);
    }

    public function test_a_permission_holder_can_record_manual_attendance_but_a_plain_volunteer_cannot(): void
    {
        [$owner] = $this->createOrganizationActor();
        [$holder, $holderToken] = $this->createVolunteerActor();
        [, $strangerToken] = $this->createVolunteerActor();
        [$registrant] = $this->createVolunteerActor();
        $opportunity = $this->volunteerOpportunity($owner, [
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);
        VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $registrant->id, 'status' => ApprovalStatus::APPROVED,
        ]);

        AttendancePermission::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $holder->id, 'is_allowed' => true,
        ]);

        $this->api($strangerToken)->postJson('/api/volunteer-attendance/manual/', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $registrant->id,
        ])->assertForbidden();

        $this->api($holderToken)->postJson('/api/volunteer-attendance/manual/', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $registrant->id,
        ])->assertOk();

        $this->assertDatabaseHas('volunteer_opportunity_attendances', [
            'registration_id' => VolunteerOpportunityRegistration::query()
                ->where('opportunity_id', $opportunity->id)->where('user_id', $registrant->id)->value('id'),
            'is_attended' => true,
        ]);
    }

    public function test_a_permission_holder_can_edit_attendance_hours_but_a_plain_volunteer_cannot(): void
    {
        [$owner] = $this->createOrganizationActor();
        [$holder, $holderToken] = $this->createVolunteerActor();
        [, $strangerToken] = $this->createVolunteerActor();
        [$registrant] = $this->createVolunteerActor();
        $opportunity = $this->volunteerOpportunity($owner);
        $registration = VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $registrant->id, 'status' => ApprovalStatus::APPROVED,
        ]);
        $attendance = VolunteerOpportunityAttendance::query()->create([
            'registration_id' => $registration->id,
            'attended_date' => now()->toDateString(),
            'is_attended' => true,
            'total_hours' => 2,
            'recorded_via' => 'manual',
        ]);

        AttendancePermission::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $holder->id, 'is_allowed' => true,
        ]);

        $this->api($strangerToken)->patchJson("/api/volunteer-attendance/{$attendance->id}/hours/", [
            'total_hours' => 3,
        ])->assertForbidden();

        $this->api($holderToken)->patchJson("/api/volunteer-attendance/{$attendance->id}/hours/", [
            'total_hours' => 3,
        ])->assertOk();

        $this->assertEquals(3, $attendance->fresh()->total_hours);
    }

    public function test_opportunity_resource_flags_can_manage_attendance_for_the_owner_and_holder_but_not_a_stranger(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$holder, $holderToken] = $this->createVolunteerActor();
        [, $strangerToken] = $this->createVolunteerActor();
        $opportunity = $this->volunteerOpportunity($owner);

        AttendancePermission::query()->create([
            'opportunity_id' => $opportunity->id, 'user_id' => $holder->id, 'is_allowed' => true,
        ]);

        $this->api($ownerToken)->getJson("/api/opportunities/{$opportunity->id}/details/")
            ->assertJsonPath('data.can_manage_attendance', true);
        $this->api($holderToken)->getJson("/api/opportunities/{$opportunity->id}/details/")
            ->assertJsonPath('data.can_manage_attendance', true);
        $this->api($strangerToken)->getJson("/api/opportunities/{$opportunity->id}/details/")
            ->assertJsonPath('data.can_manage_attendance', false);
    }

    protected function volunteerOpportunity(User $owner, array $overrides = []): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create(array_merge([
            'title_en' => 'Managed opportunity', 'title_ar' => 'فرصة', 'description_en' => 'Description', 'description_ar' => 'وصف',
            'created_by' => $owner->id, 'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING, 'is_public' => true, 'participants_needed' => 10,
            'due_date' => now()->addDay()->toDateString(),
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ], $overrides));
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
