<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\UserRoleLicenseRequirement;
use App\Models\UserTypeApproval;
use App\Models\WhyFursaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class AdminFlowTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed();
    }

    public function test_admin_login_dashboard_and_logout_flow(): void
    {
        $this->get('/dashboard')->assertRedirect('/dashboard/login');
        $this->get('/dashboard/login')->assertOk();

        $this->post('/dashboard/login', [
            'email' => 'admin@fursa.local',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->post('/dashboard/login', [
            'email' => 'admin@fursa.local',
            'password' => 'Password1',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated('admin');
        $this->get('/dashboard')->assertOk()->assertViewIs('dashboard.index');
        $this->post('/dashboard/logout')->assertRedirect('/dashboard/login');
        $this->assertGuest('admin');
    }

    public function test_all_parameterless_admin_get_pages_render_for_an_admin(): void
    {
        $this->actingAs($this->adminActor(), 'admin');

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with($route->uri(), 'dashboard'))
            ->filter(fn (Route $route) => in_array('GET', $route->methods(), true))
            ->reject(fn (Route $route) => str_contains($route->uri(), '{'))
            ->reject(fn (Route $route) => $route->uri() === 'dashboard/login');

        foreach ($routes as $route) {
            $this->get('/'.$route->uri())->assertOk();
        }
    }

    public function test_pages_why_fursa_and_site_settings_crud_flow(): void
    {
        $this->actingAs($this->adminActor(), 'admin');

        $this->post('/dashboard/pages', [
            'slug' => 'cycle-reference',
            'title_en' => 'Cycle reference',
            'title_ar' => 'مرجع الدورة',
            'content_en' => 'Test content',
            'content_ar' => 'محتوى اختبار',
        ])->assertRedirect(route('admin.pages.index'));

        $page = Page::query()->where('slug', 'cycle-reference')->firstOrFail();
        $this->get('/dashboard/pages/'.$page->slug.'/edit')
            ->assertOk()
            ->assertViewIs('dashboard.pages.edit');
        $this->put('/dashboard/pages/'.$page->slug, [
            'slug' => 'cycle-reference',
            'title_en' => 'Updated cycle reference',
            'title_ar' => 'مرجع الدورة المحدث',
            'content_en' => 'Updated',
            'content_ar' => 'محدث',
        ])->assertRedirect(route('admin.pages.index'));
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'title_en' => 'Updated cycle reference',
        ]);

        $this->post('/dashboard/why-fursa', [
            'title_en' => 'Cycle tested',
            'title_ar' => 'دورة مختبرة',
            'sort_order' => 50,
            'icon' => UploadedFile::fake()->image('cycle.png'),
        ])->assertRedirect(route('admin.why-fursa.index'));

        $item = WhyFursaItem::query()->where('title_en', 'Cycle tested')->firstOrFail();
        $this->assertTrue(Storage::disk('public')->exists($item->icon));
        $this->put('/dashboard/why-fursa/'.$item->id, [
            'title_en' => 'Cycle verified',
            'title_ar' => 'دورة مؤكدة',
            'sort_order' => 51,
        ])->assertRedirect(route('admin.why-fursa.index'));
        $this->delete('/dashboard/why-fursa/'.$item->id)->assertRedirect();
        $this->assertTrue($item->fresh()->is_deleted);

        $this->put('/dashboard/site-settings', [
            'tiktok_url' => 'https://tiktok.com/@forsa',
            'twitter_url' => 'https://x.com/forsa',
            'youtube_url' => 'https://youtube.com/@forsa',
            'instagram_url' => 'https://instagram.com/forsa',
            'copyright_en' => 'Forsa rights',
            'copyright_ar' => 'حقوق فرصة',
            'contact_email' => 'contact@joinforsa.net',
            'contact_phone' => '+96512345678',
            'contact_whatsapp' => '+96512345678',
            'contact_address_en' => 'Kuwait City',
            'contact_address_ar' => 'مدينة الكويت',
            'contact_page_text_en' => 'Reach the Forsa team anytime.',
            'contact_page_text_ar' => 'تواصل مع فريق فرصة في أي وقت.',
        ])->assertRedirect();

        $settings = SiteSetting::current();
        $this->assertSame('contact@joinforsa.net', $settings->contact_email);
        $this->assertSame('+96512345678', $settings->contact_phone);
        $this->assertSame('مدينة الكويت', $settings->contact_address_ar);

        $this->delete('/dashboard/pages/'.$page->slug)->assertRedirect();
        $this->assertTrue($page->fresh()->is_deleted);
    }

    public function test_admin_license_requirement_update_validates_and_persists_checkbox_state(): void
    {
        $this->actingAs($this->adminActor(), 'admin');

        $requirement = UserRoleLicenseRequirement::query()->firstOrFail();
        $requirement->update(['license_required' => true]);

        $this->put('/dashboard/license-requirements/'.$requirement->id, [
            'license_required' => 'invalid',
        ])->assertSessionHasErrors('license_required');

        $this->put('/dashboard/license-requirements/'.$requirement->id, [])
            ->assertRedirect();

        $this->assertFalse((bool) $requirement->fresh()->license_required);

        $this->put('/dashboard/license-requirements/'.$requirement->id, [
            'license_required' => '1',
        ])->assertRedirect();

        $this->assertTrue((bool) $requirement->fresh()->license_required);
    }

    public function test_admin_user_type_approval_update_validates_and_persists_checkbox_state(): void
    {
        $this->actingAs($this->adminActor(), 'admin');

        $approval = UserTypeApproval::query()->firstOrFail();
        $approval->update(['requires_approval' => true]);

        $this->put('/dashboard/user-type-approvals/'.$approval->id, [
            'requires_approval' => 'invalid',
        ])->assertSessionHasErrors('requires_approval');

        $this->put('/dashboard/user-type-approvals/'.$approval->id, [])
            ->assertRedirect();

        $this->assertFalse((bool) $approval->fresh()->requires_approval);

        $this->put('/dashboard/user-type-approvals/'.$approval->id, [
            'requires_approval' => '1',
        ])->assertRedirect();

        $this->assertTrue((bool) $approval->fresh()->requires_approval);
    }

    public function test_admin_sponsors_crud_flow(): void
    {
        $this->actingAs($this->adminActor(), 'admin');

        $this->get('/dashboard/sponsors/create')->assertOk();

        $this->post('/dashboard/sponsors', [])
            ->assertSessionHasErrors(['org_name', 'person_name', 'email', 'approval_status']);

        $this->post('/dashboard/sponsors', [
            'org_name' => 'STC',
            'person_name' => 'Sponsor Contact',
            'email' => 'stc.sponsor@fursa.test',
            'country_code' => 'Doloribus soluta ut',
            'approval_status' => 'approved',
        ])->assertSessionHasErrors('country_code');

        $logo = UploadedFile::fake()->image('stc-logo.png', 200, 100);

        $this->post('/dashboard/sponsors', [
            'org_name' => 'STC',
            'person_name' => 'Sponsor Contact',
            'email' => 'stc.sponsor@fursa.test',
            'country_code' => '+965',
            'phone_number' => '50000000',
            'sponsorship_details' => 'Homepage sponsor',
            'preferred_language' => 'en',
            'approval_status' => 'approved',
            'sponsor_logo' => $logo,
        ])->assertRedirect(route('admin.sponsors.index'));

        $sponsor = \App\Models\Sponsor::query()->where('email', 'stc.sponsor@fursa.test')->firstOrFail();

        $this->assertSame('approved', $sponsor->approval_status->value);
        $this->assertNotEmpty($sponsor->sponsor_logo);
        Storage::disk('public')->assertExists(normalize_storage_path($sponsor->sponsor_logo));

        $this->get('/dashboard/sponsors/'.$sponsor->id)->assertOk();
        $this->get('/dashboard/sponsors/'.$sponsor->id.'/edit')->assertOk();

        $this->put('/dashboard/sponsors/'.$sponsor->id, [
            'org_name' => 'STC Updated',
            'person_name' => 'Updated Contact',
            'email' => 'stc.sponsor@fursa.test',
            'preferred_language' => 'ar',
            'approval_status' => 'pending',
        ])->assertRedirect(route('admin.sponsors.index'));

        $this->assertDatabaseHas('sponsors', [
            'id' => $sponsor->id,
            'org_name' => 'STC Updated',
            'approval_status' => 'pending',
        ]);

        $this->delete('/dashboard/sponsors/'.$sponsor->id)->assertRedirect();
        $this->assertTrue((bool) $sponsor->fresh()->is_deleted);
    }

    public function test_admin_events_crud_flow(): void
    {
        $this->actingAs($this->adminActor(), 'admin');

        [$orgUser] = $this->createOrganizationActor('event-org@fursa.test');
        $org = $orgUser->organizationProfile;

        $this->get('/dashboard/events/create')->assertOk();

        $this->post('/dashboard/events', [])
            ->assertSessionHasErrors([
                'created_by',
                'title_en',
                'title_ar',
                'description_en',
                'description_ar',
                'start_date',
                'end_date',
                'approval_status',
                'event_status',
            ]);

        $image = UploadedFile::fake()->image('yv-connect.png', 800, 400);

        $this->post('/dashboard/events', [
            'created_by' => $org->id,
            'title_en' => 'YV CONNECT',
            'title_ar' => 'YV CONNECT',
            'description_en' => 'What is the idea?',
            'description_ar' => 'شنو فكرته؟',
            'start_date' => '2026-07-29',
            'end_date' => '2026-07-29',
            'start_time' => '16:00',
            'end_time' => '22:00',
            'location_en' => 'Kuwait National Library',
            'location_ar' => 'مكتبة الكويت الوطنية',
            'from_age' => 15,
            'participants_needed' => 100,
            'registration_required' => '1',
            'preferred_language' => 'ar',
            'primary_language' => 'ar',
            'approval_status' => 'approved',
            'event_status' => 'upcoming',
            'images' => [$image],
        ])->assertRedirect(route('admin.events.index'));

        $event = \App\Models\Event::query()->where('title_en', 'YV CONNECT')->firstOrFail();
        $this->assertSame('approved', $event->approval_status->value);
        $this->assertSame($org->id, $event->created_by);
        $this->assertTrue($event->images()->exists());

        $this->get('/dashboard/events/'.$event->id)->assertOk();
        $this->get('/dashboard/events/'.$event->id.'/edit')->assertOk();

        $this->put('/dashboard/events/'.$event->id, [
            'created_by' => $org->id,
            'title_en' => 'YV CONNECT Updated',
            'title_ar' => 'YV CONNECT',
            'description_en' => 'Updated description',
            'description_ar' => 'وصف محدث',
            'start_date' => '2026-07-29',
            'end_date' => '2026-07-29',
            'from_age' => 15,
            'approval_status' => 'approved',
            'event_status' => 'upcoming',
        ])->assertRedirect(route('admin.events.index'));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title_en' => 'YV CONNECT Updated',
        ]);

        $this->delete('/dashboard/events/'.$event->id)->assertRedirect();
        $this->assertTrue((bool) $event->fresh()->is_deleted);
    }

    public function test_admin_volunteer_opportunities_crud_flow(): void
    {
        $this->actingAs($this->adminActor(), 'admin');

        [$orgUser] = $this->createOrganizationActor('volunteer-org@fursa.test');

        $this->get('/dashboard/volunteer-opportunities/create')->assertOk();

        $this->post('/dashboard/volunteer-opportunities', [])
            ->assertSessionHasErrors([
                'created_by',
                'title_en',
                'title_ar',
                'description_en',
                'description_ar',
                'start_date',
                'end_date',
                'participants_needed',
                'approval_status',
                'opportunity_status',
            ]);

        $image = UploadedFile::fake()->image('volunteer.png', 800, 400);

        $this->post('/dashboard/volunteer-opportunities', [
            'created_by' => $orgUser->id,
            'title_en' => 'Beach Cleanup',
            'title_ar' => 'تنظيف الشاطئ',
            'description_en' => 'Clean the beach',
            'description_ar' => 'تنظيف الشاطئ',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-02',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'location_en' => 'Salmiya Beach',
            'location_ar' => 'شاطئ السالمية',
            'from_age' => 16,
            'participants_needed' => 20,
            'volunteer_hours_per_day' => 4,
            'preferred_language' => 'ar',
            'primary_language' => 'ar',
            'approval_status' => 'approved',
            'opportunity_status' => 'upcoming',
            'is_public' => '1',
            'images' => [$image],
        ])->assertRedirect(route('admin.volunteer-opportunities.index'));

        $opportunity = \App\Models\VolunteerOpportunity::query()->where('title_en', 'Beach Cleanup')->firstOrFail();
        $this->assertSame('approved', $opportunity->approval_status->value);
        $this->assertSame($orgUser->id, $opportunity->created_by);
        $this->assertTrue($opportunity->images()->exists());

        $this->get('/dashboard/volunteer-opportunities/'.$opportunity->id)->assertOk();
        $this->get('/dashboard/volunteer-opportunities/'.$opportunity->id.'/edit')->assertOk();

        $this->put('/dashboard/volunteer-opportunities/'.$opportunity->id, [
            'created_by' => $orgUser->id,
            'title_en' => 'Beach Cleanup Updated',
            'title_ar' => 'تنظيف الشاطئ',
            'description_en' => 'Updated description',
            'description_ar' => 'وصف محدث',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-02',
            'participants_needed' => 25,
            'approval_status' => 'approved',
            'opportunity_status' => 'upcoming',
        ])->assertRedirect(route('admin.volunteer-opportunities.index'));

        $this->assertDatabaseHas('volunteer_opportunities', [
            'id' => $opportunity->id,
            'title_en' => 'Beach Cleanup Updated',
            'participants_needed' => 25,
        ]);

        $imageId = $opportunity->images()->firstOrFail()->id;
        $this->delete('/dashboard/volunteer-opportunities/'.$opportunity->id.'/images/'.$imageId)->assertRedirect();
        $this->assertTrue((bool) \App\Models\OpportunityImage::query()->findOrFail($imageId)->is_deleted);

        $this->delete('/dashboard/volunteer-opportunities/'.$opportunity->id)->assertRedirect();
        $this->assertTrue((bool) $opportunity->fresh()->is_deleted);
    }

    public function test_admin_learn_serve_opportunities_crud_flow(): void
    {
        $this->actingAs($this->adminActor(), 'admin');

        [$orgUser] = $this->createOrganizationActor('learn-org@fursa.test');

        $this->get('/dashboard/learn-serve-opportunities/create')->assertOk();

        $this->post('/dashboard/learn-serve-opportunities', [])
            ->assertSessionHasErrors([
                'created_by',
                'title_en',
                'title_ar',
                'description_en',
                'description_ar',
                'start_date',
                'end_date',
                'participants_needed',
                'approval_status',
                'opportunity_status',
            ]);

        $image = UploadedFile::fake()->image('learn.png', 800, 400);

        $this->post('/dashboard/learn-serve-opportunities', [
            'created_by' => $orgUser->id,
            'title_en' => 'First Aid Workshop',
            'title_ar' => 'ورشة إسعافات أولية',
            'description_en' => 'Learn first aid',
            'description_ar' => 'تعلم الإسعافات الأولية',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'start_time' => '10:00',
            'end_time' => '14:00',
            'location_en' => 'Training Center',
            'location_ar' => 'مركز التدريب',
            'from_age' => 18,
            'participants_needed' => 30,
            'preferred_language' => 'ar',
            'primary_language' => 'ar',
            'approval_status' => 'approved',
            'opportunity_status' => 'upcoming',
            'images' => [$image],
        ])->assertRedirect(route('admin.learn-serve-opportunities.index'));

        $opportunity = \App\Models\LearnServeOpportunity::query()->where('title_en', 'First Aid Workshop')->firstOrFail();
        $this->assertSame('approved', $opportunity->approval_status->value);
        $this->assertSame($orgUser->id, $opportunity->created_by);
        $this->assertTrue($opportunity->images()->exists());

        $this->get('/dashboard/learn-serve-opportunities/'.$opportunity->id)->assertOk();
        $this->get('/dashboard/learn-serve-opportunities/'.$opportunity->id.'/edit')->assertOk();

        $this->put('/dashboard/learn-serve-opportunities/'.$opportunity->id, [
            'created_by' => $orgUser->id,
            'title_en' => 'First Aid Workshop Updated',
            'title_ar' => 'ورشة إسعافات أولية',
            'description_en' => 'Updated description',
            'description_ar' => 'وصف محدث',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'participants_needed' => 35,
            'approval_status' => 'approved',
            'opportunity_status' => 'upcoming',
        ])->assertRedirect(route('admin.learn-serve-opportunities.index'));

        $this->assertDatabaseHas('learn_serve_opportunities', [
            'id' => $opportunity->id,
            'title_en' => 'First Aid Workshop Updated',
            'participants_needed' => 35,
        ]);

        $imageId = $opportunity->images()->firstOrFail()->id;
        $this->delete('/dashboard/learn-serve-opportunities/'.$opportunity->id.'/images/'.$imageId)->assertRedirect();
        $this->assertTrue((bool) \App\Models\OpportunityImage::query()->findOrFail($imageId)->is_deleted);

        $this->delete('/dashboard/learn-serve-opportunities/'.$opportunity->id)->assertRedirect();
        $this->assertTrue((bool) $opportunity->fresh()->is_deleted);
    }

    public function test_admin_users_create_with_volunteer_team_and_email_update(): void
    {
        $this->actingAs($this->adminActor(), 'admin');

        $this->get('/dashboard/users/create')->assertOk();

        $this->post('/dashboard/users', [])
            ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'password', 'account_type', 'preferred_language']);

        $this->post('/dashboard/users', [
            'first_name' => 'Team',
            'last_name' => 'Leader',
            'email' => 'volunteer-team@fursa.test',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'account_type' => 'volunteer_team',
            'preferred_language' => 'ar',
            'company_name' => 'Youth Volunteer Team',
            'nickname' => 'yv_team',
            'is_active' => '1',
        ])->assertRedirect(route('admin.users.index'));

        $user = \App\Models\User::query()->where('email', 'volunteer-team@fursa.test')->firstOrFail();
        $this->assertSame('organization', $user->user_type->value);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->organizationProfile);
        $this->assertSame('volunteer_team', $user->accountTypeKey());
        $this->assertSame('Youth Volunteer Team', $user->organizationProfile->company_name);

        $this->put('/dashboard/users/'.$user->id, [
            'first_name' => 'Team',
            'last_name' => 'Leader',
            'email' => 'team-new-email@fursa.test',
            'account_type' => 'volunteer_team',
            'preferred_language' => 'ar',
            'company_name' => 'Youth Volunteer Team',
            'nickname' => 'yv_team',
            'is_active' => '1',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'team-new-email@fursa.test',
        ]);
    }

    public function test_admin_users_excel_export_downloads(): void
    {
        $this->actingAs($this->adminActor(), 'admin');
        $this->createOrganizationActor('export-org@fursa.test');

        $this->get('/dashboard/users/export')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_admin_banner_schedule_dates_persist(): void
    {
        $this->actingAs($this->adminActor(), 'admin');

        $image = UploadedFile::fake()->image('banner.png', 800, 300);

        $this->post('/dashboard/banners', [
            'name' => 'Campaign Banner',
            'banner_url' => 'https://example.com',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'image' => $image,
        ])->assertRedirect(route('admin.banners.index'));

        $banner = \App\Models\BannerImage::query()->where('name', 'Campaign Banner')->firstOrFail();
        $this->assertSame('2026-08-01', $banner->start_date->format('Y-m-d'));
        $this->assertSame('2026-08-31', $banner->end_date->format('Y-m-d'));
    }

    public function test_admin_login_remember_me_keeps_session(): void
    {
        $this->post('/dashboard/login', [
            'email' => 'admin@fursa.local',
            'password' => 'Password1',
            'remember' => '1',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated('admin');
        $this->assertNotEmpty(auth('admin')->user()->getRememberToken());
    }
}
