<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\ScanPermission;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * bulk-update accepts two request shapes for the same operation:
 *
 *   permissions: [{user_id, is_allowed}]  — per-entry, can mix grant + revoke
 *   user_ids: [...] + is_allowed          — one flag for the whole batch
 *
 * The frontend had been sending the second form, which the endpoint rejected
 * with "permissions field is required"; it is now normalised server-side.
 */
class ScanPermissionBulkUpdateTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed();
    }

    public function test_user_ids_with_is_allowed_true_grants_permission(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        [$a] = $this->createVolunteerActor();
        [$b] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id);

        $response = $this->api($token)->postJson('/api/scan-permissions/bulk-update/', [
            'opportunity_id' => $opportunity->id,
            'user_ids' => [$a->id, $b->id],
            'is_allowed' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('key', 'success');
        $response->assertJsonPath('response_status.error', false);
        $this->assertCount(2, $response->json('data'));

        foreach ([$a, $b] as $user) {
            $this->assertTrue(
                ScanPermission::query()
                    ->where('opportunity_id', $opportunity->id)
                    ->where('user_id', $user->id)
                    ->value('is_allowed')
            );
        }
    }

    public function test_user_ids_with_is_allowed_false_revokes_permission(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id);

        $this->api($token)->postJson('/api/scan-permissions/bulk-update/', [
            'opportunity_id' => $opportunity->id,
            'user_ids' => [$volunteer->id],
            'is_allowed' => true,
        ])->assertOk();

        $this->api($token)->postJson('/api/scan-permissions/bulk-update/', [
            'opportunity_id' => $opportunity->id,
            'user_ids' => [$volunteer->id],
            'is_allowed' => false,
        ])->assertOk();

        $this->assertFalse(
            (bool) ScanPermission::query()
                ->where('opportunity_id', $opportunity->id)
                ->where('user_id', $volunteer->id)
                ->value('is_allowed')
        );
    }

    public function test_omitting_is_allowed_defaults_to_granting(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id);

        $this->api($token)->postJson('/api/scan-permissions/bulk-update/', [
            'opportunity_id' => $opportunity->id,
            'user_ids' => [$volunteer->id],
        ])->assertOk();

        $this->assertTrue(
            (bool) ScanPermission::query()
                ->where('opportunity_id', $opportunity->id)
                ->where('user_id', $volunteer->id)
                ->value('is_allowed')
        );
    }

    public function test_the_canonical_permissions_shape_still_works(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id);

        $this->api($token)->postJson('/api/scan-permissions/bulk-update/', [
            'opportunity_id' => $opportunity->id,
            'permissions' => [
                ['user_id' => $volunteer->id, 'is_allowed' => true],
            ],
        ])->assertOk();

        $this->assertTrue(
            (bool) ScanPermission::query()
                ->where('opportunity_id', $opportunity->id)
                ->where('user_id', $volunteer->id)
                ->value('is_allowed')
        );
    }

    public function test_permissions_can_mix_grants_and_revokes_in_one_call(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        [$granted] = $this->createVolunteerActor();
        [$revoked] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id);

        $this->api($token)->postJson('/api/scan-permissions/bulk-update/', [
            'opportunity_id' => $opportunity->id,
            'permissions' => [
                ['user_id' => $granted->id, 'is_allowed' => true],
                ['user_id' => $revoked->id, 'is_allowed' => false],
            ],
        ])->assertOk();

        $this->assertTrue((bool) ScanPermission::query()
            ->where('opportunity_id', $opportunity->id)->where('user_id', $granted->id)->value('is_allowed'));
        $this->assertFalse((bool) ScanPermission::query()
            ->where('opportunity_id', $opportunity->id)->where('user_id', $revoked->id)->value('is_allowed'));
    }

    public function test_repeating_a_grant_updates_rather_than_duplicating(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id);

        for ($i = 0; $i < 3; $i++) {
            $this->api($token)->postJson('/api/scan-permissions/bulk-update/', [
                'opportunity_id' => $opportunity->id,
                'user_ids' => [$volunteer->id],
                'is_allowed' => true,
            ])->assertOk();
        }

        $this->assertSame(
            1,
            ScanPermission::query()
                ->where('opportunity_id', $opportunity->id)
                ->where('user_id', $volunteer->id)
                ->count()
        );
    }

    public function test_sending_neither_shape_returns_a_localized_validation_error(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        $opportunity = $this->opportunity($org->id);

        $response = $this->api($token)->postJson('/api/scan-permissions/bulk-update/', [
            'opportunity_id' => $opportunity->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('response_status.error', true);
        $this->assertNotEmpty($response->json('response_status.validation_errors.permissions'));
    }

    public function test_a_non_owner_cannot_change_permissions(): void
    {
        [$org] = $this->createOrganizationActor('owner.scan@test.com');
        [, $otherToken] = $this->createOrganizationActor('other.scan@test.com');
        [$volunteer] = $this->createVolunteerActor();

        $opportunity = $this->opportunity($org->id);

        $this->api($otherToken)->postJson('/api/scan-permissions/bulk-update/', [
            'opportunity_id' => $opportunity->id,
            'user_ids' => [$volunteer->id],
            'is_allowed' => true,
        ])->assertStatus(403);
    }

    public function test_missing_both_scopes_is_rejected(): void
    {
        [, $token] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();

        $this->api($token)->postJson('/api/scan-permissions/bulk-update/', [
            'user_ids' => [$volunteer->id],
            'is_allowed' => true,
        ])->assertStatus(400);
    }

    public function test_an_unknown_user_id_is_a_validation_error(): void
    {
        [$org, $token] = $this->createOrganizationActor();
        $opportunity = $this->opportunity($org->id);

        $this->api($token)->postJson('/api/scan-permissions/bulk-update/', [
            'opportunity_id' => $opportunity->id,
            'user_ids' => [999999],
            'is_allowed' => true,
        ])->assertStatus(422);
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
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
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
