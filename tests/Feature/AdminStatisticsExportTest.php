<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\UserType;
use App\Enums\VolunteerCategory;
use App\Models\Admin;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerStatistic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The client asked to be able to pull data and statistics out of the dashboard.
 */
class AdminStatisticsExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_statistics_export_downloads_a_spreadsheet(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')->get('/dashboard/statistics/export');

        $response->assertOk();
        $this->assertStringContainsString('vnd.ms-excel', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.xls', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_statistics_export_contains_the_headline_numbers(): void
    {
        $owner = $this->orgUser();

        VolunteerOpportunity::query()->create([
            'title_en' => 'Charity drive',
            'title_ar' => 'حملة خيرية',
            'description_en' => 'd',
            'description_ar' => 'و',
            'created_by' => $owner->id,
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::COMPLETED,
            'is_public' => true,
            'participants_needed' => 5,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDays(2)->toDateString(),
            'volunteer_category' => VolunteerCategory::CHARITY->value,
            'beneficiaries_count' => 40,
        ]);

        VolunteerStatistic::query()->create([
            'user_id' => $owner->id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'volunteer_hours' => 10,
        ]);

        $body = $this->actingAs($this->admin(), 'admin')
            ->get('/dashboard/statistics/export')
            ->streamedContent();

        // 10 hours x the default 6 KWD rate.
        $this->assertStringContainsString('60', $body);
        // 40 charity beneficiaries, no course learners yet.
        $this->assertStringContainsString('40', $body);
    }

    public function test_statistics_export_requires_an_authenticated_admin(): void
    {
        $this->get('/dashboard/statistics/export')->assertRedirect();
    }

    public function test_users_export_includes_nationality_and_civil_id(): void
    {
        $body = $this->actingAs($this->admin(), 'admin')
            ->get('/dashboard/users/export')
            ->streamedContent();

        // Assert on data, not header text: the dashboard locale varies.
        $this->assertStringContainsString('200000000001', $body);
        $this->assertSame(
            17,
            substr_count(substr($body, 0, (int) strpos($body, '</tr>')), '<th>'),
            'The export should carry 17 columns, including nationality, civil id, residency status, and passport number.'
        );
    }

    protected function admin(): Admin
    {
        return Admin::query()->firstOrFail();
    }

    protected function orgUser(): User
    {
        return User::query()->create([
            'email' => 'org.'.Str::lower(Str::random(6)).'@test.com',
            'password' => 'Password1',
            'password_length' => 9,
            'user_type' => UserType::ORGANIZATION,
            'first_name' => 'Org',
            'last_name' => 'Owner',
            'is_active' => true,
            'preferred_language' => 'en',
            'manual_id' => Str::random(22),
        ]);
    }
}
