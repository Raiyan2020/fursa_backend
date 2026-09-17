<?php

namespace Tests\Feature;

use App\Models\ChoiceType;
use App\Models\LearnServeOpportunity;
use App\Models\MasterChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-62 — Consultation renamed to «مساحة» (label only), and master_choices
 * gets a stable slug so behaviour never again has to match on that label.
 */
class RenameConsultationSlugTest extends TestCase
{
    use CreatesDomainFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_consultation_arabic_label_is_renamed_in_both_choice_types_without_changing_ids(): void
    {
        foreach (['learning_type', 'filter-type'] as $typeName) {
            $type = ChoiceType::query()->where('name', $typeName)->firstOrFail();
            $choice = MasterChoice::query()
                ->where('choice_type_id', $type->id)
                ->where('value_en', 'Consultation')
                ->firstOrFail();

            $this->assertSame('مساحة', $choice->value_ar);
            $this->assertSame('Consultation', $choice->value_en);
        }
    }

    public function test_every_master_choice_gets_a_unique_slug_per_choice_type(): void
    {
        $this->assertSame(0, MasterChoice::query()->whereNull('slug')->count());

        $duplicate = MasterChoice::query()
            ->select('choice_type_id', 'slug')
            ->groupBy('choice_type_id', 'slug')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->assertCount(0, $duplicate, 'Every (choice_type_id, slug) pair must be unique.');

        // The known intentional case-variant duplicate still gets two distinct slugs.
        $certificateType = ChoiceType::query()->where('name', 'learn_serve_certificate_type')->firstOrFail();
        $slugs = MasterChoice::query()
            ->where('choice_type_id', $certificateType->id)
            ->where('value_en', 'like', '%forsa certificate%')
            ->pluck('slug');

        $this->assertCount(2, $slugs);
        $this->assertNotSame($slugs[0], $slugs[1]);
    }

    public function test_internship_matches_via_slug_not_label(): void
    {
        $learningType = ChoiceType::query()->where('name', 'learning_type')->firstOrFail();
        $internship = MasterChoice::query()
            ->where('choice_type_id', $learningType->id)
            ->where('value_en', 'Internship')
            ->firstOrFail();

        $this->assertSame('internship', $internship->slug);

        $opportunity = new LearnServeOpportunity(['learning_type_id' => $internship->id]);
        $opportunity->setRelation('learningType', $internship);

        $this->assertTrue($opportunity->isInternship());
        $this->assertFalse($opportunity->qrAttendanceEligible());

        // Falls back to the label match when a row has no slug yet.
        $legacyType = new MasterChoice(['value_en' => 'Internship', 'slug' => null]);
        $opportunity->setRelation('learningType', $legacyType);
        $this->assertTrue($opportunity->isInternship());
    }

    public function test_choices_endpoint_returns_the_renamed_label_and_a_slug(): void
    {
        $response = $this->getJson('/api/choices/learning_type/')->assertOk();

        $consultation = collect($response->json('data'))->firstWhere('value_en', 'Consultation');

        $this->assertNotNull($consultation);
        $this->assertSame('مساحة', $consultation['value_ar']);
        $this->assertSame('consultation', $consultation['slug']);

        $filterType = $this->getJson('/api/choices/filter-type/')->assertOk();
        $filterConsultation = collect($filterType->json('data'))->firstWhere('value_en', 'Consultation');
        $this->assertSame('مساحة', $filterConsultation['value_ar']);
    }
}
