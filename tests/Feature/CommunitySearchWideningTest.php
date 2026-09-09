<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-33 — `search` matched post text only, and `search` + `name` intersected.
 */
class CommunitySearchWideningTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private array $ids = [];

    private function seedPosts(): void
    {
        [$islam] = $this->createVolunteerActor('islam.author@test.com');
        $islam->update(['first_name' => 'islam', 'last_name' => 'Ghareeb']);
        $islam->volunteerProfile->update(['nickname' => 'islamGH']);

        [$fahmy] = $this->createVolunteerActor('fahmy.author@test.com');
        $fahmy->update(['first_name' => 'Mohamed', 'last_name' => 'Fahmy']);
        $fahmy->volunteerProfile->update(['nickname' => 'fahmy123']);

        // Post whose TEXT matches "ssss" but whose author does not.
        $this->ids['text'] = Post::query()->create([
            'user_id' => $fahmy->id,
            'idea_text_en' => 'ssss body text',
            'proposing_idea' => true,
        ])->id;

        // Post whose AUTHOR matches "islam" but whose text does not.
        $this->ids['author'] = Post::query()->create([
            'user_id' => $islam->id,
            'idea_text_en' => 'completely unrelated wording',
            'proposing_idea' => true,
        ])->id;
    }

    private function search(string $query): array
    {
        $response = $this->getJson("/api/posts/?{$query}")->assertStatus(200);
        $items = $response->json('data');

        return array_column(is_array($items) ? $items : [], 'id');
    }

    public function test_search_matches_the_post_text(): void
    {
        $this->seedPosts();

        $this->assertSame([$this->ids['text']], $this->search('search=ssss'));
    }

    public function test_search_now_also_matches_the_author(): void
    {
        $this->seedPosts();

        // This returned 0 before: `search` never looked at the author.
        $this->assertContains($this->ids['author'], $this->search('search=islam'));
    }

    public function test_search_matches_the_author_nickname_too(): void
    {
        $this->seedPosts();

        $this->assertContains($this->ids['text'], $this->search('search=fahmy123'));
    }

    public function test_name_still_filters_by_author_only(): void
    {
        $this->seedPosts();

        // `name` is a deliberate author-only filter and must not have widened.
        $byName = $this->search('name=islam');
        $this->assertContains($this->ids['author'], $byName);
        $this->assertNotContains($this->ids['text'], $byName);
    }

    public function test_search_and_name_still_intersect_deliberately(): void
    {
        $this->seedPosts();

        // Both params together remain an AND — that is the documented behaviour of
        // combining a text search with an author filter. The fix was to make one
        // term reach both sides, not to change how two params combine.
        $this->assertSame([], $this->search('search=ssss&name=islam'));
    }
}
