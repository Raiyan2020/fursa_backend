<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MasterChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-70 — the client reverted `due_date` back to required for learn & serve
 * (development) opportunities, matching BE-66's earlier fix for volunteering.
 * Events are explicitly excluded and stay optional.
 */
class LearnServeDueDateRequiredTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_api_rejects_a_learn_serve_opportunity_created_without_a_due_date(): void
    {
        [, $token] = $this->createOrganizationActor();

        $response = $this->api($token)->postJson('/api/learn-serve-opportunities/', [
            ...$this->learningChoicePayload(),
            'title_en' => 'No due date',
            'title_ar' => 'بدون تاريخ استحقاق',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'participants_needed' => 5,
        ]);

        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('response_status.validation_errors.due_date'));
    }

    public function test_api_accepts_a_learn_serve_opportunity_with_a_due_date(): void
    {
        [, $token] = $this->createOrganizationActor();

        $response = $this->api($token)->postJson('/api/learn-serve-opportunities/', [
            ...$this->learningChoicePayload(),
            'title_en' => 'Has due date',
            'title_ar' => 'بتاريخ استحقاق',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'due_date' => now()->addDays(1)->toDateString(),
            'participants_needed' => 5,
        ]);

        $response->assertCreated();
    }

    public function test_a_patch_that_omits_due_date_still_passes(): void
    {
        [, $token] = $this->createOrganizationActor();

        $create = $this->api($token)->postJson('/api/learn-serve-opportunities/', [
            ...$this->learningChoicePayload(),
            'title_en' => 'Editable', 'title_ar' => 'قابل للتعديل',
            'description_en' => 'Desc', 'description_ar' => 'وصف',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'due_date' => now()->addDays(1)->toDateString(),
            'participants_needed' => 5,
        ])->assertCreated();
        $id = $create->json('data.id');

        $this->api($token)->patchJson("/api/learn-serve-opportunities/{$id}/", [
            'title_en' => 'Editable renamed',
        ])->assertOk();
    }

    public function test_admin_form_rejects_a_learn_serve_opportunity_without_a_due_date(): void
    {
        $admin = $this->adminActor();
        [$orgUser] = $this->createOrganizationActor();

        $this->actingAs($admin, 'admin')->post('/dashboard/learn-serve-opportunities', [
            'created_by' => $orgUser->id,
            'title_en' => 'No due date admin',
            'title_ar' => 'بدون تاريخ استحقاق',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'participants_needed' => 5,
            'approval_status' => 'approved',
            'opportunity_status' => 'upcoming',
        ])->assertSessionHasErrors('due_date');
    }

    public function test_events_still_do_not_require_a_due_date(): void
    {
        [, $token] = $this->createOrganizationActor();
        $eventTypeId = $this->choice('event_type');

        $response = $this->api($token)->postJson('/api/events/', [
            'title_en' => 'No due date event',
            'title_ar' => 'فعالية بدون تاريخ استحقاق',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'event_type_id' => $eventTypeId,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertNull(Event::query()->latest('id')->first()->due_date);
    }

    private function choice(string $type): int
    {
        return MasterChoice::query()->whereHas('choiceType', fn ($q) => $q->where('name', $type))->firstOrFail()->id;
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
