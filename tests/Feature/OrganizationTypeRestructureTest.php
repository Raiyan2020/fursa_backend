<?php

namespace Tests\Feature;

use App\Models\MasterChoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The client replaced the old org_type list with a six-option classification
 * and asked for the sector concept to be dropped from signup.
 */
class OrganizationTypeRestructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_all_seven_client_approved_types_are_available(): void
    {
        $available = $this->orgTypes()->pluck('value_en')->all();

        // BE-51 (round two): Institution/Education/Society/NGO were renamed and
        // Society split into Association + Community.
        foreach (['Governmental', 'Educational', 'Association', 'Community', 'NonProfit', 'Volunteer Team', 'Commercial'] as $expected) {
            $this->assertContains($expected, $available, "Missing org type: {$expected}");
        }
    }

    public function test_retired_types_are_no_longer_offered(): void
    {
        $available = $this->orgTypes()->pluck('value_en')->all();

        foreach (['Private', 'Public', 'Company', 'Government', 'Institution', 'Education', 'Society', 'NGO'] as $retired) {
            $this->assertNotContains($retired, $available, "Retired org type still offered: {$retired}");
        }
    }

    public function test_new_types_carry_arabic_labels(): void
    {
        $association = $this->orgTypes()->firstWhere('value_en', 'Association');

        $this->assertNotNull($association);
        $this->assertNotEmpty($association->value_ar);
        $this->assertStringContainsString('جمعية', $association->value_ar);
    }

    public function test_org_type_choices_endpoint_returns_the_new_list(): void
    {
        $response = $this->getJson('/api/choices/org_type/');
        $response->assertOk();

        $values = array_column($response->json('data') ?? [], 'value_en');

        $this->assertSame([
            'Governmental',
            'Commercial',
            'Educational',
            'NonProfit',
            'Association',
            'Community',
        ], $values);
    }

    public function test_round_two_migration_renames_society_in_place_and_restores_community(): void
    {
        $migration = require database_path('migrations/2026_09_15_000001_restructure_organization_types_round_two.php');
        $migration->down();

        $society = $this->orgTypes(includeDeleted: true)->firstWhere('value_en', 'Society');
        $this->assertNotNull($society);
        $societyId = $society->id;

        $migration->up();

        $this->assertSame('Association', MasterChoice::query()->findOrFail($societyId)->value_en);
        $this->assertNotNull($this->orgTypes()->firstWhere('value_en', 'Community'));
    }

    /**
     * @return Collection<int, MasterChoice>
     */
    protected function orgTypes(bool $includeDeleted = false)
    {
        return MasterChoice::query()
            ->when(! $includeDeleted, fn ($query) => $query->notDeleted())
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->get();
    }
}
