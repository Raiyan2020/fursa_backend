<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\LearnServeOpportunity;
use App\Models\MasterChoice;
use App\Models\Post;
use App\Models\Sponsor;
use App\Models\VolunteerOpportunity;
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
        foreach (['about', 'privacy', 'terms', 'contact'] as $slug) {
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

        // BE-29: editing a sponsor is a staff action now — it used to be public.
        [, $staffToken] = $this->createStaffActor();
        $this->withToken($staffToken)
            ->patchJson('/api/sponsors/'.$sponsorId.'/', [
                'person_name' => 'Updated Contact',
            ])->assertOk()->assertJsonPath('key', 'success');

        Sponsor::findOrFail($sponsorId)->update(['approval_status' => 'approved']);
        $this->getJson('/api/sponsors/'.$sponsorId.'/')
            ->assertOk()
            ->assertJsonPath('data.person_name', 'Updated Contact');

        $orgType = MasterChoice::query()->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))->firstOrFail();
        $sponsorType = MasterChoice::query()->whereHas('choiceType', fn ($q) => $q->where('name', 'sponsor_type'))->firstOrFail();
        $typeOfSupport = MasterChoice::query()->whereHas('choiceType', fn ($q) => $q->where('name', 'type_of_support'))->firstOrFail();

        $underscoreSponsor = $this->postJson('/api/sponsors/', [
            'org_name' => 'Underscore Sponsor',
            'person_name' => 'Underscore Contact',
            'email' => 'sponsor-underscore@test.com',
            '_org_type_id' => $orgType->id,
            '_sponsor_type_id' => $sponsorType->id,
            '_type_of_support_id' => $typeOfSupport->id,
        ]);
        $this->assertSuccessEnvelope($underscoreSponsor, 201);
        $this->assertDatabaseHas('sponsors', [
            'id' => (int) $underscoreSponsor->json('data.id'),
            'org_type_id' => $orgType->id,
            'sponsor_type_id' => $sponsorType->id,
            'type_of_support_id' => $typeOfSupport->id,
        ]);

        $contact = $this->postJson('/api/contact-us/', [
            'name_en' => 'Cycle User',
            'email' => 'contact-cycle@test.com',
            'message_en' => 'Cycle contact message',
        ]);
        $this->assertSuccessEnvelope($contact, 201);
        $contactId = (int) $contact->json('data.id');

        // BE-29: reading/editing/deleting a submission is staff-only now.
        $this->withToken($staffToken)->patchJson('/api/contact-us/'.$contactId.'/', [
            'message_en' => 'Updated contact message',
        ])->assertOk()->assertJsonPath('key', 'success');
        $this->withToken($staffToken)->deleteJson('/api/contact-us/'.$contactId.'/')->assertNoContent();
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

    public function test_posts_name_filter_matches_author_name_and_nickname(): void
    {
        [$islam, $islamToken] = $this->createVolunteerActor('islam-name-filter@test.com');
        $islam->update(['first_name' => 'Islam', 'last_name' => 'Ghanem']);
        [, $strangerToken] = $this->createVolunteerActor('stranger-name-filter@test.com');

        $islamPost = $this->api($islamToken)->postJson('/api/posts/', [
            'title_en' => 'Islam post',
            'idea_text_en' => 'From islam',
        ]);
        $this->assertSuccessEnvelope($islamPost, 201);
        $islamPostId = (int) $islamPost->json('data.id');

        $strangerPost = $this->api($strangerToken)->postJson('/api/posts/', [
            'title_en' => 'Stranger post',
            'idea_text_en' => 'Not islam',
        ]);
        $this->assertSuccessEnvelope($strangerPost, 201);
        $strangerPostId = (int) $strangerPost->json('data.id');

        $filtered = $this->getJson('/api/posts/?name=isl');
        $this->assertSuccessEnvelope($filtered);
        $ids = collect($filtered->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($islamPostId));
        $this->assertFalse($ids->contains($strangerPostId));
    }

    public function test_list_all_opportunities_organized_events_returns_only_events(): void
    {
        [, $organizationToken] = $this->createOrganizationActor('organized.events.org@test.com');
        [, $volunteerToken] = $this->createVolunteerActor('organized.events.vol@test.com');

        $eventType = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'event_type'))
            ->firstOrFail();

        $dates = [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
        ];

        $opportunity = $this->api($organizationToken)->postJson('/api/volunteer-opportunities/', array_merge($dates, [
            'title_en' => 'Not An Event Opportunity',
            'title_ar' => 'فرصة ليست فعالية',
            'description_en' => 'Should not appear in organized_events',
            'description_ar' => 'لا يجب أن تظهر',
            'participants_needed' => 10,
            'is_public' => true,
            'volunteer_category' => 'environmental',
        ]));
        $this->assertSuccessEnvelope($opportunity, 201);
        $opportunityId = (int) $opportunity->json('data.id');

        $event = $this->api($organizationToken)->postJson('/api/events/', [
            'title_en' => 'Real Organized Event',
            'title_ar' => 'فعالية حقيقية',
            'description_en' => 'Organized event description',
            'description_ar' => 'وصف الفعالية',
            'event_type_id' => $eventType->id,
            'start_date' => $dates['start_date'],
            'end_date' => $dates['start_date'],
            'due_date' => now()->addDays(4)->toDateTimeString(),
            'registration_required' => true,
            'participants_needed' => 10,
        ]);
        $this->assertSuccessEnvelope($event, 201);
        $eventId = (int) $event->json('data.id');

        VolunteerOpportunity::query()->whereKey($opportunityId)->update(['approval_status' => 'approved']);
        Event::query()->whereKey($eventId)->update(['approval_status' => 'approved']);

        // `organized_events` means "events I organized", so it has to be queried by
        // the organization that created it — a volunteer organizes none, which is
        // why this returned an empty list when asked with the volunteer token.
        $list = $this->api($organizationToken)->getJson('/api/list-all-opportunities/?filter_type=organized_events&page=1&limit=9');
        $this->assertSuccessEnvelope($list);

        $items = $list->json('data');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertSame('event', $item['opportunity_type'] ?? null);
        }

        $ids = array_column($items, 'id');
        $this->assertContains($eventId, $ids);
        $this->assertNotContains('volunteer_opportunity', array_column($items, 'opportunity_type'));

        $this->getJson('/api/events/'.$eventId.'/')
            ->assertOk()
            ->assertJsonPath('key', 'success')
            ->assertJsonPath('data.opportunity_type', 'event');
    }

    public function test_list_all_opportunities_opportunity_type_filter_accepts_both_vocabularies(): void
    {
        [$org, $organizationToken] = $this->createOrganizationActor('type-filter-org@test.com');

        $volunteerOpportunity = $this->api($organizationToken)->postJson('/api/volunteer-opportunities/', [
            'title_en' => 'Type Filter Volunteer Opportunity',
            'title_ar' => 'فرصة تطوع',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'participants_needed' => 8,
            'is_public' => true,
            'volunteer_category' => 'environmental',
        ]);
        $this->assertSuccessEnvelope($volunteerOpportunity, 201);
        $volunteerId = (int) $volunteerOpportunity->json('data.id');
        VolunteerOpportunity::query()->whereKey($volunteerId)->update(['approval_status' => 'approved']);

        $learnOpportunity = $this->api($organizationToken)->postJson('/api/learn-serve-opportunities/', [
            ...$this->learningChoicePayload(),
            'title_en' => 'Type Filter Learn Serve Opportunity',
            'title_ar' => 'فرصة تعلم وخدمة',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
            'participants_needed' => 8,
        ]);
        $this->assertSuccessEnvelope($learnOpportunity, 201);
        $learnId = (int) $learnOpportunity->json('data.id');
        LearnServeOpportunity::query()->whereKey($learnId)->update(['approval_status' => 'approved']);

        $base = '/api/list-all-opportunities/?filter_type=organized&user_id='.$org->id;

        foreach (['learn', 'learn_serve_opportunity'] as $learnValue) {
            $types = collect($this->getJson($base.'&opportunity_type='.$learnValue)->json('data'))->pluck('opportunity_type');
            $this->assertTrue($types->contains('learn_serve_opportunity'), "opportunity_type={$learnValue} must include the learn-serve record.");
            $this->assertFalse($types->contains('volunteer_opportunity'), "opportunity_type={$learnValue} must exclude the volunteer record.");
        }

        foreach (['volunteer', 'volunteer_opportunity'] as $volunteerValue) {
            $types = collect($this->getJson($base.'&opportunity_type='.$volunteerValue)->json('data'))->pluck('opportunity_type');
            $this->assertTrue($types->contains('volunteer_opportunity'), "opportunity_type={$volunteerValue} must include the volunteer record.");
            $this->assertFalse($types->contains('learn_serve_opportunity'), "opportunity_type={$volunteerValue} must exclude the learn-serve record.");
        }
    }

    public function test_relationship_tags_mark_the_organizer_consistently_across_event_and_list_resources(): void
    {
        [$org, $organizationToken] = $this->createOrganizationActor('relationship-tags-org@test.com');

        $eventType = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'event_type'))
            ->firstOrFail();

        $event = $this->api($organizationToken)->postJson('/api/events/', [
            'title_en' => 'Relationship Tags Event',
            'title_ar' => 'فعالية',
            'description_en' => 'Relationship tags description',
            'description_ar' => 'وصف الفعالية',
            'event_type_id' => $eventType->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'due_date' => now()->addDays(2)->toDateTimeString(),
            'registration_required' => true,
            'participants_needed' => 10,
        ]);
        $this->assertSuccessEnvelope($event, 201);
        $eventId = (int) $event->json('data.id');
        Event::query()->whereKey($eventId)->update(['approval_status' => 'approved']);

        $this->api($organizationToken)->getJson("/api/events/{$eventId}/")
            ->assertOk()
            ->assertJsonPath('data.relationship_tags', ['organizer']);

        $eventList = $this->api($organizationToken)
            ->getJson('/api/list-all-opportunities/?filter_type=organized_events&user_id='.$org->id);
        $this->assertSuccessEnvelope($eventList);
        $eventItem = collect($eventList->json('data'))->firstWhere('id', $eventId);
        $this->assertSame(['organizer'], $eventItem['relationship_tags']);

        $volunteerOpportunity = $this->api($organizationToken)->postJson('/api/volunteer-opportunities/', [
            'title_en' => 'Relationship Tags Volunteer Opportunity',
            'title_ar' => 'فرصة تطوع',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'participants_needed' => 8,
            'is_public' => true,
            'volunteer_category' => 'environmental',
        ]);
        $this->assertSuccessEnvelope($volunteerOpportunity, 201);
        $volunteerId = (int) $volunteerOpportunity->json('data.id');
        VolunteerOpportunity::query()->whereKey($volunteerId)->update(['approval_status' => 'approved']);

        $learnOpportunity = $this->api($organizationToken)->postJson('/api/learn-serve-opportunities/', [
            ...$this->learningChoicePayload(),
            'title_en' => 'Relationship Tags Learn Serve Opportunity',
            'title_ar' => 'فرصة تعلم وخدمة',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
            'participants_needed' => 8,
        ]);
        $this->assertSuccessEnvelope($learnOpportunity, 201);
        $learnId = (int) $learnOpportunity->json('data.id');
        LearnServeOpportunity::query()->whereKey($learnId)->update(['approval_status' => 'approved']);

        $list = $this->api($organizationToken)
            ->getJson('/api/list-all-opportunities/?filter_type=organized&user_id='.$org->id);
        $this->assertSuccessEnvelope($list);
        $items = collect($list->json('data'));

        $volunteerItem = $items->first(fn ($item) => $item['id'] === $volunteerId && $item['opportunity_type'] === 'volunteer_opportunity');
        $this->assertSame(['organizer'], $volunteerItem['relationship_tags']);

        $learnItem = $items->first(fn ($item) => $item['id'] === $learnId && $item['opportunity_type'] === 'learn_serve_opportunity');
        $this->assertSame(['organizer'], $learnItem['relationship_tags']);
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
