<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\InterestType;
use App\Enums\OpportunityStatus;
use App\Models\Interest;
use App\Models\LearnServeOpportunity;
use App\Models\MasterChoice;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * match_my_interest returned zero results for every user.
 *
 * A user's interests live in two places: masterInterests (MasterChoice rows of
 * choice type "user_interest", written by the profile screens) and the legacy
 * interests relation (the Interest table, which is what opportunities are
 * tagged with). The profile save writes whichever matches the submitted ids and
 * returns early, so a user who picked interests through the normal UI has
 * masterInterests populated and interests empty — while the filter only ever
 * read interests. Hence a guaranteed empty result.
 */
class MatchMyInterestFilterTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed();
    }

    public function test_matches_when_the_user_picked_interests_through_the_profile_ui(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();

        // The opportunity is tagged with an Interest row...
        $interest = $this->interest('Environment', 'البيئة');
        $matching = $this->opportunity($org->id, 'Beach cleanup');
        $matching->interests()->sync([$interest->id]);

        $this->opportunity($org->id, 'Unrelated');

        // ...and the volunteer picks the equivalent MasterChoice through the
        // profile screen, which is the only path the real UI offers.
        $this->selectInterestsViaProfile($token, ['Environment'], $volunteer->civil_id);

        $response = $this->api($token)
            ->getJson('/api/list-volunteer-opportunities/?match_my_interest=true');

        $response->assertOk();

        $ids = array_column($response->json('data') ?? [], 'id');
        $this->assertContains(
            $matching->id,
            $ids,
            'An opportunity sharing the interest the user picked must be returned.'
        );
    }

    public function test_excludes_opportunities_that_do_not_share_the_interest(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();

        $environment = $this->interest('Environment', 'البيئة');
        $health = $this->interest('Health', 'الصحة');

        $matching = $this->opportunity($org->id, 'Beach cleanup');
        $matching->interests()->sync([$environment->id]);

        $other = $this->opportunity($org->id, 'Blood drive');
        $other->interests()->sync([$health->id]);

        $this->selectInterestsViaProfile($token, ['Environment'], $volunteer->civil_id);

        $ids = array_column(
            $this->api($token)->getJson('/api/list-volunteer-opportunities/?match_my_interest=true')->json('data') ?? [],
            'id'
        );

        $this->assertContains($matching->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_matches_via_the_legacy_interests_relation_too(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();

        $interest = $this->interest('Environment', 'البيئة');
        $matching = $this->opportunity($org->id, 'Beach cleanup');
        $matching->interests()->sync([$interest->id]);

        // Older accounts have the legacy relation populated directly.
        $volunteer->interests()->sync([$interest->id]);

        $ids = array_column(
            $this->api($token)->getJson('/api/list-volunteer-opportunities/?match_my_interest=true')->json('data') ?? [],
            'id'
        );

        $this->assertContains($matching->id, $ids);
    }

    public function test_a_user_with_no_interests_gets_an_empty_result(): void
    {
        [$org] = $this->createOrganizationActor();
        [, $token] = $this->createVolunteerActor();

        $interest = $this->interest('Environment', 'البيئة');
        $opportunity = $this->opportunity($org->id, 'Beach cleanup');
        $opportunity->interests()->sync([$interest->id]);

        // Nothing selected on the profile: an empty list is correct here.
        $response = $this->api($token)
            ->getJson('/api/list-volunteer-opportunities/?match_my_interest=true');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_the_filter_is_ignored_for_guests(): void
    {
        [$org] = $this->createOrganizationActor();

        $interest = $this->interest('Environment', 'البيئة');
        $opportunity = $this->opportunity($org->id, 'Beach cleanup');
        $opportunity->interests()->sync([$interest->id]);

        // No token: the filter cannot resolve a user, so it must not blank the list.
        $ids = array_column(
            $this->getJson('/api/list-volunteer-opportunities/?match_my_interest=true')->json('data') ?? [],
            'id'
        );

        $this->assertContains($opportunity->id, $ids);
    }

    public function test_omitting_the_flag_returns_everything(): void
    {
        [$org] = $this->createOrganizationActor();
        [, $token] = $this->createVolunteerActor();

        $a = $this->opportunity($org->id, 'One');
        $b = $this->opportunity($org->id, 'Two');

        $ids = array_column(
            $this->api($token)->getJson('/api/list-volunteer-opportunities/')->json('data') ?? [],
            'id'
        );

        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
    }

    public function test_learn_serve_listing_honours_the_filter_too(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();

        $interest = $this->interest('Environment', 'البيئة');

        $matching = LearnServeOpportunity::query()->create([
            'title_en' => 'Recycling course',
            'title_ar' => 'دورة إعادة التدوير',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'created_by' => $org->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'participants_needed' => 10,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ]);
        $matching->interests()->sync([$interest->id]);

        $this->selectInterestsViaProfile($token, ['Environment'], $volunteer->civil_id);

        $response = $this->api($token)
            ->getJson('/api/learn-serve-opportunities/?match_my_interest=true');

        $response->assertOk();

        $ids = array_column($response->json('data') ?? [], 'id');
        $this->assertContains(
            $matching->id,
            $ids,
            'The learn-serve listing must honour match_my_interest as well.'
        );
    }

    /**
     * Selects interests the way the profile screen does: by MasterChoice id of
     * choice type user_interest.
     *
     * @param  list<string>  $englishLabels
     */
    protected function selectInterestsViaProfile(string $token, array $englishLabels, string $civilId): void
    {
        $ids = MasterChoice::query()
            ->notDeleted()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'user_interest'))
            ->whereIn('value_en', $englishLabels)
            ->pluck('id')
            ->all();

        $this->assertNotEmpty($ids, 'Expected seeded user_interest choices to exist.');

        // civil_id is required by this endpoint; resend the stored value.
        $this->api($token)
            ->patchJson('/api/volunteer-profile/', [
                'interest_ids' => $ids,
                'civil_id' => $civilId,
            ])
            ->assertSuccessful();
    }

    protected function interest(string $en, string $ar): Interest
    {
        return Interest::query()->firstOrCreate(
            ['name_en' => $en],
            ['name_ar' => $ar, 'interest_type' => InterestType::VOLUNTEER]
        );
    }

    protected function opportunity(int $ownerId, string $title): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create([
            'title_en' => $title,
            'title_ar' => $title,
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'created_by' => $ownerId,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'is_public' => true,
            'participants_needed' => 10,
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
