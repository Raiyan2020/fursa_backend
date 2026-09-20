<?php

namespace Tests\Feature;

use App\Models\Config;
use App\Models\MasterChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * PDF review: a paid learn & serve opportunity needs a price, and an
 * individual (Volunteer Team) or Association publisher must supply bank
 * details before publishing one, so their share can be transferred to them
 * manually after the platform's 7% cut. A full organization is exempt.
 */
class PaidOpportunityPayoutTest extends TestCase
{
    use CreatesDomainFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function api(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function payload(array $overrides = []): array
    {
        $format = MasterChoice::query()->whereHas('choiceType', fn ($q) => $q->where('name', 'learn_serve_format'))->firstOrFail();
        $learningType = MasterChoice::query()->whereHas('choiceType', fn ($q) => $q->where('name', 'learning_type'))
            ->where('value_en', 'Class/Workshop')->firstOrFail();

        return array_merge([
            'title_en' => 'Paid course', 'title_ar' => 'دورة مدفوعة',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'participants_needed' => 10,
            'format_id' => $format->id,
            'learning_type_id' => $learningType->id,
            'is_paid' => true,
            'price' => 75,
        ], $overrides);
    }

    public function test_paid_opportunity_requires_a_price(): void
    {
        [, $token] = $this->createOrganizationActor();

        $response = $this->api($token)->postJson('/api/learn-serve-opportunities/', $this->payload(['price' => null]));

        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('response_status.validation_errors.price'));
    }

    public function test_individual_publisher_must_have_bank_details_before_publishing_paid(): void
    {
        [$team, $token] = $this->createVolunteerTeamActor();

        $rejected = $this->api($token)->postJson('/api/learn-serve-opportunities/', $this->payload());
        $rejected->assertStatus(422);
        $this->assertNotEmpty($rejected->json('response_status.validation_errors.bank_account'));

        $team->organizationProfile->update([
            'bank_name' => 'Test Bank',
            'bank_account_holder_name' => 'Team Lead',
            'bank_account_number' => 'KW00TEST0000000000000000',
        ]);

        $response = $this->api($token)->postJson('/api/learn-serve-opportunities/', $this->payload());
        $response->assertSuccessful();

        $this->assertSame(75.0, (float) $response->json('data.price'));
        $this->assertSame(69.75, (float) $response->json('data.payout_after_fee'));
    }

    public function test_association_publisher_must_have_bank_details_before_publishing_paid(): void
    {
        [$user, $token] = $this->createOrganizationActor();
        $associationType = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->where('value_en', 'Association')
            ->firstOrFail();
        $user->organizationProfile->update(['organizer_type_id' => $associationType->id]);

        $response = $this->api($token)->postJson('/api/learn-serve-opportunities/', $this->payload());
        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('response_status.validation_errors.bank_account'));
    }

    public function test_full_organization_publisher_is_exempt_from_bank_details(): void
    {
        [$user, $token] = $this->createOrganizationActor();
        $commercialType = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->where('value_en', 'Commercial')
            ->firstOrFail();
        $user->organizationProfile->update(['organizer_type_id' => $commercialType->id]);

        $this->api($token)->postJson('/api/learn-serve-opportunities/', $this->payload())
            ->assertSuccessful();
    }

    public function test_payout_uses_the_configured_platform_fee_percentage(): void
    {
        Config::query()->update(['platform_fee_percentage' => 10]);
        [$team, $token] = $this->createVolunteerTeamActor();
        $team->organizationProfile->update([
            'bank_name' => 'Test Bank',
            'bank_account_holder_name' => 'Team Lead',
            'bank_account_number' => 'KW00TEST0000000000000000',
        ]);

        $response = $this->api($token)->postJson('/api/learn-serve-opportunities/', $this->payload(['price' => 100]));
        $response->assertSuccessful();

        $this->assertSame(90.0, (float) $response->json('data.payout_after_fee'));
        $this->assertSame(10.0, (float) $response->json('data.platform_fee_percentage'));
    }

    public function test_platform_fee_percentage_defaults_to_seven_when_unconfigured(): void
    {
        [$team, $token] = $this->createVolunteerTeamActor();
        $team->organizationProfile->update([
            'bank_name' => 'Test Bank',
            'bank_account_holder_name' => 'Team Lead',
            'bank_account_number' => 'KW00TEST0000000000000000',
        ]);

        $response = $this->api($token)->postJson('/api/learn-serve-opportunities/', $this->payload(['price' => 100]));
        $response->assertSuccessful();

        $this->assertSame(7.0, (float) $response->json('data.platform_fee_percentage'));
    }

    public function test_free_opportunity_needs_neither_price_nor_bank_details(): void
    {
        [, $token] = $this->createVolunteerTeamActor();

        $this->api($token)->postJson('/api/learn-serve-opportunities/', $this->payload([
            'is_paid' => false,
            'price' => null,
        ]))->assertSuccessful();
    }

    public function test_organization_profile_can_update_bank_details(): void
    {
        [, $token] = $this->createOrganizationActor();

        $response = $this->api($token)->putJson('/api/organization-profile/', [
            'bank_name' => 'Gulf Bank',
            'bank_account_holder_name' => 'Test Org',
            'bank_account_number' => 'KW00GULF0000000000000000',
        ]);

        $response->assertSuccessful();
        $this->assertSame('Gulf Bank', $response->json('data.bank_name'));
    }
}
