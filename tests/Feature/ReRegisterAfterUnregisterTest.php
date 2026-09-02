<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\VolunteerCategory;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use App\Models\VolunteerOpportunityRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * Register -> unregister -> register again used to return a raw 500.
 *
 * unregister() soft-deletes, and the unique index on
 * (opportunity_id, user_id) is not scoped to exclude soft-deleted rows, so the
 * second insert hit a 1062 duplicate-key violation. store() now revives the
 * cancelled row, and a 1062 that still slips through (concurrent requests)
 * returns the normal envelope instead of a stack trace.
 */
class ReRegisterAfterUnregisterTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed();
    }

    public function test_register_unregister_register_again_succeeds(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id);

        // 1) register
        $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => (string) $opportunity->id,
        ])->assertSuccessful();

        // 2) unregister
        $this->api($token)
            ->deleteJson("/api/volunteer-opportunities/{$opportunity->id}/unregister/")
            ->assertOk()
            ->assertJsonPath('key', 'success');

        // 3) register again with the same payload — used to be a 500
        $again = $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => (string) $opportunity->id,
        ]);

        $again->assertSuccessful();
        $again->assertJsonPath('key', 'success');
        $again->assertJsonPath('response_status.error', false);
    }

    public function test_re_registering_reuses_the_same_row(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id);

        $first = $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => (string) $opportunity->id,
        ]);
        $firstId = $first->json('data.registration.id');

        $this->api($token)->deleteJson("/api/volunteer-opportunities/{$opportunity->id}/unregister/");

        $second = $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => (string) $opportunity->id,
        ]);

        $this->assertSame($firstId, $second->json('data.registration.id'));

        // Exactly one row for the pair, and it is active again.
        $rows = VolunteerOpportunityRegistration::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('user_id', $volunteer->id)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertFalse((bool) $rows->first()->is_deleted);
        $this->assertSame(ApprovalStatus::PENDING, $rows->first()->status);
    }

    public function test_re_registering_with_a_role_reassigns_it(): void
    {
        [$org] = $this->createOrganizationActor();
        [, $token] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id);
        $role = VolunteerOpportunityRole::query()->create([
            'opportunity_id' => $opportunity->id,
            'role_name_en' => 'Helper',
            'role_name_ar' => 'مساعد',
            'participants_needed' => 5,
        ]);

        $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => (string) $opportunity->id,
            'role_id' => (string) $role->id,
        ])->assertSuccessful();

        $this->api($token)->deleteJson("/api/volunteer-opportunities/{$opportunity->id}/unregister/");

        $again = $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => (string) $opportunity->id,
            'role_id' => (string) $role->id,
        ]);

        $again->assertSuccessful();
        $again->assertJsonPath('data.registration.role.id', $role->id);
    }

    public function test_re_registering_does_not_consume_two_role_slots(): void
    {
        [$org] = $this->createOrganizationActor();
        [, $token] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id);
        $role = VolunteerOpportunityRole::query()->create([
            'opportunity_id' => $opportunity->id,
            'role_name_en' => 'Only one',
            'role_name_ar' => 'واحد فقط',
            'participants_needed' => 1,
        ]);

        $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => (string) $opportunity->id,
            'role_id' => (string) $role->id,
        ])->assertSuccessful();

        $this->api($token)->deleteJson("/api/volunteer-opportunities/{$opportunity->id}/unregister/");

        // The cancelled assignment must not still be occupying the single slot.
        $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => (string) $opportunity->id,
            'role_id' => (string) $role->id,
        ])->assertSuccessful();
    }

    public function test_registering_twice_without_unregistering_is_still_rejected(): void
    {
        [$org] = $this->createOrganizationActor();
        [, $token] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id);

        $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => (string) $opportunity->id,
        ])->assertSuccessful();

        // The pre-existing guard still applies; no silent re-register.
        $duplicate = $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => (string) $opportunity->id,
        ]);

        $duplicate->assertStatus(400);
        $duplicate->assertJsonPath('response_status.error', true);
    }

    public function test_unregister_twice_reports_not_found_rather_than_erroring(): void
    {
        [$org] = $this->createOrganizationActor();
        [, $token] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id);

        $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => (string) $opportunity->id,
        ])->assertSuccessful();

        $this->api($token)
            ->deleteJson("/api/volunteer-opportunities/{$opportunity->id}/unregister/")
            ->assertOk();

        $this->api($token)
            ->deleteJson("/api/volunteer-opportunities/{$opportunity->id}/unregister/")
            ->assertStatus(404);
    }

    public function test_the_full_cycle_can_repeat(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id);

        // Three full cycles; none of them may 500 or duplicate the row.
        for ($i = 0; $i < 3; $i++) {
            $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
                'opportunity_id' => (string) $opportunity->id,
            ])->assertSuccessful();

            $this->api($token)
                ->deleteJson("/api/volunteer-opportunities/{$opportunity->id}/unregister/")
                ->assertOk();
        }

        $this->assertSame(
            1,
            VolunteerOpportunityRegistration::query()
                ->where('opportunity_id', $opportunity->id)
                ->where('user_id', $volunteer->id)
                ->count()
        );
    }

    protected function opportunity(int $ownerId): VolunteerOpportunity
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
            'from_age' => 1,
            'to_age' => 99,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
            'volunteer_category' => VolunteerCategory::ENVIRONMENTAL->value,
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
