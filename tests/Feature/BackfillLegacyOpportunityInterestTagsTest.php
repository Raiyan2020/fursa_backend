<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\Interest;
use App\Models\MasterChoice;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * Reproduces BE-01: an opportunity tagged only through the legacy
 * MasterChoice-based pivot (imported from the old database) must show up in
 * `interests`/`interest_display` after running the backfill command, exactly
 * like a record tagged the current way.
 */
class BackfillLegacyOpportunityInterestTagsTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_backfill_bridges_legacy_master_choice_tags_into_the_interest_model(): void
    {
        [$org] = $this->createOrganizationActor();

        $opportunity = VolunteerOpportunity::query()->create([
            'title_en' => 'Legacy Tagged Opportunity',
            'title_ar' => 'فرصة موسومة قديمًا',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'created_by' => $org->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'is_public' => true,
            'participants_needed' => 8,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        $communityService = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'volunteer_opportunity_interest'))
            ->where('value_en', 'Community Service')
            ->firstOrFail();

        // Only the legacy pivot has a row -- the current Interest-model pivot
        // is empty, matching what the imported production dump looks like.
        DB::table('master_choice_volunteer_opportunity')->insert([
            'volunteer_opportunity_id' => $opportunity->id,
            'master_choice_id' => $communityService->id,
        ]);

        $before = $this->getJson("/api/opportunities/{$opportunity->id}/details/");
        $before->assertJsonPath('data.interests', []);

        $this->artisan('fursa:backfill-legacy-opportunity-interest-tags')->assertSuccessful();

        $interest = Interest::query()->where('name_en', 'Community Service')->firstOrFail();
        $this->assertSame('خدمة مجتمعية', $interest->name_ar);
        $this->assertDatabaseHas('interest_volunteer_opportunity', [
            'volunteer_opportunity_id' => $opportunity->id,
            'interest_id' => $interest->id,
        ]);

        $after = $this->getJson("/api/opportunities/{$opportunity->id}/details/");
        $after->assertJsonPath('data.interests', [[
            'id' => $interest->id,
            'name_en' => 'Community Service',
            'name_ar' => 'خدمة مجتمعية',
            'interest_type' => 'volunteer',
        ]]);

        // Re-running must not duplicate the pivot row or the Interest row.
        $this->artisan('fursa:backfill-legacy-opportunity-interest-tags')->assertSuccessful();
        $this->assertSame(1, Interest::query()->where('name_en', 'Community Service')->count());
        $this->assertSame(
            1,
            DB::table('interest_volunteer_opportunity')
                ->where('volunteer_opportunity_id', $opportunity->id)
                ->where('interest_id', $interest->id)
                ->count()
        );
    }
}
