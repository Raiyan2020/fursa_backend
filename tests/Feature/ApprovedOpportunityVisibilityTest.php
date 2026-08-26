<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * Reproduces the reported bug: an opportunity approved from the dashboard never
 * appears on the public site.
 */
class ApprovedOpportunityVisibilityTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed();
    }

    public function test_opportunity_created_via_api_defaults_to_not_public(): void
    {
        [, $token] = $this->createOrganizationActor();

        $response = $this->api($token)->postJson('/api/volunteer-opportunities/', [
            'title_en' => 'Sint perspiciatis',
            'title_ar' => 'فرصة',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'participants_needed' => 8,
        ]);

        $response->assertSuccessful();

        $opportunity = VolunteerOpportunity::query()->find($response->json('data.id'));

        // This is the root cause: the column defaults to false and the publisher
        // never sends it, so the record can never satisfy the public filter.
        $this->assertFalse((bool) $opportunity->is_public);
    }

    public function test_approving_from_the_dashboard_leaves_it_hidden(): void
    {
        [$org] = $this->createOrganizationActor();

        $opportunity = VolunteerOpportunity::query()->create([
            'title_en' => 'Sint perspiciatis',
            'title_ar' => 'فرصة',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'created_by' => $org->id,
            'approval_status' => ApprovalStatus::PENDING,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'is_public' => false,
            'participants_needed' => 8,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        $this->actingAs($this->adminActor(), 'admin')
            ->post("/dashboard/volunteer-opportunities/{$opportunity->id}/approve")
            ->assertRedirect();

        $opportunity->refresh();

        // Approval flips approval_status...
        $this->assertSame(ApprovalStatus::APPROVED, $opportunity->approval_status);
        // ...but is_public stays false, so the public list still excludes it.
        $this->assertFalse((bool) $opportunity->is_public);

        $ids = array_column(
            $this->getJson('/api/list-volunteer-opportunities/')->json('data') ?? [],
            'id'
        );

        $this->assertNotContains(
            $opportunity->id,
            $ids,
            'Reproduces the bug: an approved opportunity is absent from the public list.'
        );
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
