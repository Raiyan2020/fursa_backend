<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\VolunteerCategory;
use App\Models\Event;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\MasterChoice;
use App\Models\OpportunityImage;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-21, BE-22 and BE-23 from the frontend's backend-issues log (2026-09-09).
 */
class BackendIssuesRoundTwoTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed();
    }

    /** @return array<int, int> */
    private function choiceIds(string $type, int $take = 3): array
    {
        return MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', $type))
            ->limit($take)
            ->pluck('id')
            ->all();
    }

    // ---------------------------------------------------------------
    // BE-23 — interest_ids must accept the ids /choices/* actually serves
    // ---------------------------------------------------------------

    public function test_be23_volunteer_opportunity_accepts_master_choice_interest_ids(): void
    {
        [, $token] = $this->createOrganizationActor();
        $interestIds = $this->choiceIds('volunteer_opportunity_interest', 3);
        $this->assertCount(3, $interestIds, 'seeded volunteer interest choices');

        $response = $this->withToken($token)->postJson('/api/volunteer-opportunities/', [
            'title_en' => 'Tag write test',
            'title_ar' => 'اختبار الوسوم',
            'description_en' => 'desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'participants_needed' => 5,
            'volunteer_category' => VolunteerCategory::CHARITY->value,
            'interest_ids' => $interestIds,
        ]);

        $response->assertStatus(201);

        // The write actually landed on the master-choice pivot...
        $opportunity = VolunteerOpportunity::query()->latest('id')->first();
        $this->assertEqualsCanonicalizing(
            $interestIds,
            $opportunity->masterInterests()->pluck('master_choices.id')->all()
        );

        // ...and the read payload is no longer empty (this is BE-01's symptom).
        $interests = $response->json('data.interests');
        $this->assertCount(3, $interests);
        // Shape is unchanged from before (id / name_en / name_ar /
        // interest_type) — only the data is new, so no client changes shape.
        $this->assertNotNull($interests[0]['name_en'] ?? null);
        $this->assertArrayHasKey('id', $interests[0]);
        $this->assertContains($interests[0]['id'], $interestIds);
    }

    public function test_be23_learn_serve_and_event_accept_their_own_vocabularies(): void
    {
        [, $orgToken] = $this->createOrganizationActor();

        $lsIds = $this->choiceIds('learnserve_opportunity_interest', 2);
        $ls = $this->withToken($orgToken)->postJson('/api/learn-serve-opportunities/', [
            ...$this->learningChoicePayload(),
            'title_en' => 'LS tags',
            'title_ar' => 'وسوم',
            'description_en' => 'd',
            'description_ar' => 'و',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'participants_needed' => 4,
            'interest_ids' => $lsIds,
        ]);
        $ls->assertStatus(201);
        $this->assertCount(2, $ls->json('data.interests'));

        $eventIds = $this->choiceIds('event_interest', 2);
        $eventTypeId = $this->choiceIds('event_type', 1)[0] ?? null;
        $this->assertNotNull($eventTypeId, 'seeded event_type choice');

        $event = $this->withToken($orgToken)->postJson('/api/events/', [
            'title_en' => 'Event tags',
            'title_ar' => 'وسوم',
            'description_en' => 'Event tags description',
            'description_ar' => 'وصف الفعالية',
            'event_type_id' => $eventTypeId,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'interest_ids' => $eventIds,
        ]);
        $event->assertStatus(201);
        $this->assertEqualsCanonicalizing(
            $eventIds,
            Event::query()->latest('id')->first()->masterInterests()->pluck('master_choices.id')->all()
        );
    }

    public function test_be23_an_id_from_the_wrong_vocabulary_is_rejected_with_a_clear_error(): void
    {
        [, $token] = $this->createOrganizationActor();
        // profile tags are a different curated list from opportunity tags
        $wrongIds = $this->choiceIds('user_interest', 1);
        $this->assertNotEmpty($wrongIds);

        $response = $this->withToken($token)->postJson('/api/volunteer-opportunities/', [
            'title_en' => 'Wrong vocab',
            'title_ar' => 'خطأ',
            'description_en' => 'd',
            'description_ar' => 'و',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'participants_needed' => 5,
            'volunteer_category' => VolunteerCategory::CHARITY->value,
            'interest_ids' => $wrongIds,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            '/api/choices/volunteer_opportunity_interest/',
            json_encode($response->json('response_status.validation_errors'), JSON_UNESCAPED_SLASHES)
        );
    }

    // ---------------------------------------------------------------
    // BE-22 ask 4 — an unknown write key must 422, not be dropped
    // ---------------------------------------------------------------

    public function test_be22_unknown_write_key_is_rejected(): void
    {
        [, $token] = $this->createOrganizationActor();

        $response = $this->withToken($token)->postJson('/api/volunteer-opportunities/', [
            'title_en' => 'Unknown key',
            'title_ar' => 'مفتاح',
            'description_en' => 'd',
            'description_ar' => 'و',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'participants_needed' => 5,
            'volunteer_category' => VolunteerCategory::CHARITY->value,
            // the exact mistake BE-20/BE-22 were caused by
            'gender' => 1,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('gender', $response->json('response_status.validation_errors'));
    }

    public function test_be22_the_documented_field_names_are_accepted(): void
    {
        [, $token] = $this->createOrganizationActor();
        $genderId = $this->choiceIds('opportunity_gender', 1)[0] ?? null;

        $payload = [
            'title_en' => 'Correct names',
            'title_ar' => 'أسماء',
            'description_en' => 'd',
            'description_ar' => 'و',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'participants_needed' => 5,
            'volunteer_category' => VolunteerCategory::CHARITY->value,
            'interest_ids' => $this->choiceIds('volunteer_opportunity_interest', 2),
        ];

        if ($genderId) {
            $payload['gender_id'] = $genderId;
        }

        $this->withToken($token)
            ->postJson('/api/volunteer-opportunities/', $payload)
            ->assertStatus(201);
    }

    // ---------------------------------------------------------------
    // BE-22 ask 2 — existing_image_ids keeps every id sent as an array
    // ---------------------------------------------------------------

    public function test_be22_existing_image_ids_keeps_all_three_images(): void
    {
        [$org, $token] = $this->createOrganizationActor();

        $opportunity = VolunteerOpportunity::query()->create([
            'title_en' => 'Images', 'title_ar' => 'صور',
            'description_en' => 'd', 'description_ar' => 'و',
            'start_date' => now()->addDays(2), 'end_date' => now()->addDays(3),
            'participants_needed' => 5,
            'created_by' => $org->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
        ]);

        $imageIds = collect(range(1, 3))->map(fn ($i) => OpportunityImage::query()->create([
            'volunteer_opportunity_id' => $opportunity->id,
            'image' => "opportunity-images/img{$i}.jpg",
            'is_after_completed' => true,
        ])->id)->all();

        $this->withToken($token)
            ->patchJson("/api/volunteer-opportunities/{$opportunity->id}/update_images/", [
                'existing_image_ids' => $imageIds,
            ])
            ->assertStatus(200);

        $this->assertSame(
            3,
            OpportunityImage::query()
                ->where('volunteer_opportunity_id', $opportunity->id)
                ->whereIn('id', $imageIds)
                ->count(),
            'all three ids stay attached'
        );
    }

    // ---------------------------------------------------------------
    // BE-21 — certificate rows must say which table they came from
    // ---------------------------------------------------------------

    public function test_be21_user_certificates_rows_carry_registration_type(): void
    {
        [$volunteer, $token] = $this->createVolunteerActor();
        [$org] = $this->createOrganizationActor();

        $volunteerOpportunity = VolunteerOpportunity::query()->create([
            'title_en' => 'V opp', 'title_ar' => 'فرصة',
            'description_en' => 'd', 'description_ar' => 'و',
            'start_date' => now()->subDays(5), 'end_date' => now()->subDays(4),
            'participants_needed' => 5,
            'created_by' => $org->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
        ]);

        VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $volunteerOpportunity->id,
            'user_id' => $volunteer->id,
            'registration_date' => now()->subDays(6),
            'status' => ApprovalStatus::APPROVED,
            'is_certified' => true,
            'certificate_image' => 'certificates/v.html',
        ]);

        $learnServe = LearnServeOpportunity::query()->create([
            'title_en' => 'LS opp', 'title_ar' => 'دورة',
            'description_en' => 'd', 'description_ar' => 'و',
            'start_date' => now()->subDays(5), 'end_date' => now()->subDays(4),
            'participants_needed' => 5,
            'created_by' => $org->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
        ]);

        LearnServeOpportunityRegistration::query()->create([
            'opportunity_id' => $learnServe->id,
            'user_id' => $volunteer->id,
            'registration_date' => now()->subDays(6),
            'status' => ApprovalStatus::APPROVED,
            'is_attended' => true,
            'is_certified' => true,
            'certificate_image' => 'certificates/ls.html',
        ]);

        $rows = $this->withToken($token)
            ->getJson("/api/user-certificates/?user_id={$volunteer->id}")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertArrayHasKey('registration_type', $row);
            $this->assertContains($row['registration_type'], ['volunteer', 'learn_serve']);
        }
        $this->assertEqualsCanonicalizing(
            ['volunteer', 'learn_serve'],
            array_column($rows, 'registration_type')
        );
    }

    public function test_be21_registration_ids_do_collide_across_the_two_tables(): void
    {
        [$volunteer] = $this->createVolunteerActor();
        [$org] = $this->createOrganizationActor();

        $v = VolunteerOpportunity::query()->create([
            'title_en' => 'v', 'title_ar' => 'v',
            'description_en' => 'd', 'description_ar' => 'و',
            'start_date' => now(), 'end_date' => now()->addDay(),
            'participants_needed' => 1, 'created_by' => $org->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
        ]);
        $ls = LearnServeOpportunity::query()->create([
            'title_en' => 'l', 'title_ar' => 'l',
            'description_en' => 'd', 'description_ar' => 'و',
            'start_date' => now(), 'end_date' => now()->addDay(),
            'participants_needed' => 1, 'created_by' => $org->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
        ]);

        $vReg = VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $v->id, 'user_id' => $volunteer->id,
            'registration_date' => now(), 'status' => ApprovalStatus::APPROVED,
        ]);
        $lsReg = LearnServeOpportunityRegistration::query()->create([
            'opportunity_id' => $ls->id, 'user_id' => $volunteer->id,
            'registration_date' => now(), 'status' => ApprovalStatus::APPROVED,
        ]);

        // Independent sequences: the first row of each table shares an id.
        $this->assertSame(
            $vReg->id,
            $lsReg->id,
            'the two registration tables have independent id sequences, so ids collide'
        );
    }
}
