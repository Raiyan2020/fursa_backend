<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\Event;
use App\Models\LearnServeOpportunity;
use App\Models\MasterChoice;
use App\Models\OpportunityImage;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-76 — only one announcement image (is_after_completed=false) per
 * opportunity/event; a new upload replaces the old one rather than
 * accumulating. The after-completion gallery stays uncapped.
 */
class AnnouncementImageCapTest extends TestCase
{
    use CreatesDomainFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed();
    }

    private function api(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function choice(string $type, ?string $value = null): int
    {
        return MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', $type))
            ->when($value, fn ($q) => $q->where('value_en', $value))
            ->firstOrFail()->id;
    }

    public function test_volunteer_opportunity_update_replaces_the_announcement_image(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        $opportunity = VolunteerOpportunity::create([
            'created_by' => $owner->id,
            'title_en' => 'Cleanup', 'title_ar' => 'تنظيف',
            'description_en' => 'd', 'description_ar' => 'و',
            'start_date' => now()->addDays(2), 'end_date' => now()->addDays(3),
            'participants_needed' => 5,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
        ]);
        $existing = OpportunityImage::query()->create([
            'volunteer_opportunity_id' => $opportunity->id,
            'image' => 'opportunity-images/old.jpg',
            'is_after_completed' => false,
        ]);

        $this->api($token)->post("/api/volunteer-opportunities/{$opportunity->id}/", [
            '_method' => 'PATCH',
            'new_opportunity_images_0' => UploadedFile::fake()->image('new.jpg'),
        ])->assertOk();

        $images = OpportunityImage::query()->notDeleted()
            ->where('volunteer_opportunity_id', $opportunity->id)
            ->where('is_after_completed', false)
            ->get();

        $this->assertCount(1, $images);
        $this->assertNotSame($existing->id, $images->first()->id);
        $this->assertTrue($existing->fresh()->is_deleted);
    }

    public function test_learn_serve_create_with_two_announcement_images_keeps_only_the_last(): void
    {
        [, $token] = $this->createOrganizationActor();

        $response = $this->api($token)->post('/api/learn-serve-opportunities/', [
            'title_en' => 'Course', 'title_ar' => 'دورة',
            'description_en' => 'd', 'description_ar' => 'و',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'participants_needed' => 10,
            'format_id' => $this->choice('learn_serve_format'),
            'learning_type_id' => $this->choice('learning_type', 'Class/Workshop'),
            'new_opportunity_images_0' => UploadedFile::fake()->image('first.jpg'),
            'new_opportunity_images_1' => UploadedFile::fake()->image('second.jpg'),
        ])->assertCreated();

        $opportunityId = $response->json('data.id');
        $images = OpportunityImage::query()->notDeleted()
            ->where('learn_serve_opportunity_id', $opportunityId)
            ->where('is_after_completed', false)
            ->get();

        $this->assertCount(1, $images);
    }

    public function test_after_completion_gallery_stays_uncapped(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        $opportunity = VolunteerOpportunity::create([
            'created_by' => $owner->id,
            'title_en' => 'Cleanup', 'title_ar' => 'تنظيف',
            'description_en' => 'd', 'description_ar' => 'و',
            'start_date' => now()->subDays(2), 'end_date' => now()->subDay(),
            'participants_needed' => 5,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
        ]);

        $this->api($token)->post("/api/volunteer-opportunities/{$opportunity->id}/update_images/", [
            'opportunity_images_0' => UploadedFile::fake()->image('a.jpg'),
            'opportunity_images_1' => UploadedFile::fake()->image('b.jpg'),
        ])->assertOk();

        $images = OpportunityImage::query()->notDeleted()
            ->where('volunteer_opportunity_id', $opportunity->id)
            ->where('is_after_completed', true)
            ->get();

        $this->assertCount(2, $images);
    }

    public function test_event_update_replaces_the_image(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        $event = Event::create([
            'created_by' => $owner->organizationProfile->id,
            'title_en' => 'Fair', 'title_ar' => 'معرض',
            'description_en' => 'd', 'description_ar' => 'و',
            'start_date' => now()->addDays(2), 'end_date' => now()->addDays(3),
            'participants_needed' => 10,
            'approval_status' => ApprovalStatus::APPROVED,
            'event_status' => OpportunityStatus::UPCOMING,
        ]);
        $existing = \App\Models\EventImage::query()->create([
            'event_id' => $event->id,
            'image' => 'events/old.jpg',
        ]);

        $this->api($token)->post("/api/events/{$event->id}/", [
            '_method' => 'PATCH',
            'images' => [UploadedFile::fake()->image('new.jpg')],
        ])->assertOk();

        $images = \App\Models\EventImage::query()->notDeleted()->where('event_id', $event->id)->get();
        $this->assertCount(1, $images);
        $this->assertNotSame($existing->id, $images->first()->id);
        $this->assertTrue($existing->fresh()->is_deleted);
    }

    public function test_create_with_three_announcement_images_keeps_only_one(): void
    {
        [$owner, $token] = $this->createOrganizationActor();

        $response = $this->api($token)->post('/api/volunteer-opportunities/', [
            'title_en' => 'Cleanup', 'title_ar' => 'تنظيف',
            'description_en' => 'd', 'description_ar' => 'و',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'participants_needed' => 5,
            'volunteer_category' => 'charity',
            'new_opportunity_images_0' => UploadedFile::fake()->image('a.jpg'),
            'new_opportunity_images_1' => UploadedFile::fake()->image('b.jpg'),
            'new_opportunity_images_2' => UploadedFile::fake()->image('c.jpg'),
        ])->assertCreated();

        $images = OpportunityImage::query()->notDeleted()
            ->where('volunteer_opportunity_id', $response->json('data.id'))
            ->where('is_after_completed', false)
            ->get();

        $this->assertCount(1, $images);
    }

    /**
     * A legacy record from before this cap existed can still hold several
     * announcement images. Saving it for an unrelated change (no new image
     * upload) must not silently prune the extras — the delete-on-replace
     * logic only fires when a NEW announcement image actually arrives.
     */
    public function test_a_legacy_record_with_several_announcement_images_is_not_pruned_on_an_unrelated_save(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        $opportunity = VolunteerOpportunity::create([
            'created_by' => $owner->id,
            'title_en' => 'Cleanup', 'title_ar' => 'تنظيف',
            'description_en' => 'd', 'description_ar' => 'و',
            'start_date' => now()->addDays(2), 'end_date' => now()->addDays(3),
            'participants_needed' => 5,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
        ]);
        collect(range(1, 4))->each(fn ($i) => OpportunityImage::query()->create([
            'volunteer_opportunity_id' => $opportunity->id,
            'image' => "opportunity-images/legacy{$i}.jpg",
            'is_after_completed' => false,
        ]));

        $this->api($token)->post("/api/volunteer-opportunities/{$opportunity->id}/", [
            '_method' => 'PATCH',
            'title_en' => 'Cleanup (renamed)',
        ])->assertOk();

        $images = OpportunityImage::query()->notDeleted()
            ->where('volunteer_opportunity_id', $opportunity->id)
            ->where('is_after_completed', false)
            ->get();

        $this->assertCount(4, $images);
    }
}
