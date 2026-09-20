<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\VolunteerCategory;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\OpportunitySponsorImage;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;
use ZipArchive;

/**
 * Backend items found while comparing the PDF review notes against
 * FURSA_BACKEND_ISSUES (3).md — logged as new fixes since the PDF raised
 * things not covered by any existing ticket.
 */
class BackendIssuesFromPdfReviewTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_administrative_is_now_an_accepted_volunteer_opportunity_category(): void
    {
        [, $token] = $this->createOrganizationActor();

        $response = $this->api($token)->postJson('/api/volunteer-opportunities/', [
            ...$this->volunteerOpportunityPayload(),
            'volunteer_category' => VolunteerCategory::ADMINISTRATIVE->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.volunteer_category', 'administrative')
            ->assertJsonPath('data.volunteer_category_display.en', 'Administrative')
            ->assertJsonPath('data.volunteer_category_display.ar', 'إداري');
    }

    public function test_full_name_accessor_joins_first_and_last_name(): void
    {
        [$volunteer] = $this->createVolunteerActor();
        $volunteer->update(['first_name' => 'Sara', 'last_name' => 'Ahmed']);

        $this->assertSame('Sara Ahmed', $volunteer->fresh()->full_name);
    }

    public function test_learn_serve_participants_export_no_longer_has_a_blank_name_column(): void
    {
        Storage::fake('public');
        [$organization, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $volunteer->update(['first_name' => 'UniqueExportFirst', 'last_name' => 'UniqueExportLast']);

        $opportunity = LearnServeOpportunity::query()->create([
            'created_by' => $organization->id,
            'title_en' => 'Export test course',
            'title_ar' => 'دورة اختبار التصدير',
            'description_en' => 'Description',
            'description_ar' => 'وصف',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'participants_needed' => 5,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
        ]);
        LearnServeOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'registration_date' => now(),
            'status' => ApprovalStatus::APPROVED,
        ]);

        $response = $this->api($orgToken)
            ->getJson('/api/learn-serve-opportunities/'.$opportunity->id.'/registrations/?download=true')
            ->assertOk();

        $downloadUrl = $response->json('data.downloadUrl');
        $this->assertNotEmpty($downloadUrl);

        $relativePath = $this->storageRelativePath($downloadUrl);
        $absolutePath = Storage::disk('public')->path($relativePath);

        $zip = new ZipArchive();
        $zip->open($absolutePath);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertStringContainsString('UniqueExportFirst UniqueExportLast', $sheet);
        $this->assertStringContainsString($volunteer->email, $sheet);
    }

    public function test_learn_serve_participants_export_includes_contact_and_guardian_columns_but_not_scan_allowed(): void
    {
        Storage::fake('public');
        [$organization, $orgToken] = $this->createOrganizationActor();
        [$volunteer] = $this->createVolunteerActor();
        $volunteer->update([
            'phone_number' => '55512345',
            'country_code' => '+965',
            'emergency_contact_name' => 'Guardian Export Name',
            'emergency_contact_phone' => '99887766',
            'emergency_contact_civil_id' => '281234567890',
        ]);
        $relationship = \App\Models\MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'emergency_contact_relationship'))
            ->where('value_en', 'Mother')
            ->firstOrFail();
        $volunteer->update(['emergency_contact_relationship_id' => $relationship->id]);

        $opportunity = LearnServeOpportunity::query()->create([
            'created_by' => $organization->id,
            'title_en' => 'Export test course 2',
            'title_ar' => 'دورة اختبار التصدير 2',
            'description_en' => 'Description',
            'description_ar' => 'وصف',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'participants_needed' => 5,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
        ]);
        LearnServeOpportunityRegistration::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'registration_date' => now(),
            'status' => ApprovalStatus::APPROVED,
        ]);

        $response = $this->api($orgToken)
            ->getJson('/api/learn-serve-opportunities/'.$opportunity->id.'/registrations/?download=true')
            ->assertOk();

        $relativePath = $this->storageRelativePath($response->json('data.downloadUrl'));
        $absolutePath = Storage::disk('public')->path($relativePath);

        $zip = new ZipArchive();
        $zip->open($absolutePath);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertStringContainsString('Guardian Export Name', $sheet);
        $this->assertStringContainsString('99887766', $sheet);
        $this->assertStringContainsString('281234567890', $sheet);
        $this->assertStringContainsString($relationship->value_ar, $sheet);
        $this->assertStringContainsString('96555512345', $sheet);
        $this->assertStringNotContainsString('Scan allowed', $sheet);
    }

    public function test_sponsor_name_is_hidden_on_a_paid_learn_serve_opportunity(): void
    {
        [$organization, $token] = $this->createOrganizationActor();
        [$sponsor] = $this->createOrganizationActor();

        $free = LearnServeOpportunity::query()->create([
            'created_by' => $organization->id,
            'title_en' => 'Free course', 'title_ar' => 'دورة مجانية',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'participants_needed' => 5,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'price' => null,
        ]);
        $paid = LearnServeOpportunity::query()->create([
            'created_by' => $organization->id,
            'title_en' => 'Paid course', 'title_ar' => 'دورة مدفوعة',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'participants_needed' => 5,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'is_paid' => true,
            'price' => 20,
        ]);

        foreach ([$free, $paid] as $opportunity) {
            OpportunitySponsorImage::query()->create([
                'learn_serve_opportunity_id' => $opportunity->id,
                'organization_id' => $sponsor->organizationProfile->id,
                'image' => 'sponsors/logo.png',
            ]);
        }

        $freeResponse = $this->api($token)->getJson('/api/learn-serve-opportunities/'.$free->id.'/')->assertOk();
        $this->assertNotEmpty($freeResponse->json('data.opportunity_sponsor_images'));

        $paidResponse = $this->api($token)->getJson('/api/learn-serve-opportunities/'.$paid->id.'/')->assertOk();
        $this->assertEmpty($paidResponse->json('data.opportunity_sponsor_images'));
    }

    protected function volunteerOpportunityPayload(array $overrides = []): array
    {
        return array_merge([
            'title_en' => 'PDF review test opportunity',
            'title_ar' => 'فرصة اختبار',
            'description_en' => 'Description',
            'description_ar' => 'وصف',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'participants_needed' => 5,
            'volunteer_category' => VolunteerCategory::ENVIRONMENTAL->value,
        ], $overrides);
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

    protected function storageRelativePath(string $url): string
    {
        $pos = strpos($url, '/storage/');

        return $pos !== false
            ? substr($url, $pos + strlen('/storage/'))
            : ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
    }
}
