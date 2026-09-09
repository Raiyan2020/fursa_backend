<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\InterestType;
use App\Enums\OpportunityStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventTimeSlot;
use App\Models\Interest;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\MasterChoice;
use App\Models\MyCalendar;
use App\Models\Post;
use App\Models\Reply;
use App\Models\ScanPermission;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use App\Services\Certificate\VolunteerCertificateRenderer;
use App\Services\Certificate\VolunteerCertificateService;
use App\Services\Opportunity\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class BackendMissingItemsTest extends TestCase
{
    use CreatesDomainFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
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
        return MasterChoice::whereHas('choiceType', fn ($q) => $q->where('name', $type))
            ->when($value, fn ($q) => $q->where('value_en', $value))->firstOrFail()->id;
    }

    private function item(string $class, $owner, array $extra = [])
    {
        return $class::create($extra + [
            'created_by' => $class === Event::class ? $owner->organizationProfile->id : $owner->id,
            'title_en' => 'Fixture', 'title_ar' => 'فرصة', 'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->addDays(3), 'end_date' => now()->addDays(4),
            'approval_status' => ApprovalStatus::APPROVED, 'participants_needed' => 10,
            $class === Event::class ? 'event_status' : 'opportunity_status' => OpportunityStatus::UPCOMING,
            ...($class === VolunteerOpportunity::class ? ['is_public' => true] : []),
        ]);
    }

    private function payload(string $class): array
    {
        return [
            'title_en' => 'Reposted', 'title_ar' => 'جديد', 'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->addDays(6)->toDateString(), 'end_date' => now()->addDays(7)->toDateString(),
            'participants_needed' => 10,
            ...match ($class) {
                Event::class => ['event_type_id' => $this->choice('event_type')],
                LearnServeOpportunity::class => ['learning_type_id' => $this->choice('learning_type', 'Course'),
                    'format_id' => $this->choice('learn_serve_format'), 'certificate_type_id' => $this->choice('learn_serve_certificate_type')],
                default => ['volunteer_category' => 'charity'],
            },
        ];
    }

    public function test_registration_rejects_hidden_closed_full_and_invalid_lifecycle_items(): void
    {
        [$owner] = $this->createOrganizationActor();
        [$volunteer, $token] = $this->createVolunteerActor();
        foreach ([VolunteerOpportunity::class => 'volunteer-opportunity', LearnServeOpportunity::class => 'learn-serve-opportunity', Event::class => 'event'] as $class => $path) {
            $item = $this->item($class, $owner);
            $body = [$class === Event::class ? 'event' : 'opportunity_id' => $item->id];
            foreach ([ApprovalStatus::PENDING, ApprovalStatus::REJECTED] as $status) {
                $item->update(['approval_status' => $status]);
                $this->api($token)->postJson("/api/$path-registrations/", $body)->assertNotFound();
            }
            $item->update(['approval_status' => ApprovalStatus::APPROVED, 'is_registration_closed' => true]);
            $this->api($token)->postJson("/api/$path-registrations/", $body)->assertStatus(400);
            $item->update(['is_registration_closed' => false, $class === Event::class ? 'event_status' : 'opportunity_status' => OpportunityStatus::CANCELLED]);
            $this->api($token)->postJson("/api/$path-registrations/", $body)->assertStatus(400);
            $item->update([$class === Event::class ? 'event_status' : 'opportunity_status' => OpportunityStatus::UPCOMING]);
            $this->api($token)->postJson("/api/$path-registrations/", $body)->assertCreated();
            $item->update(['participants_needed' => 1]);
            [, $otherToken] = $this->createVolunteerActor();
            $this->api($otherToken)->postJson("/api/$path-registrations/", $body)->assertStatus(400);
            if ($class === VolunteerOpportunity::class) {
                $item->update(['is_public' => false]);
                $this->api($token)->postJson("/api/$path-registrations/", $body)->assertNotFound();
            }
        }
    }

    public function test_event_registration_and_scan_permission_ownership_and_slot_scoping(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$user, $token] = $this->createVolunteerActor();
        [, $stranger] = $this->createVolunteerActor();
        $event = $this->item(Event::class, $owner);
        $other = $this->item(Event::class, $owner);
        $slot = EventTimeSlot::create(['event_id' => $other->id, 'date' => now()->addDays(3), 'start_time' => '09:00', 'end_time' => '12:00']);
        $this->api($token)->postJson('/api/event-registrations/', ['event' => $event->id, 'time_slot_id' => $slot->id])->assertUnprocessable();
        $reg = EventRegistration::create(['event_id' => $event->id, 'user_id' => $user->id, 'registration_date' => now()]);
        $this->api($stranger)->getJson("/api/event-registrations/$reg->id/")->assertForbidden();
        $this->api($token)->getJson("/api/event-registrations/$reg->id/")->assertOk();
        $this->api($ownerToken)->getJson("/api/event-registrations/$reg->id/")->assertOk();
        $this->api($ownerToken)->patchJson("/api/event-registrations/$reg->id/", ['time_slot_id' => $slot->id])->assertUnprocessable();
        $this->api($stranger)->getJson("/api/scan-permissions/list/?event_id=$event->id")->assertForbidden();
        $this->api($ownerToken)->getJson("/api/scan-permissions/list/?event_id=$event->id")->assertOk();
    }

    public function test_calendar_routes_scope_saved_items_and_upload_ics(): void
    {
        [$owner] = $this->createOrganizationActor();
        [, $token] = $this->createVolunteerActor();
        [, $stranger] = $this->createVolunteerActor();
        $event = $this->item(Event::class, $owner);
        $response = $this->api($token)->postJson('/api/my-calendar/save/', ['event_id' => $event->id])->assertCreated();
        $id = MyCalendar::firstOrFail()->id;
        $this->api($stranger)->patchJson("/api/my-calendar/$id/", ['is_saved' => false])->assertNotFound();
        $this->api($stranger)->deleteJson("/api/my-calendar/$id/")->assertNotFound();
        $this->api($token)->getJson('/api/my-calendar/')->assertOk();
        $this->api($token)->patchJson("/api/my-calendar/$id/", ['is_saved' => false])->assertOk();
        $this->api($token)->postJson('/api/upload-ics/', ['ics_file' => UploadedFile::fake()->createWithContent('calendar.ics', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nEND:VCALENDAR")])->assertCreated()->assertJsonStructure(['data' => ['file_url', 'webcal_url']]);
        $this->api($token)->deleteJson("/api/my-calendar/$id/")->assertNoContent();
    }

    public function test_media_keep_sets_remove_one_of_three_and_cannot_take_foreign_images(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        $event = $this->item(Event::class, $owner);
        $post = Post::create(['user_id' => $owner->id, 'idea_text_en' => 'Text', 'is_displayed' => true]);
        $reply = Reply::create(['user_id' => $owner->id, 'post_id' => $post->id, 'text_en' => 'Reply', 'is_displayed' => true]);
        foreach (['events' => $event, 'posts' => $post, 'replies' => $reply] as $path => $parent) {
            $ids = [];
            foreach (range(1, 3) as $i) {
                $ids[] = $parent->images()->create(['image' => "$path/$i.png"])->id;
            }
            $this->api($token)->patchJson("/api/$path/$parent->id/", ['existing_image_ids' => [$ids[0], $ids[2]]])->assertOk();
            $this->assertSame([$ids[0], $ids[2]], $parent->images()->notDeleted()->pluck('id')->all());
            $this->api($token)->patchJson("/api/$path/$parent->id/", ['existing_image_ids' => [999999]])->assertUnprocessable();
            $this->assertSame(2, $parent->images()->notDeleted()->count());
            $this->api($token)->patchJson("/api/$path/$parent->id/", ['existing_image_ids' => []])->assertOk();
            $this->assertSame(0, $parent->images()->notDeleted()->count());
        }
    }

    public function test_sponsors_can_be_removed_then_restored_with_position_for_all_types(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        [$sponsor] = $this->createOrganizationActor();
        [, $stranger] = $this->createOrganizationActor();
        foreach (['volunteer-opportunities' => VolunteerOpportunity::class, 'learn-serve-opportunities' => LearnServeOpportunity::class, 'events' => Event::class] as $path => $class) {
            $item = $this->item($class, $owner);
            $body = ['organization_id' => $sponsor->organizationProfile->id, 'position' => 4];
            $url = "/api/$path/$item->id/sponsors/";
            $this->api($stranger)->postJson($url, $body)->assertStatus($class === Event::class ? 403 : 404);
            $id = $this->api($token)->postJson($url, $body)->assertCreated()->json('data.id');
            $this->api($token)->deleteJson($url.$id.'/')->assertOk();
            $this->api($token)->postJson($url, $body)->assertCreated()->assertJsonPath('data.id', $id)->assertJsonPath('data.position', 4);
            $this->assertSame(1, $item->sponsorImages()->notDeleted()->count());
        }
    }

    public function test_republish_copies_replaces_removes_and_does_not_mutate_source(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        [, $stranger] = $this->createOrganizationActor();
        foreach (['volunteer-opportunities' => VolunteerOpportunity::class, 'learn-serve-opportunities' => LearnServeOpportunity::class, 'events' => Event::class] as $path => $class) {
            $source = $this->item($class, $owner, ['license_image' => 'license.pdf']);
            Storage::disk('public')->put('license.pdf', 'original license');
            Storage::disk('public')->put('gallery.png', 'original gallery');
            $image = $source->images()->create(['image' => 'gallery.png']);
            $before = $source->fresh()->getRawOriginal();
            $url = $class === Event::class ? "/api/event/republish/$source->id" : "/api/$path/";
            $payload = $this->payload($class) + ['existing_image_ids' => [$image->id]];
            if ($class !== Event::class) {
                $payload['opportunity_id'] = $source->id;
            }
            $this->api($stranger)->postJson($url, $payload)->assertStatus($class === Event::class ? 403 : 404);
            foreach (['copy', 'remove', 'replace'] as $mode) {
                $body = $payload;
                if ($mode === 'remove') {
                    $body['license_image_removed'] = true;
                }
                if ($mode === 'replace') {
                    $body['license_image'] = UploadedFile::fake()->createWithContent('replacement.pdf', '%PDF replacement');
                }
                $newId = $this->api($token)->postJson($url, $body)->assertCreated()->json('data.id');
                $new = $class::findOrFail($newId);
                $this->assertNotSame($source->id, $new->id);
                $this->assertSame(ApprovalStatus::PENDING, $new->approval_status);
                $this->assertSame('original gallery', Storage::disk('public')->get($new->images()->firstOrFail()->image));
                if ($mode === 'remove') {
                    $this->assertNull($new->license_image);
                } else {
                    $this->assertSame($mode === 'copy' ? 'original license' : '%PDF replacement', Storage::disk('public')->get($new->license_image));
                }
                $this->assertSame($before, $source->fresh()->getRawOriginal());
            }
        }
    }

    public function test_exports_return_real_filtered_xlsx_files_and_reject_nonowners(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        [$user, $stranger] = $this->createVolunteerActor();
        $event = $this->item(Event::class, $owner);
        $learn = $this->item(LearnServeOpportunity::class, $owner);
        EventRegistration::create(['event_id' => $event->id, 'user_id' => $user->id, 'registration_date' => now(), 'registration_status' => ApprovalStatus::APPROVED]);
        LearnServeOpportunityRegistration::create(['opportunity_id' => $learn->id, 'user_id' => $user->id, 'registration_date' => now(), 'status' => ApprovalStatus::APPROVED]);
        ScanPermission::create(['event_id' => $event->id, 'user_id' => $user->id, 'is_allowed' => true]);
        foreach (["event-registrations/by-event/$event->id/?status=approved", "learn-serve-opportunities/$learn->id/registrations/?status=approved", "scan-permissions/list/?event_id=$event->id"] as $path) {
            $url = "/api/$path&download=true";
            $this->api($stranger)->getJson($url)->assertForbidden();
            $download = $this->api($token)->getJson($url)->assertOk()->json('data.downloadUrl');
            $this->assertStringEndsWith('.xlsx', $download);
            $file = 'exports/'.explode('/exports/', $download)[1];
            $bytes = Storage::disk('public')->get($file);
            $this->assertStringStartsWith('PK', $bytes);
            $zip = new \ZipArchive;
            $zip->open(Storage::disk('public')->path($file));
            $this->assertStringContainsString($user->email, $zip->getFromName('xl/worksheets/sheet1.xml'));
            $zip->close();
            $this->api($token)->getJson($url.'&mark_attendance=true')->assertUnprocessable();
        }
    }

    public function test_participation_matrix_and_required_learning_choices(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        foreach (['Paid Event' => [true, true], 'Free Event' => [false, false], 'Free Event (Registration Required)' => [true, false]] as $value => [$required, $paid]) {
            $body = $this->payload(Event::class) + ['participation_type_id' => $this->choice('event_participation_type', $value), 'registration_fee' => $paid ? 20 : 0];
            $body['participants_needed'] = $required ? 10 : 0;
            $this->api($token)->postJson('/api/events/', $body)->assertCreated()->assertJsonPath('data.registration_required', $required)->assertJsonPath('data.paid_registration', $paid);
            $this->api($token)->postJson('/api/events/', $body + ['paid_registration' => ! $paid])->assertUnprocessable();
        }
        $this->api($token)->postJson('/api/learn-serve-opportunities/', [])->assertUnprocessable()->assertJsonStructure(['response_status' => ['validation_errors' => ['learning_type_id', 'format_id']]]);
        $body = $this->payload(LearnServeOpportunity::class);
        unset($body['certificate_type_id']);
        $this->api($token)->postJson('/api/learn-serve-opportunities/', $body)->assertUnprocessable();
    }

    public function test_correcting_hours_reissues_certificate_without_double_counting(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $item = $this->item(VolunteerOpportunity::class, $owner, [
            'start_date' => now()->subDays(3), 'end_date' => now()->subDay(),
            'opportunity_status' => OpportunityStatus::COMPLETED,
        ]);
        $reg = VolunteerOpportunityRegistration::create(['opportunity_id' => $item->id, 'user_id' => $volunteer->id, 'status' => ApprovalStatus::APPROVED]);
        $attendance = AttendanceService::record($reg, $item, now()->subDay()->toDateString(), 1, 'manual');
        VolunteerCertificateService::issueEligible($item->id);
        $this->assertTrue($reg->fresh()->is_certified);
        $this->api($token)->patchJson("/api/volunteer-attendance/$attendance->id/hours/", ['total_hours' => 24])->assertOk();
        $this->assertTrue($reg->fresh()->is_certified);
        $this->assertSame(24.0, (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours);
        $this->assertSame(1, $volunteer->volunteerProfile->fresh()->total_certificates);
        $this->assertSame(24.0, VolunteerCertificateRenderer::data($reg->fresh())['total_hours']);
        $bytes = Storage::disk('public')->get($reg->fresh()->certificate_image);
        $this->assertStringContainsString('24', $bytes);
        $this->api($token)->patchJson("/api/volunteer-attendance/$attendance->id/hours/", ['total_hours' => 24])->assertOk();
        $this->assertSame($bytes, Storage::disk('public')->get($reg->fresh()->certificate_image));
        $this->assertSame(24.0, (float) $volunteer->volunteerProfile->fresh()->total_volunteer_hours);
        $this->assertSame(0, VolunteerCertificateService::issueEligible($item->id));
    }

    public function test_event_choice_updates_and_external_registration_cannot_drift(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        [, $volunteerToken] = $this->createVolunteerActor();
        $event = $this->item(Event::class, $owner, [
            'participation_type_id' => $this->choice('event_participation_type', 'Paid Event'),
            'registration_required' => true, 'paid_registration' => true, 'registration_fee' => 10,
        ]);
        $this->api($token)->patchJson("/api/events/$event->id/", ['paid_registration' => false])->assertUnprocessable();
        $this->api($token)->patchJson("/api/events/$event->id/", [
            'participation_type_id' => $this->choice('event_participation_type', 'Free Event'),
        ])->assertOk();
        $this->assertFalse($event->fresh()->paid_registration);
        $this->assertSame(0, (int) $event->fresh()->registration_fee);
        $this->api($volunteerToken)->postJson('/api/event-registrations/', ['event' => $event->id])->assertUnprocessable();
        $this->api($token)->patchJson("/api/events/$event->id/", [
            'participation_type_id' => $this->choice('event_participation_type', 'Free Event (Registration Required)'),
            'registration_link' => 'https://example.com/register',
        ])->assertOk();
        $this->api($volunteerToken)->postJson('/api/event-registrations/', ['event' => $event->id])->assertUnprocessable();
        foreach (['Individual', 'Team'] as $value) {
            $body = $this->payload(Event::class) + ['participation_type_id' => $this->choice('event_participation_type', $value)];
            $this->api($token)->postJson('/api/events/', $body)->assertUnprocessable();
            $this->api($token)->postJson('/api/events/', $body + ['registration_required' => true, 'paid_registration' => false])->assertCreated();
        }
    }

    public function test_interest_bridge_uses_names_and_rejects_mixed_invalid_ids_atomically(): void
    {
        [$owner, $token] = $this->createOrganizationActor();
        $id = $this->choice('volunteer_opportunity_interest');
        $choice = MasterChoice::findOrFail($id);
        Interest::create(['id' => $id, 'name_en' => 'Unrelated numeric collision', 'name_ar' => 'مختلف', 'interest_type' => InterestType::VOLUNTEER]);
        $body = $this->payload(VolunteerOpportunity::class) + ['interest_ids' => [$id]];
        $response = $this->api($token)->postJson('/api/volunteer-opportunities/', $body)->assertCreated();
        $item = VolunteerOpportunity::findOrFail($response->json('data.id'));
        $response->assertJsonPath('data.interest_display.0.id', $id)->assertJsonPath('data.interest_display.0.value_en', $choice->value_en);
        $this->assertSame($choice->value_en, $item->interests()->firstOrFail()->name_en);
        $this->assertNotSame($id, $item->interests()->firstOrFail()->id);
        $body['interest_ids'][] = 999999;
        $this->api($token)->postJson('/api/volunteer-opportunities/', $body)->assertUnprocessable();
        $this->assertSame(1, VolunteerOpportunity::count());
        $this->api($token)->patchJson("/api/volunteer-opportunities/$item->id/", ['title_en' => 'Must not save', 'interest_ids' => [$id, 999999]])->assertUnprocessable();
        $this->assertSame('Reposted', $item->fresh()->title_en);
        $this->assertSame([$id], $item->masterInterests()->pluck('master_choices.id')->all());
    }
}
