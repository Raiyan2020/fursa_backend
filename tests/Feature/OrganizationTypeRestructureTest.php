<?php

namespace Tests\Feature;

use App\Models\MasterChoice;
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

    public function test_all_six_client_approved_types_are_available(): void
    {
        $available = $this->orgTypes()->pluck('value_en')->all();

        foreach (['Institution', 'Education', 'Society', 'NGO', 'Volunteer Team', 'Commercial'] as $expected) {
            $this->assertContains($expected, $available, "Missing org type: {$expected}");
        }
    }

    public function test_retired_types_are_no_longer_offered(): void
    {
        $available = $this->orgTypes()->pluck('value_en')->all();

        foreach (['Private', 'Public', 'Community', 'Company', 'Government'] as $retired) {
            $this->assertNotContains($retired, $available, "Retired org type still offered: {$retired}");
        }
    }

    public function test_new_types_carry_arabic_labels(): void
    {
        $society = $this->orgTypes()->firstWhere('value_en', 'Society');

        $this->assertNotNull($society);
        $this->assertNotEmpty($society->value_ar);
        $this->assertStringContainsString('جمعية', $society->value_ar);
    }

    public function test_org_type_choices_endpoint_returns_the_new_list(): void
    {
        $response = $this->getJson('/api/choices/org_type/');
        $response->assertOk();

        $values = array_column($response->json('data') ?? [], 'value_en');

        $this->assertContains('Institution', $values);
        $this->assertContains('Commercial', $values);
        $this->assertNotContains('Government', $values);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, MasterChoice>
     */
    protected function orgTypes()
    {
        return MasterChoice::query()
            ->notDeleted()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->get();
    }
}
