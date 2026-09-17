<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\MasterChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-61 Part B — self check-in QR for learn-&-serve: one code, issued only
 * on the last day, valid two hours, excluded for Internship.
 */
class LearnServeSelfCheckInQrTest extends TestCase
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

    private function choice(string $type, string $value): int
    {
        return MasterChoice::whereHas('choiceType', fn ($q) => $q->where('name', $type))
            ->where('value_en', $value)->firstOrFail()->id;
    }

    private function opportunity($owner, string $learningType, array $extra = []): LearnServeOpportunity
    {
        return LearnServeOpportunity::create($extra + [
            'created_by' => $owner->id,
            'title_en' => 'Photography course', 'title_ar' => 'دورة تصوير',
            'description_en' => 'Description', 'description_ar' => 'وصف',
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->toDateString(),
            'approval_status' => ApprovalStatus::APPROVED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
            'participants_needed' => 10,
            'learning_type_id' => $this->choice('learning_type', $learningType),
        ]);
    }

    public function test_code_can_only_be_issued_on_the_last_day_and_not_for_internship(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();

        $notLastDay = $this->opportunity($owner, 'Course', ['end_date' => now()->addDay()->toDateString()]);
        $this->api($ownerToken)->postJson("/api/learn-serve-opportunities/{$notLastDay->id}/attendance-qr/")
            ->assertStatus(400);

        $internship = $this->opportunity($owner, 'Internship');
        $this->api($ownerToken)->postJson("/api/learn-serve-opportunities/{$internship->id}/attendance-qr/")
            ->assertStatus(400);

        $course = $this->opportunity($owner, 'Course');
        $this->api($ownerToken)->postJson("/api/learn-serve-opportunities/{$course->id}/attendance-qr/")
            ->assertOk()
            ->assertJsonStructure(['data' => ['code', 'expires_at']]);
    }

    public function test_re_issuing_invalidates_the_previous_code_and_only_the_owner_may_issue(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [, $strangerToken] = $this->createOrganizationActor();
        $course = $this->opportunity($owner, 'Course');

        $this->api($strangerToken)->postJson("/api/learn-serve-opportunities/{$course->id}/attendance-qr/")
            ->assertNotFound();

        $first = $this->api($ownerToken)->postJson("/api/learn-serve-opportunities/{$course->id}/attendance-qr/")
            ->assertOk()->json('data');
        $second = $this->api($ownerToken)->postJson("/api/learn-serve-opportunities/{$course->id}/attendance-qr/")
            ->assertOk()->json('data');

        $this->assertNotSame($first['code'], $second['code']);

        [, $participantToken] = $this->createVolunteerActor();
        $this->api($participantToken)->postJson('/api/learn-serve-attendance/self-scan/', [
            'code' => $first['code'],
        ])->assertStatus(400);
    }

    public function test_scan_marks_attended_once_and_rejects_expired_or_duplicate(): void
    {
        [$owner, $ownerToken] = $this->createOrganizationActor();
        [$participant, $participantToken] = $this->createVolunteerActor();
        $course = $this->opportunity($owner, 'Course');
        LearnServeOpportunityRegistration::create([
            'opportunity_id' => $course->id,
            'user_id' => $participant->id,
            'status' => ApprovalStatus::APPROVED,
        ]);

        $issued = $this->api($ownerToken)->postJson("/api/learn-serve-opportunities/{$course->id}/attendance-qr/")
            ->assertOk()->json('data');

        $this->api($participantToken)->postJson('/api/learn-serve-attendance/self-scan/', [
            'code' => $issued['code'],
        ])->assertOk()->assertJsonPath('data.is_attended', true);

        $this->assertTrue($course->registrations()->firstOrFail()->is_attended);

        // Already recorded — cannot scan a second time.
        $this->api($participantToken)->postJson('/api/learn-serve-attendance/self-scan/', [
            'code' => $issued['code'],
        ])->assertStatus(409);

        // A fresh registrant, but the code has since expired.
        [$latecomer, $latecomerToken] = $this->createVolunteerActor();
        LearnServeOpportunityRegistration::create([
            'opportunity_id' => $course->id,
            'user_id' => $latecomer->id,
            'status' => ApprovalStatus::APPROVED,
        ]);
        $this->travelTo(now()->addHours(3));
        $this->api($latecomerToken)->postJson('/api/learn-serve-attendance/self-scan/', [
            'code' => $issued['code'],
        ])->assertStatus(400);
        $this->travelBack();
    }

    public function test_qr_attendance_enabled_is_type_derived(): void
    {
        [$owner] = $this->createOrganizationActor();
        $course = $this->opportunity($owner, 'Course');
        $internship = $this->opportunity($owner, 'Internship');

        $this->getJson("/api/learn-serve-opportunities/{$course->id}/")
            ->assertOk()
            ->assertJsonPath('data.qr_attendance_enabled', true);

        $this->getJson("/api/learn-serve-opportunities/{$internship->id}/")
            ->assertOk()
            ->assertJsonPath('data.qr_attendance_enabled', false);
    }
}
