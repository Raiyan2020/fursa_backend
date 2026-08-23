<?php

namespace Tests\Feature;

use App\Enums\Nationality;
use App\Enums\UserType;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The client reported nationality missing from the admin panel: it was shown on
 * the detail screen but was absent from the create/edit form, so it could never
 * be set from the dashboard.
 */
class AdminUserNationalityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_edit_form_exposes_the_nationality_field(): void
    {
        $user = $this->volunteer();

        $this->actingAs($this->admin(), 'admin')
            ->get("/dashboard/users/{$user->id}/edit")
            ->assertOk()
            ->assertSee('name="nationality"', false)
            ->assertSee('name="civil_id"', false);
    }

    public function test_admin_can_set_nationality_and_civil_id(): void
    {
        $user = $this->volunteer();

        $this->actingAs($this->admin(), 'admin')
            ->put("/dashboard/users/{$user->id}", [
                'first_name' => 'Updated',
                'last_name' => 'Volunteer',
                'email' => $user->email,
                'account_type' => 'volunteer',
                'preferred_language' => 'en',
                'is_active' => 1,
                'nationality' => Nationality::KUWAITIS->value,
                'civil_id' => '292929292929',
            ])
            ->assertRedirect(route('admin.users.index'));

        $fresh = $user->fresh();
        $this->assertSame(Nationality::KUWAITIS->value, $fresh->nationality?->value ?? $fresh->nationality);
        $this->assertSame('292929292929', $fresh->civil_id);
    }

    public function test_unknown_nationality_is_rejected(): void
    {
        $user = $this->volunteer();

        $this->actingAs($this->admin(), 'admin')
            ->put("/dashboard/users/{$user->id}", [
                'first_name' => 'Updated',
                'last_name' => 'Volunteer',
                'email' => $user->email,
                'account_type' => 'volunteer',
                'preferred_language' => 'en',
                'nationality' => 'martian',
            ])
            ->assertSessionHasErrors('nationality');
    }

    public function test_admin_can_change_an_organization_email(): void
    {
        $org = User::query()->create([
            'email' => 'org.'.Str::lower(Str::random(6)).'@test.com',
            'password' => 'Password1',
            'password_length' => 9,
            'user_type' => UserType::ORGANIZATION,
            'first_name' => 'Org',
            'last_name' => 'Owner',
            'is_active' => true,
            'preferred_language' => 'en',
            'manual_id' => Str::random(22),
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->put("/dashboard/users/{$org->id}", [
                'first_name' => 'Org',
                'last_name' => 'Owner',
                'email' => 'changed-by-admin@test.com',
                'account_type' => 'organization',
                'preferred_language' => 'en',
                'is_active' => 1,
                'company_name' => 'Test Org',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('changed-by-admin@test.com', $org->fresh()->email);
    }

    protected function admin(): Admin
    {
        return Admin::query()->firstOrFail();
    }

    protected function volunteer(): User
    {
        return User::query()->create([
            'email' => 'vol.'.Str::lower(Str::random(6)).'@test.com',
            'password' => 'Password1',
            'password_length' => 9,
            'user_type' => UserType::VOLUNTEER,
            'first_name' => 'Test',
            'last_name' => 'Volunteer',
            'birth_year' => 1995,
            'is_active' => true,
            'preferred_language' => 'en',
            'manual_id' => Str::random(22),
        ]);
    }
}
