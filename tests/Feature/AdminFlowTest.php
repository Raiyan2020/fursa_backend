<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SiteSetting;
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
        ])->assertRedirect();

        $this->assertSame('contact@joinforsa.net', SiteSetting::current()->contact_email);

        $this->delete('/dashboard/pages/'.$page->slug)->assertRedirect();
        $this->assertTrue($page->fresh()->is_deleted);
    }
}
