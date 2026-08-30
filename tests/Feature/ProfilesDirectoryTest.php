<?php

namespace Tests\Feature;

use App\Models\MasterChoice;
use App\Models\OrganizationProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsDjangoApiEnvelope;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class ProfilesDirectoryTest extends TestCase
{
    use AssertsDjangoApiEnvelope;
    use CreatesDomainFixtures;
    use RefreshDatabase;

    public function test_all_profiles_groups_volunteers_organizations_and_volunteer_teams(): void
    {
        $this->seed();
        [$volunteer] = $this->createVolunteerActor();
        [$org] = $this->createOrganizationActor();
        $team = $this->createVolunteerTeamActor();

        $response = $this->getJson('/api/all-profiles/?limit=10');
        $this->assertSuccessEnvelope($response, 200, 'Profiles retrieved successfully.');

        $volunteerIds = $this->userIds($response->json('data.volunteer'));
        $orgIds = $this->userIds($response->json('data.organization'));
        $teamIds = $this->userIds($response->json('data.volunteer_team'));

        $this->assertContains($volunteer->id, $volunteerIds);
        $this->assertContains($org->id, $orgIds);
        $this->assertContains($team->id, $teamIds);
        $this->assertNotContains($team->id, $orgIds, 'A volunteer team must not leak into the organization group.');

        $response->assertJsonStructure([
            'data' => [
                'volunteer', 'organization', 'volunteer_team',
                'meta' => ['pagination' => ['volunteer', 'organization', 'volunteer_team']],
            ],
        ]);
    }

    public function test_volunteers_list_is_paginated_and_excludes_organizations(): void
    {
        $this->seed();
        [$volunteer] = $this->createVolunteerActor();
        [$org] = $this->createOrganizationActor();

        $response = $this->getJson('/api/profiles/volunteers/?limit=5');
        $this->assertSuccessEnvelope($response, 200, 'Volunteers retrieved successfully.');

        $ids = $this->userIds($response->json('data'));
        $this->assertContains($volunteer->id, $ids);
        $this->assertNotContains($org->id, $ids);

        $response->assertJsonStructure([
            'meta' => ['pagination' => ['page', 'limit', 'total', 'total_pages']],
        ]);
    }

    public function test_organizations_list_excludes_volunteer_teams(): void
    {
        $this->seed();
        [$org] = $this->createOrganizationActor();
        $team = $this->createVolunteerTeamActor();

        $response = $this->getJson('/api/profiles/organizations/?limit=5');
        $this->assertSuccessEnvelope($response, 200, 'Organizations retrieved successfully.');

        $ids = $this->userIds($response->json('data'));
        $this->assertContains($org->id, $ids);
        $this->assertNotContains($team->id, $ids);
    }

    public function test_volunteer_teams_list_only_returns_volunteer_teams(): void
    {
        $this->seed();
        [$org] = $this->createOrganizationActor();
        $team = $this->createVolunteerTeamActor();

        $response = $this->getJson('/api/profiles/volunteer-teams/?limit=5');
        $this->assertSuccessEnvelope($response, 200, 'Volunteer teams retrieved successfully.');

        $ids = $this->userIds($response->json('data'));
        $this->assertContains($team->id, $ids);
        $this->assertNotContains($org->id, $ids);
    }

    public function test_profiles_lists_filter_by_nickname(): void
    {
        $this->seed();
        [$volunteer] = $this->createVolunteerActor();
        $volunteer->volunteerProfile->update(['nickname' => 'unique_nick_xyz']);

        $response = $this->getJson('/api/profiles/volunteers/?nickname=unique_nick_xyz');
        $this->assertSuccessEnvelope($response, 200);

        $ids = $this->userIds($response->json('data'));
        $this->assertSame([$volunteer->id], $ids);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, int>
     */
    protected function userIds(array $items): array
    {
        return array_map(fn ($item) => $item['user_details']['id'], $items);
    }

    protected function createVolunteerTeamActor()
    {
        [$user] = $this->createOrganizationActor('team.'.uniqid().'@test.com');

        $teamType = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->where('value_en', 'Volunteer Team')
            ->firstOrFail();

        OrganizationProfile::query()
            ->where('user_id', $user->id)
            ->update(['organizer_type_id' => $teamType->id]);

        return $user->fresh();
    }
}
