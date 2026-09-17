<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Services\Auth\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * BE-59 — a submission landing as PENDING now notifies the admins who can
 * actually approve it, scoped by the module-specific `.approve` permission
 * rather than broadcasting to every admin regardless of what they can act on.
 */
class AdminOpportunityNotificationTest extends TestCase
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

    /**
     * Created after seeding, so RolePermissionSeeder's "promote every
     * existing admin to super-admin" pass does not sweep this one up — it
     * only ever has the one permission given to it here.
     */
    private function adminWithPermission(?string $permission): Admin
    {
        $admin = Admin::query()->create([
            'name' => 'Reviewer '.uniqid(),
            'email' => uniqid().'@fursa.test',
            'phone' => '99999999',
            'password' => 'password',
            'is_active' => true,
        ]);

        if ($permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    private function unreadNotificationTitles(Admin $admin): array
    {
        return AdminNotification::query()
            ->where('admin_id', $admin->id)
            ->where('is_read', false)
            ->with('notification')
            ->get()
            ->pluck('notification.title_en')
            ->all();
    }

    public function test_creating_a_volunteer_opportunity_notifies_only_admins_who_can_approve_it(): void
    {
        $approver = $this->adminWithPermission('volunteer-opportunities.approve');
        $unrelated = $this->adminWithPermission('events.approve');

        [, $token] = $this->createOrganizationActor();

        $this->api($token)->postJson('/api/volunteer-opportunities/', [
            'title_en' => 'Beach cleanup',
            'title_ar' => 'تنظيف الشاطئ',
            'description_en' => 'Desc',
            'description_ar' => 'وصف',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'participants_needed' => 10,
            'volunteer_category' => \App\Enums\VolunteerCategory::ENVIRONMENTAL->value,
        ])->assertCreated();

        $titles = $this->unreadNotificationTitles($approver);
        $this->assertCount(1, $titles);
        $this->assertStringContainsString('New volunteer opportunity awaiting review', $titles[0]);

        $this->assertSame(0, AdminNotification::query()->where('admin_id', $unrelated->id)->count());
        $this->assertSame(1, $approver->fresh()->unreadNotificationsCount());
    }

    public function test_resubmitting_a_rejected_volunteer_opportunity_notifies_admins_again(): void
    {
        $approver = $this->adminWithPermission('volunteer-opportunities.approve');
        [$org, $token] = $this->createOrganizationActor();

        $opportunity = \App\Models\VolunteerOpportunity::create([
            'created_by' => $org->id,
            'title_en' => 'Rejected once', 'title_ar' => 'رُفضت مرة',
            'description_en' => 'Desc', 'description_ar' => 'وصف',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'participants_needed' => 10,
            'approval_status' => ApprovalStatus::REJECTED,
            'rejected_reason' => 'Missing details',
        ]);

        $this->api($token)->postJson("/api/volunteer-opportunities/{$opportunity->id}/resubmit/")
            ->assertOk();

        $this->assertSame(1, $approver->fresh()->unreadNotificationsCount());
    }

    public function test_creating_a_learn_serve_opportunity_notifies_admins_with_that_modules_permission(): void
    {
        $approver = $this->adminWithPermission('learn-serve-opportunities.approve');
        [, $token] = $this->createOrganizationActor();

        $learningType = $this->choice('learning_type', 'Course');
        $format = $this->choice('learn_serve_format');
        $certificateType = $this->choice('learn_serve_certificate_type');

        $this->api($token)->postJson('/api/learn-serve-opportunities/', [
            'title_en' => 'Intro to coding', 'title_ar' => 'مقدمة في البرمجة',
            'description_en' => 'Desc', 'description_ar' => 'وصف',
            'start_date' => now()->addDays(6)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'participants_needed' => 10,
            'learning_type_id' => $learningType,
            'format_id' => $format,
            'certificate_type_id' => $certificateType,
        ])->assertCreated();

        $this->assertSame(1, $approver->fresh()->unreadNotificationsCount());
    }

    public function test_creating_an_event_notifies_admins_with_events_approve_permission(): void
    {
        $approver = $this->adminWithPermission('events.approve');
        [, $token] = $this->createOrganizationActor();

        $eventType = $this->choice('event_type');

        $this->api($token)->postJson('/api/events/', [
            'title_en' => 'Cycle Event', 'title_ar' => 'فعالية دورة اختبار',
            'description_en' => 'Cycle event description', 'description_ar' => 'وصف الفعالية',
            'event_type_id' => $eventType,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'due_date' => now()->addDays(4)->toDateTimeString(),
            'registration_required' => true,
            'participants_needed' => 10,
        ])->assertCreated();

        $this->assertSame(1, $approver->fresh()->unreadNotificationsCount());
    }

    public function test_a_new_organization_registration_notifies_admins_with_entities_approve_permission(): void
    {
        $approver = $this->adminWithPermission('entities.approve');

        app(AuthService::class)->register([
            'email' => 'neworg@fursa.test',
            'user_type' => 'organization',
            'password' => 'password123',
            'company_name' => 'New Org Co',
        ]);

        $this->assertSame(1, $approver->fresh()->unreadNotificationsCount());
    }

    private function choice(string $typeName, ?string $valueEn = null): int
    {
        $query = \App\Models\MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', $typeName));

        if ($valueEn) {
            $query->where('value_en', $valueEn);
        }

        return $query->firstOrFail()->id;
    }
}
