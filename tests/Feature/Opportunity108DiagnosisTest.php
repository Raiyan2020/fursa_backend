<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * Diagnoses why opportunity #108 is missing from the public site while #106
 * (older, same flags) shows fine.
 *
 * Real row values from the dump:
 *   #108 approved, is_public=1, is_deleted=0, due_date 2026-08-26 00:00,
 *        start_date 2026-08-27
 *   #109 approved, is_public=1, is_deleted=1  <- soft deleted
 */
class Opportunity108DiagnosisTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed();
        // The day the client reported the issue.
        Carbon::setTestNow('2026-08-26 11:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_row_108_with_a_past_due_date_still_appears_in_the_list(): void
    {
        [$org] = $this->createOrganizationActor();

        $row108 = $this->row($org->id, [
            'title_en' => 'Dolore esse et volup',
            'due_date' => '2026-08-26 00:00:00',
            'start_date' => '2026-08-27',
            'end_date' => '2026-08-29',
            'participants_needed' => 123,
            'is_public' => true,
            'is_deleted' => false,
        ]);

        $ids = array_column(
            $this->getJson('/api/list-volunteer-opportunities/')->json('data') ?? [],
            'id'
        );

        // A past due_date only sinks it in the ordering; it is NOT excluded.
        $this->assertContains(
            $row108->id,
            $ids,
            'A past due_date must not hide the opportunity from the public list.'
        );
    }

    public function test_row_109_is_hidden_because_it_is_soft_deleted(): void
    {
        [$org] = $this->createOrganizationActor();

        $row109 = $this->row($org->id, [
            'title_en' => 'Sint perspiciatis',
            'due_date' => '2026-08-27 00:00:00',
            'start_date' => '2026-08-28',
            'end_date' => '2026-08-29',
            'participants_needed' => 300,
            'is_public' => true,
            'is_deleted' => true,
        ]);

        $ids = array_column(
            $this->getJson('/api/list-volunteer-opportunities/')->json('data') ?? [],
            'id'
        );

        $this->assertNotContains($row109->id, $ids);
    }

    public function test_full_capacity_would_hide_nothing_either(): void
    {
        [$org] = $this->createOrganizationActor();

        $row = $this->row($org->id, [
            'title_en' => 'Full one',
            'due_date' => '2026-08-30 00:00:00',
            'start_date' => '2026-08-31',
            'end_date' => '2026-09-01',
            'participants_needed' => 1,
            'is_public' => true,
            'is_deleted' => false,
        ]);

        $ids = array_column(
            $this->getJson('/api/list-volunteer-opportunities/')->json('data') ?? [],
            'id'
        );

        $this->assertContains($row->id, $ids);
    }

    public function test_pagination_can_push_a_row_off_the_first_page(): void
    {
        [$org] = $this->createOrganizationActor();

        // 12 healthy upcoming rows rank ahead of a past-due one.
        for ($i = 0; $i < 12; $i++) {
            $this->row($org->id, [
                'title_en' => "Healthy {$i}",
                'due_date' => '2026-09-20 00:00:00',
                'start_date' => '2026-09-21',
                'end_date' => '2026-09-22',
                'participants_needed' => 10,
                'is_public' => true,
                'is_deleted' => false,
            ]);
        }

        $pastDue = $this->row($org->id, [
            'title_en' => 'Dolore esse et volup',
            'due_date' => '2026-08-26 00:00:00',
            'start_date' => '2026-08-27',
            'end_date' => '2026-08-29',
            'participants_needed' => 123,
            'is_public' => true,
            'is_deleted' => false,
        ]);

        $firstPage = array_column(
            $this->getJson('/api/list-volunteer-opportunities/?page=1&limit=10')->json('data') ?? [],
            'id'
        );

        // This is the practical failure mode: it is in the data set, but it
        // sorts into the last bucket, so page 1 never shows it.
        $this->assertNotContains(
            $pastDue->id,
            $firstPage,
            'A past-due row sinks to the last bucket and falls off page 1.'
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function row(int $ownerId, array $attributes): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create(array_merge([
            'title_ar' => 'فرصة',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'created_by' => $ownerId,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
        ], $attributes));
    }
}
