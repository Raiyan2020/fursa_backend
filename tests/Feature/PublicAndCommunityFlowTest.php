<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Sponsor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsDjangoApiEnvelope;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class PublicAndCommunityFlowTest extends TestCase
{
    use AssertsDjangoApiEnvelope;
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_home_cms_sponsor_and_contact_flows(): void
    {
        $home = $this->getJson('/api/home/');
        $this->assertSuccessEnvelope($home);
        $home->assertJsonStructure(['data' => [
            'hero', 'statistics', 'sponsors', 'why_fursa', 'opportunities',
            'community', 'learn_share', 'share_idea', 'events', 'achievements', 'footer',
        ]]);

        $pages = $this->getJson('/api/pages/');
        $this->assertSuccessEnvelope($pages);
        foreach (['about', 'privacy', 'terms'] as $slug) {
            $this->getJson('/api/pages/'.$slug.'/')
                ->assertOk()
                ->assertJsonPath('key', 'success')
                ->assertJsonPath('data.slug', $slug);
        }

        $sponsor = $this->postJson('/api/sponsors/', [
            'org_name' => 'Cycle Sponsor',
            'person_name' => 'Sponsor Contact',
            'email' => 'sponsor-cycle@test.com',
            'sponsorship_details' => 'Testing sponsor flow',
        ]);
        $this->assertSuccessEnvelope($sponsor, 201);
        $sponsorId = (int) $sponsor->json('data.id');
        $this->assertDatabaseHas('sponsors', [
            'id' => $sponsorId,
            'email' => 'sponsor-cycle@test.com',
            'approval_status' => 'pending',
        ]);

        $this->patchJson('/api/sponsors/'.$sponsorId.'/', [
            'person_name' => 'Updated Contact',
        ])->assertOk()->assertJsonPath('key', 'success');

        Sponsor::findOrFail($sponsorId)->update(['approval_status' => 'approved']);
        $this->getJson('/api/sponsors/'.$sponsorId.'/')
            ->assertOk()
            ->assertJsonPath('data.person_name', 'Updated Contact');

        $contact = $this->postJson('/api/contact-us/', [
            'name_en' => 'Cycle User',
            'email' => 'contact-cycle@test.com',
            'message_en' => 'Cycle contact message',
        ]);
        $this->assertSuccessEnvelope($contact, 201);
        $contactId = (int) $contact->json('data.id');

        $this->patchJson('/api/contact-us/'.$contactId.'/', [
            'message_en' => 'Updated contact message',
        ])->assertOk()->assertJsonPath('key', 'success');
        $this->deleteJson('/api/contact-us/'.$contactId.'/')->assertNoContent();
    }

    public function test_community_create_update_like_and_deleted_target_flow(): void
    {
        [, $token] = $this->createVolunteerActor();

        $create = $this->api($token)->postJson('/api/posts/', [
            'title_en' => 'Cycle community post',
            'idea_text_en' => 'A safe idea for the community',
            'tags' => ['testing', 'cycle'],
        ]);
        $this->assertSuccessEnvelope($create, 201);
        $postId = (int) $create->json('data.id');

        $this->getJson('/api/posts/'.$postId.'/')
            ->assertOk()
            ->assertJsonPath('key', 'success');

        $this->api($token)->patchJson('/api/posts/'.$postId.'/', [
            'idea_text_en' => 'Updated safe community idea',
        ])->assertOk()->assertJsonPath('key', 'success');

        $this->api($token)->postJson('/api/likes/toggle/', [
            'post_id' => $postId,
        ])->assertOk()
            ->assertJsonPath('key', 'success')
            ->assertJsonPath('data.is_liked', true);

        Post::findOrFail($postId)->softDeleteFlags();
        $this->api($token)->postJson('/api/likes/toggle/', [
            'post_id' => $postId,
        ])->assertNotFound()->assertJsonPath('key', 'fail');
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
