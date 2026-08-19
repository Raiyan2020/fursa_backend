<?php

namespace Tests\Feature;

use App\Models\MasterChoice;
use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AssertsDjangoApiEnvelope;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use AssertsDjangoApiEnvelope;
    use RefreshDatabase;

    public function test_register_login_and_account_flow(): void
    {
        config(['mail.default' => 'array']);
        $this->seed();

        $register = $this->postJson('/api/register/', [
            'email' => 'volunteer1@test.com',
            'password' => 'Password1',
            'user_type' => 'volunteer',
            'first_name' => 'Ahmed',
            'last_name' => 'Arafa',
            'civil_id' => '123456789012',
            'birth_year' => 1995,
        ]);

        $this->assertSuccessEnvelope($register, 201, 'OTP has been sent to the email address.');
        $this->assertGreaterThan(
            0,
            Mail::mailer('array')->getSymfonyTransport()->messages()->count()
        );

        $otp = OtpVerification::query()->latest('id')->value('otp');
        $this->assertNotEmpty($otp);

        $verify = $this->postJson('/api/verify_otp_or_token/', [
            'email' => 'volunteer1@test.com',
            'type' => 'register',
            'otp' => $otp,
        ]);

        $this->assertSuccessEnvelope($verify, 200, 'OTP verified successfully.');
        $verify->assertJsonPath(
            'data.user_id',
            User::query()->where('email', 'volunteer1@test.com')->value('id')
        );

        $login = $this->postJson('/api/login/', [
            'email' => 'volunteer1@test.com',
            'password' => 'Password1',
            'rememberMe' => true,
        ]);

        $this->assertSuccessEnvelope($login, 200, 'Login successful.');
        $token = $login->json('data.data.auth_token');
        $this->assertNotEmpty($token);

        $account = $this->withHeader('Authorization', 'Token '.$token)
            ->getJson('/api/account/');

        $this->assertSuccessEnvelope($account, 200, 'Account information retrieved');
        $account->assertJsonPath('data.email', 'volunteer1@test.com')
            ->assertJsonStructure(['data' => ['full_name', 'manual_id', 'password_length']]);

        $this->getJson('/api/choices/gender/')
            ->assertOk()
            ->assertJsonPath('key', 'success');

        $this->getJson('/health/')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('service', 'fursa_backend')
            ->assertJsonPath('database', 'connected');
    }

    public function test_forgot_password_rejects_unregistered_email(): void
    {
        $this->seed();

        $response = $this->postJson('/api/forgot-password/', [
            'email' => 'not.registered@example.com',
        ], ['Lang' => 'ar']);

        $this->assertErrorEnvelope($response, 404);
        $response->assertJsonPath('msg', 'البريد الإلكتروني غير مسجل لدينا. يرجى إنشاء حساب جديد.');
        $this->assertStringContainsString(
            'غير مسجل',
            implode(' ', $response->json('response_status.validation_errors.email'))
        );
    }

    public function test_register_rejects_duplicate_emergency_civil_id(): void
    {
        $this->seed();

        $response = $this->postJson('/api/register/', [
            'email' => 'dup.civil@test.com',
            'password' => 'Password1',
            'user_type' => 'volunteer',
            'civil_id' => '232323232323',
            'emergency_contact_civil_id' => '232323232323',
            'birth_year' => 1995,
        ], ['Lang' => 'ar']);

        $this->assertErrorEnvelope($response, 422);
        $this->assertNotEmpty(
            $response->json('response_status.validation_errors.emergency_contact_civil_id')
        );
        $this->assertStringContainsString(
            'الرقم المدني',
            implode(' ', $response->json('response_status.validation_errors.emergency_contact_civil_id'))
        );
    }

    public function test_register_duplicate_civil_id_uses_translated_attribute(): void
    {
        $this->seed();

        $this->postJson('/api/register/', [
            'email' => 'first.civil@test.com',
            'password' => 'Password1',
            'user_type' => 'volunteer',
            'civil_id' => '232323232323',
            'birth_year' => 1995,
        ]);

        $response = $this->postJson('/api/register/', [
            'email' => 'second.civil@test.com',
            'password' => 'Password1',
            'user_type' => 'volunteer',
            'civil_id' => '232323232323',
            'birth_year' => 1995,
        ], ['Lang' => 'ar']);

        $this->assertErrorEnvelope($response, 422);
        $message = implode(' ', $response->json('response_status.validation_errors.civil_id'));
        $this->assertStringContainsString('الرقم المدني', $message);
        $this->assertStringNotContainsString('civil_id', $message);
    }

    public function test_volunteer_profile_update_persists_first_and_last_name(): void
    {
        $this->seed();

        $register = $this->postJson('/api/register/', [
            'email' => 'profile.update@test.com',
            'password' => 'Password1',
            'user_type' => 'volunteer',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'civil_id' => '123456789013',
            'birth_year' => 1995,
        ]);
        $this->assertSuccessEnvelope($register, 201);

        $otp = OtpVerification::query()->latest('id')->value('otp');

        $this->postJson('/api/verify_otp_or_token/', [
            'email' => 'profile.update@test.com',
            'type' => 'register',
            'otp' => $otp,
        ])->assertOk();

        $login = $this->postJson('/api/login/', [
            'email' => 'profile.update@test.com',
            'password' => 'Password1',
        ]);
        $this->assertSuccessEnvelope($login, 200, 'Login successful.');

        $token = $login->json('data.data.auth_token');

        $response = $this->withHeaders([
            'Authorization' => 'Token '.$token,
            'Accept' => 'application/json',
            'Lang' => 'en',
        ])->patchJson('/api/volunteer-profile/', [
            'first_name' => 'New',
            'last_name' => 'Volunteer',
            'civil_id' => '123456789013',
        ]);

        $this->assertSuccessEnvelope($response, 200, 'Volunteer profile updated successfully.');

        $user = User::query()->where('email', 'profile.update@test.com')->firstOrFail();
        $this->assertSame('New', $user->first_name);
        $this->assertSame('Volunteer', $user->last_name);

        $account = $this->withHeader('Authorization', 'Token '.$token)
            ->getJson('/api/account/');
        $account->assertOk()
            ->assertJsonPath('data.first_name', 'New')
            ->assertJsonPath('data.last_name', 'Volunteer')
            ->assertJsonPath('data.full_name', 'New Volunteer');
    }

    public function test_volunteer_profile_update_persists_user_interest_tags(): void
    {
        $this->seed();

        $interestIds = MasterChoice::query()
            ->whereHas('choiceType', fn ($query) => $query->where('name', 'user_interest'))
            ->orderBy('id')
            ->limit(2)
            ->pluck('id')
            ->all();

        $this->assertCount(2, $interestIds);

        $register = $this->postJson('/api/register/', [
            'email' => 'profile.interests@test.com',
            'password' => 'Password1',
            'user_type' => 'volunteer',
            'civil_id' => '123456789014',
            'birth_year' => 1995,
        ]);
        $this->assertSuccessEnvelope($register, 201);

        $otp = OtpVerification::query()->latest('id')->value('otp');

        $this->postJson('/api/verify_otp_or_token/', [
            'email' => 'profile.interests@test.com',
            'type' => 'register',
            'otp' => $otp,
        ])->assertOk();

        $login = $this->postJson('/api/login/', [
            'email' => 'profile.interests@test.com',
            'password' => 'Password1',
        ]);
        $this->assertSuccessEnvelope($login, 200, 'Login successful.');

        $token = $login->json('data.data.auth_token');

        $response = $this->withHeaders([
            'Authorization' => 'Token '.$token,
            'Accept' => 'application/json',
            'Lang' => 'en',
        ])->patchJson('/api/volunteer-profile/', [
            'civil_id' => '123456789014',
            'interests' => $interestIds,
        ]);

        $this->assertSuccessEnvelope($response, 200, 'Volunteer profile updated successfully.');
        $response->assertJsonPath('data.interest_display.0.id', $interestIds[0])
            ->assertJsonPath('data.interest_display.1.id', $interestIds[1]);

        $user = User::query()->where('email', 'profile.interests@test.com')->firstOrFail();
        $this->assertEqualsCanonicalizing($interestIds, $user->masterInterests()->pluck('master_choices.id')->all());

        $profile = $this->withHeader('Authorization', 'Token '.$token)
            ->getJson('/api/volunteer-profile/');
        $profile->assertOk()
            ->assertJsonPath('data.interest_display.0.id', $interestIds[0])
            ->assertJsonPath('data.interest_display.1.id', $interestIds[1]);
    }

    public function test_volunteer_profile_update_persists_phone_number(): void
    {
        $this->seed();

        $register = $this->postJson('/api/register/', [
            'email' => 'profile.phone@test.com',
            'password' => 'Password1',
            'user_type' => 'volunteer',
            'civil_id' => '123456789015',
            'birth_year' => 1995,
        ]);
        $this->assertSuccessEnvelope($register, 201);

        $otp = OtpVerification::query()->latest('id')->value('otp');
        $this->postJson('/api/verify_otp_or_token/', [
            'email' => 'profile.phone@test.com',
            'type' => 'register',
            'otp' => $otp,
        ])->assertOk();

        $login = $this->postJson('/api/login/', [
            'email' => 'profile.phone@test.com',
            'password' => 'Password1',
        ]);
        $token = $login->json('data.data.auth_token');

        // First save — set phone number
        $response = $this->withHeaders([
            'Authorization' => 'Token '.$token,
            'Accept' => 'application/json',
            'Lang' => 'en',
        ])->patchJson('/api/volunteer-profile/', [
            'civil_id' => '123456789015',
            'phone_number' => '55001122',
            'country_code' => '+965',
        ]);
        $this->assertSuccessEnvelope($response, 200, 'Volunteer profile updated successfully.');
        $response->assertJsonPath('data.phone_number', '55001122')
            ->assertJsonPath('data.country_code', '+965');

        // Second save — change phone number
        $response2 = $this->withHeaders([
            'Authorization' => 'Token '.$token,
            'Accept' => 'application/json',
            'Lang' => 'en',
        ])->patchJson('/api/volunteer-profile/', [
            'civil_id' => '123456789015',
            'phone_number' => '99887766',
            'country_code' => '+965',
        ]);
        $this->assertSuccessEnvelope($response2, 200, 'Volunteer profile updated successfully.');
        $response2->assertJsonPath('data.phone_number', '99887766');

        // Reload from DB — must reflect new number
        $user = User::query()->where('email', 'profile.phone@test.com')->firstOrFail();
        $this->assertSame('99887766', $user->phone_number);

        // GET profile — must also return new number
        $profile = $this->withHeader('Authorization', 'Token '.$token)
            ->getJson('/api/volunteer-profile/');
        $profile->assertOk()
            ->assertJsonPath('data.phone_number', '99887766');
    }

    public function test_volunteer_profile_update_persists_emergency_relationship_and_civil_id(): void
    {
        $this->seed();

        // Pick a valid emergency_contact_relationship master choice
        $relId = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'emergency_contact_relationship'))
            ->orderBy('id')
            ->value('id');

        // If this choice type doesn't exist in seeder, skip gracefully
        if (! $relId) {
            $this->markTestSkipped('No emergency_contact_relationship master choices seeded.');
        }

        $register = $this->postJson('/api/register/', [
            'email' => 'profile.emergency@test.com',
            'password' => 'Password1',
            'user_type' => 'volunteer',
            'civil_id' => '123456789016',
            'birth_year' => 1995,
        ]);
        $this->assertSuccessEnvelope($register, 201);

        $otp = OtpVerification::query()->latest('id')->value('otp');
        $this->postJson('/api/verify_otp_or_token/', [
            'email' => 'profile.emergency@test.com',
            'type' => 'register',
            'otp' => $otp,
        ])->assertOk();

        $token = $this->postJson('/api/login/', [
            'email' => 'profile.emergency@test.com',
            'password' => 'Password1',
        ])->json('data.data.auth_token');

        $headers = ['Authorization' => 'Token '.$token, 'Accept' => 'application/json', 'Lang' => 'en'];

        // Set relationship and civil id
        $this->withHeaders($headers)->patchJson('/api/volunteer-profile/', [
            'civil_id' => '123456789016',
            'emergency_contact_relationship' => $relId,
            'emergency_contact_civil_id' => '999888777666',
        ])->assertSuccessful();

        // Change both
        $response = $this->withHeaders($headers)->patchJson('/api/volunteer-profile/', [
            'civil_id' => '123456789016',
            'emergency_contact_relationship' => $relId,
            'emergency_contact_civil_id' => '111222333444',
        ]);
        $this->assertSuccessEnvelope($response, 200, 'Volunteer profile updated successfully.');

        $user = User::query()->where('email', 'profile.emergency@test.com')->firstOrFail();
        $this->assertSame($relId, $user->emergency_contact_relationship_id);
        $this->assertSame('111222333444', $user->emergency_contact_civil_id);

        // GET profile — must return updated values
        $profile = $this->withHeaders($headers)->getJson('/api/volunteer-profile/');
        $profile->assertOk()
            ->assertJsonPath('data.emergency_contact_relationship', $relId)
            ->assertJsonPath('data.emergency_contact_civil_id', '111222333444');
    }
}
