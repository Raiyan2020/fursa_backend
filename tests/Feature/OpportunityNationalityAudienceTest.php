<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\LearnServeOpportunity;
use App\Models\MasterChoice;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-77 — is_kuwaitis becomes a four-way opportunity_nationality audience,
 * and registration is actually blocked for a mismatch.
 */
class OpportunityNationalityAudienceTest extends TestCase
{
    use CreatesDomainFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function api(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function opportunity($owner, array $extra = []): VolunteerOpportunity
    {
        return VolunteerOpportunity::create($extra + [
            'created_by' => $owner->id,
            'title_en' => 'Cleanup', 'title_ar' => 'تنظيف',
            'description_en' => 'd', 'description_ar' => 'و',
            'start_date' => now()->addDays(2), 'end_date' => now()->addDays(3),
            'participants_needed' => 10,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'is_public' => true,
        ]);
    }

    /**
     * Exercises the actual migration file's backfill logic (not just a
     * hand-rolled equivalent) against rows simulating pre-migration state:
     * `opportunity_nationality` unset, only the legacy `is_kuwaitis` boolean
     * meaningful.
     */
    public function test_migration_backfill_maps_is_kuwaitis_to_the_new_column(): void
    {
        [$owner] = $this->createOrganizationActor();
        $trueRow = $this->opportunity($owner, ['is_kuwaitis' => true]);
        $falseRow = $this->opportunity($owner, ['is_kuwaitis' => false]);
        \Illuminate\Support\Facades\DB::table('volunteer_opportunities')->whereIn('id', [$trueRow->id, $falseRow->id])
            ->update(['opportunity_nationality' => null]);

        (include database_path('migrations/2026_09_21_000002_add_be77_nationality_audience_fields.php'))->up();

        $this->assertSame('kuwaitis', $trueRow->fresh()->opportunity_nationality);
        $this->assertSame('all', $falseRow->fresh()->opportunity_nationality);
    }

    // --- A: create/update stores and returns the four-value column -----

    public function test_publish_with_opportunity_nationality_non_arabic_is_stored_and_returned(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        $opportunity = $this->opportunity($owner);

        $response = $this->api($token)->patchJson("/api/volunteer-opportunities/{$opportunity->id}/", [
            'opportunity_nationality' => 'non_arabic',
        ])->assertOk();

        $this->assertSame('non_arabic', $response->json('data.opportunity_nationality'));
        $this->assertFalse((bool) $opportunity->fresh()->is_kuwaitis);
    }

    public function test_publish_with_legacy_is_kuwaitis_and_no_new_key_still_works(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        $opportunity = $this->opportunity($owner);

        $this->api($token)->patchJson("/api/volunteer-opportunities/{$opportunity->id}/", [
            'is_kuwaitis' => 1,
        ])->assertOk();

        $fresh = $opportunity->fresh();
        $this->assertTrue((bool) $fresh->is_kuwaitis);
        $this->assertSame('kuwaitis', $fresh->opportunity_nationality);
    }

    public function test_create_without_either_field_defaults_to_all(): void
    {
        [, $token] = $this->createOrganizationActor();

        $response = $this->api($token)->postJson('/api/volunteer-opportunities/', [
            'title_en' => 'New', 'title_ar' => 'جديد',
            'description_en' => 'd', 'description_ar' => 'و',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'participants_needed' => 5,
            'volunteer_category' => 'charity',
        ])->assertCreated();

        $this->assertSame('all', $response->json('data.opportunity_nationality'));
    }

    // --- D: the list filter accepts all four values, plus legacy alias -

    public function test_list_filter_accepts_all_four_values_and_the_legacy_alias(): void
    {
        [$owner] = $this->createOrganizationActor();
        $all = $this->opportunity($owner, ['opportunity_nationality' => 'all', 'title_en' => 'For all']);
        $kuwaitis = $this->opportunity($owner, ['opportunity_nationality' => 'kuwaitis', 'title_en' => 'For kuwaitis']);
        $nonKuwaitiArabic = $this->opportunity($owner, ['opportunity_nationality' => 'non_kuwaiti_arabic', 'title_en' => 'For arabic']);
        $nonArabic = $this->opportunity($owner, ['opportunity_nationality' => 'non_arabic', 'title_en' => 'For non arabic']);

        [, $token] = $this->createVolunteerActor();

        $narrowed = $this->api($token)->getJson('/api/list-volunteer-opportunities/?opportunity_nationality=non_kuwaiti_arabic')
            ->assertOk();
        $narrowedIds = collect($narrowed->json('data.items') ?? $narrowed->json('data'))->pluck('id')->all();
        $this->assertSame([$nonKuwaitiArabic->id], $narrowedIds);

        // Legacy alias: "not Kuwaitis-only".
        $response = $this->api($token)->getJson('/api/list-volunteer-opportunities/?opportunity_nationality=non-kuwaitis')->assertOk();
        $ids = collect($response->json('data.items') ?? $response->json('data'))->pluck('id')->all();
        $this->assertContains($all->id, $ids);
        $this->assertContains($nonKuwaitiArabic->id, $ids);
        $this->assertContains($nonArabic->id, $ids);
        $this->assertNotContains($kuwaitis->id, $ids);
    }

    // --- C: registration is actually blocked -----------------------------

    public function test_kuwaiti_user_registers_on_a_kuwaitis_opportunity_allowed(): void
    {
        [$owner] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();
        $volunteer->update(['nationality' => 'kuwaitis']);
        $opportunity = $this->opportunity($owner, ['opportunity_nationality' => 'kuwaitis']);

        $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => $opportunity->id,
        ])->assertCreated();
    }

    public function test_kuwaiti_user_registers_on_a_non_arabic_opportunity_refused(): void
    {
        [$owner] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();
        $volunteer->update(['nationality' => 'kuwaitis']);
        $opportunity = $this->opportunity($owner, ['opportunity_nationality' => 'non_arabic']);

        $response = $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => $opportunity->id,
        ])->assertStatus(400);

        $this->assertStringContainsString('only', $response->json('msg'));
    }

    public function test_user_with_no_speaks_arabic_answer_passes_the_arabic_only_audiences(): void
    {
        [$owner] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();
        $volunteer->update(['nationality' => 'other']);
        $this->assertNull($volunteer->fresh()->speaks_arabic);

        $opportunity = $this->opportunity($owner, ['opportunity_nationality' => 'non_kuwaiti_arabic']);

        $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => $opportunity->id,
        ])->assertCreated();
    }

    public function test_user_with_no_speaks_arabic_answer_passes_a_non_arabic_opportunity_too(): void
    {
        [$owner] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();
        $volunteer->update(['nationality' => 'other']);
        $this->assertNull($volunteer->fresh()->speaks_arabic);

        $opportunity = $this->opportunity($owner, ['opportunity_nationality' => 'non_arabic']);

        $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => $opportunity->id,
        ])->assertCreated();
    }

    public function test_non_arabic_speaker_is_refused_on_a_non_kuwaiti_arabic_opportunity(): void
    {
        [$owner] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();
        $volunteer->update(['nationality' => 'other', 'speaks_arabic' => false]);
        $opportunity = $this->opportunity($owner, ['opportunity_nationality' => 'non_kuwaiti_arabic']);

        $this->api($token)->postJson('/api/volunteer-opportunity-registrations/', [
            'opportunity_id' => $opportunity->id,
        ])->assertStatus(400);
    }

    public function test_learn_serve_registration_enforces_the_same_guard(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();
        $volunteer->update(['nationality' => 'kuwaitis']);

        $format = MasterChoice::query()->whereHas('choiceType', fn ($q) => $q->where('name', 'learn_serve_format'))->firstOrFail();
        $learningType = MasterChoice::query()->whereHas('choiceType', fn ($q) => $q->where('name', 'learning_type'))
            ->where('value_en', 'Class/Workshop')->firstOrFail();

        $opportunity = LearnServeOpportunity::create([
            'created_by' => $owner->id,
            'title_en' => 'Course', 'title_ar' => 'دورة',
            'description_en' => 'd', 'description_ar' => 'و',
            'start_date' => now()->addDays(2), 'end_date' => now()->addDays(2),
            'due_date' => now()->addDay(),
            'participants_needed' => 5,
            'format_id' => $format->id,
            'learning_type_id' => $learningType->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'opportunity_nationality' => 'non_arabic',
        ]);

        $this->api($token)->postJson('/api/learn-serve-opportunity-registrations/', [
            'opportunity_id' => $opportunity->id,
        ])->assertStatus(400);
    }
}
