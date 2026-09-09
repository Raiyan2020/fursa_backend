<?php

namespace Tests\Feature;

use App\Models\ContactUs;
use App\Models\Sponsor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-29 — the contact-us read/update/delete methods and the sponsor mutations were
 * public with no authorization in either controller.
 */
class AdminApiRouteProtectionTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function contactMessage(): ContactUs
    {
        return ContactUs::query()->create([
            'name_en' => 'Reporter',
            'email' => 'reporter@example.com',
            'message_en' => 'Private message body',
        ]);
    }

    private function sponsor(): Sponsor
    {
        return Sponsor::query()->create([
            'org_name' => 'Acme',
            'person_name' => 'Contact Person',
            'email' => 'sponsor@example.com',
        ]);
    }

    public function test_contact_submissions_are_not_readable_without_a_token(): void
    {
        $message = $this->contactMessage();

        $this->getJson('/api/contact-us/')->assertStatus(401);
        $this->getJson("/api/contact-us/{$message->id}/")->assertStatus(401);
        $this->patchJson("/api/contact-us/{$message->id}/", ['name_en' => 'x'])->assertStatus(401);
        $this->deleteJson("/api/contact-us/{$message->id}/")->assertStatus(401);

        $this->assertDatabaseHas('contact_us', ['id' => $message->id, 'name_en' => 'Reporter']);
    }

    public function test_a_normal_volunteer_cannot_read_or_delete_contact_submissions(): void
    {
        $message = $this->contactMessage();
        [, $token] = $this->createVolunteerActor();

        $this->withToken($token)->getJson('/api/contact-us/')->assertStatus(403);
        $this->withToken($token)->deleteJson("/api/contact-us/{$message->id}/")->assertStatus(403);

        $this->assertDatabaseHas('contact_us', ['id' => $message->id]);
    }

    public function test_staff_can_read_contact_submissions(): void
    {
        $this->contactMessage();
        [, $token] = $this->createStaffActor();

        $this->withToken($token)->getJson('/api/contact-us/')->assertStatus(200);
    }

    public function test_submitting_the_contact_form_stays_public(): void
    {
        $this->postJson('/api/contact-us/', [
            'name_en' => 'Visitor',
            'email' => 'visitor@example.com',
            'message_en' => 'Hello',
        ])->assertSuccessful();
    }

    public function test_sponsor_mutations_require_staff(): void
    {
        $sponsor = $this->sponsor();
        [, $volunteerToken] = $this->createVolunteerActor();

        $this->patchJson("/api/sponsors/{$sponsor->id}/", ['org_name' => 'Hijacked'])->assertStatus(401);
        $this->deleteJson("/api/sponsors/{$sponsor->id}/")->assertStatus(401);

        $this->withToken($volunteerToken)
            ->deleteJson("/api/sponsors/{$sponsor->id}/")
            ->assertStatus(403);

        $this->assertDatabaseHas('sponsors', ['id' => $sponsor->id, 'org_name' => 'Acme']);
    }

    public function test_public_sponsor_reads_and_applications_still_work(): void
    {
        // index/show filter to approved, so they are safe to leave public — the
        // website's sponsors section depends on them.
        $this->getJson('/api/sponsors/')->assertStatus(200);
    }
}
