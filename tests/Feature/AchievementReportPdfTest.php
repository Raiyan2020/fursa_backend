<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class AchievementReportPdfTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function api(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders([
            'Authorization' => 'Token '.$token,
            'Accept' => 'application/json',
        ]);
    }

    public function test_download_true_returns_a_real_pdf_with_the_volunteers_activity(): void
    {
        [$org] = $this->createOrganizationActor();
        [$volunteer, $volunteerToken] = $this->createVolunteerActor();

        $opportunity = VolunteerOpportunity::query()->create([
            'title_en' => 'Beach Cleanup', 'title_ar' => 'تنظيف الشاطئ',
            'description_en' => 'Desc', 'description_ar' => 'وصف',
            'created_by' => $org->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
            'is_public' => true,
            'participants_needed' => 10,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(8)->toDateString(),
        ]);
        VolunteerOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
        ]);

        $response = $this->api($volunteerToken)->get('/api/volunteer-detail/?download=true');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(1000, strlen($response->getContent()));
    }

    public function test_download_true_without_activity_still_returns_a_valid_pdf(): void
    {
        [, $volunteerToken] = $this->createVolunteerActor();

        $response = $this->api($volunteerToken)->get('/api/volunteer-detail/?download=true');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
