<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * The dashboard had no password recovery at all, which the client flagged as a
 * critical gap.
 */
class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_forgot_password_page_is_reachable_for_a_guest(): void
    {
        $this->get('/dashboard/forgot-password')
            ->assertOk()
            ->assertSee('forgot-password', false);
    }

    public function test_login_page_links_to_the_reset_flow(): void
    {
        $this->get('/dashboard/login')
            ->assertOk()
            ->assertSee('forgot-password', false);
    }

    public function test_reset_link_is_emailed_to_an_active_admin(): void
    {
        Notification::fake();

        $admin = $this->activeAdmin();

        $this->post('/dashboard/forgot-password', ['email' => $admin->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($admin, AdminResetPasswordNotification::class);
    }

    public function test_unknown_email_does_not_reveal_whether_an_admin_exists(): void
    {
        Notification::fake();

        $this->post('/dashboard/forgot-password', ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_inactive_admin_cannot_request_a_reset(): void
    {
        Notification::fake();

        $admin = $this->activeAdmin();
        $admin->forceFill(['is_active' => false])->save();

        $this->post('/dashboard/forgot-password', ['email' => $admin->email])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_admin_can_complete_a_password_reset(): void
    {
        $admin = $this->activeAdmin();
        $token = Password::broker('admins')->createToken($admin);

        $this->post('/dashboard/reset-password', [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'BrandNewPass1',
            'password_confirmation' => 'BrandNewPass1',
        ])->assertRedirect(route('admin.login'));

        $this->assertTrue(Hash::check('BrandNewPass1', $admin->fresh()->password));
    }

    public function test_a_tampered_token_is_rejected(): void
    {
        $admin = $this->activeAdmin();

        $this->post('/dashboard/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $admin->email,
            'password' => 'BrandNewPass1',
            'password_confirmation' => 'BrandNewPass1',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('BrandNewPass1', $admin->fresh()->password));
    }

    public function test_reset_requires_a_matching_confirmation(): void
    {
        $admin = $this->activeAdmin();
        $token = Password::broker('admins')->createToken($admin);

        $this->post('/dashboard/reset-password', [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'BrandNewPass1',
            'password_confirmation' => 'SomethingElse1',
        ])->assertSessionHasErrors('password');
    }

    public function test_admin_reset_tokens_live_in_their_own_table(): void
    {
        $admin = $this->activeAdmin();
        Password::broker('admins')->createToken($admin);

        $this->assertDatabaseHas('admin_password_reset_tokens', ['email' => $admin->email]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $admin->email]);
    }

    protected function activeAdmin(): Admin
    {
        /** @var Admin $admin */
        $admin = Admin::query()->firstOrFail();
        $admin->forceFill(['is_active' => true])->save();

        return $admin;
    }
}
