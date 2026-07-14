<?php

namespace Tests\Feature;

use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsDjangoApiEnvelope;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use AssertsDjangoApiEnvelope;
    use RefreshDatabase;

    public function test_register_login_and_account_flow(): void
    {
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

        $otp = OtpVerification::query()->latest('id')->value('otp');
        $this->assertNotEmpty($otp);

        $verify = $this->postJson('/api/verify_otp_or_token/', [
            'email' => 'volunteer1@test.com',
            'type' => 'register',
            'otp' => $otp,
        ]);

        $this->assertSuccessEnvelope($verify, 200, 'OTP verified successfully.');
        $verify->assertJsonPath('data.user_id', User::first()->id);

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
            ->assertJsonPath('status', 'success');

        $this->getJson('/health/')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('service', 'fursa_backend')
            ->assertJsonPath('database', 'connected');
    }
}
