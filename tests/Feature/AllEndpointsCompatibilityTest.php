<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\UserType;
use App\Models\ExpiringToken;
use App\Models\Faq;
use App\Models\OrganizationProfile;
use App\Models\OtpVerification;
use App\Models\TokenVerification;
use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AssertsDjangoApiEnvelope;
use Tests\TestCase;

class AllEndpointsCompatibilityTest extends TestCase
{
    use AssertsDjangoApiEnvelope;
    use RefreshDatabase;

    protected User $volunteer;

    protected string $volunteerToken;

    protected User $organization;

    protected string $organizationToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        [$this->volunteer, $this->volunteerToken] = $this->createActiveVolunteer('vol@test.com', '123456789001');
        [$this->organization, $this->organizationToken] = $this->createActiveOrganization('org@test.com');
    }

    public function test_health_matches_django_shape(): void
    {
        $this->getJson('/health/')
            ->assertOk()
            ->assertExactJson([
                'status' => 'healthy',
                'service' => 'fursa_backend',
                'database' => 'connected',
            ]);
    }

    public function test_register_envelope_and_user_fields(): void
    {
        $response = $this->postJson('/api/register/', [
            'email' => 'new.volunteer@test.com',
            'password' => 'Password1',
            'user_type' => 'volunteer',
            'first_name' => 'Sara',
            'last_name' => 'Ali',
            'civil_id' => '123456789099',
            'birth_year' => 1998,
        ]);

        $this->assertSuccessEnvelope($response, 201, 'OTP has been sent to the email address.');
        $response->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'first_name',
                    'last_name',
                    'user_type',
                    'civil_id',
                    'manual_id',
                    'is_social_login',
                    'is_banned',
                    'preferred_language',
                ],
            ])
            ->assertJsonPath('data.email', 'new.volunteer@test.com')
            ->assertJsonPath('data.user_type', 'volunteer')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.auth_token');

        $this->assertFalse(User::query()->where('email', 'new.volunteer@test.com')->value('is_active'));
        $this->assertDatabaseHas('otp_verifications', [
            'user_id' => User::query()->where('email', 'new.volunteer@test.com')->value('id'),
        ]);
    }

    public function test_register_validation_error_envelope(): void
    {
        $response = $this->postJson('/api/register/', [
            'email' => 'bad',
            'password' => 'short',
            'user_type' => 'volunteer',
        ]);

        $this->assertErrorEnvelope($response, 422);
        $response->assertJsonStructure(['response_status' => ['validation_errors']]);
    }

    public function test_verify_otp_register_matches_django(): void
    {
        $this->postJson('/api/register/', [
            'email' => 'otp.user@test.com',
            'password' => 'Password1',
            'user_type' => 'volunteer',
            'civil_id' => '123456789088',
            'birth_year' => 1990,
        ])->assertCreated();

        $user = User::query()->where('email', 'otp.user@test.com')->firstOrFail();
        $otp = OtpVerification::query()->where('user_id', $user->id)->latest('id')->value('otp');

        $response = $this->postJson('/api/verify_otp_or_token/', [
            'email' => 'otp.user@test.com',
            'type' => 'register',
            'otp' => $otp,
        ]);

        $this->assertSuccessEnvelope($response, 200, 'OTP verified successfully.');
        $response->assertJsonStructure(['data' => ['token', 'user_id']])
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_login_wraps_user_in_data_data_like_django(): void
    {
        $response = $this->postJson('/api/login/', [
            'email' => 'vol@test.com',
            'password' => 'Password1',
            'rememberMe' => true,
        ]);

        $this->assertSuccessEnvelope($response, 200, 'Login successful.');
        $response->assertJsonStructure([
                'data' => [
                    'data' => [
                        'id',
                        'email',
                        'auth_token',
                        'user_type',
                        'manual_id',
                        'is_social_login',
                    ],
                ],
            ])
            ->assertJsonPath('data.data.email', 'vol@test.com');

        $this->assertNotEmpty($response->json('data.data.auth_token'));
    }

    public function test_login_failure_envelope(): void
    {
        $response = $this->postJson('/api/login/', [
            'email' => 'vol@test.com',
            'password' => 'WrongPass1',
        ]);

        $this->assertErrorEnvelope($response, 400, 'Login failed.');
        $response->assertJsonStructure(['response_status' => ['validation_errors']]);
    }

    public function test_forgot_password_and_verify_password_otp_and_change_password(): void
    {
        $forgot = $this->postJson('/api/forgot-password/', ['email' => 'vol@test.com']);
        $this->assertSuccessEnvelope($forgot, 200, 'Please check your email for the OTP.');

        $otp = OtpVerification::query()
            ->where('user_id', $this->volunteer->id)
            ->latest('id')
            ->value('otp');

        $verify = $this->postJson('/api/verify_otp_or_token/', [
            'email' => 'vol@test.com',
            'type' => 'password',
            'otp' => $otp,
        ]);
        $this->assertSuccessEnvelope($verify, 200, 'OTP verified successfully.');
        $token = $verify->json('data.token');
        $this->assertNotEmpty($token);

        $change = $this->postJson('/api/change-password/', [
            'email' => 'vol@test.com',
            'password' => 'Password2',
            'token' => $token,
        ]);
        $this->assertSuccessEnvelope($change, 200, 'Password updated successfully.');

        $this->postJson('/api/login/', [
            'email' => 'vol@test.com',
            'password' => 'Password2',
        ])->assertOk();

        // restore for other tests using setUp user is separate DB per test method with RefreshDatabase
    }

    public function test_resend_otp_messages_match_django(): void
    {
        $this->postJson('/api/register/', [
            'email' => 'resend@test.com',
            'password' => 'Password1',
            'user_type' => 'volunteer',
            'civil_id' => '123456789077',
            'birth_year' => 1992,
        ])->assertCreated();

        $response = $this->postJson('/api/resend_otp_or_token/', [
            'email' => 'resend@test.com',
            'type' => 'register',
        ]);

        $this->assertSuccessEnvelope($response, 200, 'OTP has been sent to the email address.');
    }

    public function test_check_user_matches_django_data_shape(): void
    {
        $response = $this->postJson('/api/check-user/', [
            'email' => 'vol@test.com',
            'nickname' => 'UniqueNick999',
        ]);

        $this->assertSuccessEnvelope($response, 200, 'User check completed.');
        $response->assertJsonPath('data.email.is_new_user', false)
            ->assertJsonPath('data.nickname.is_new_user', true);
    }

    public function test_account_get_and_update_match_account_info_serializer(): void
    {
        $get = $this->withTokenHeader($this->volunteerToken)->getJson('/api/account/');
        $this->assertSuccessEnvelope($get, 200, 'Account information retrieved');
        $get->assertJsonStructure([
            'data' => [
                'id',
                'profile_pic',
                'first_name',
                'last_name',
                'full_name',
                'email',
                'phone_number',
                'country_code',
                'birth_year',
                'password',
                'manual_id',
                'user_type',
                'password_length',
                'nationality',
                'preferred_language',
                'civil_id',
            ],
        ])->assertJsonPath('data.email', 'vol@test.com')
            ->assertJsonPath('data.user_type', 'volunteer')
            ->assertJsonPath('data.password', null);

        $update = $this->withTokenHeader($this->volunteerToken)->patchJson('/api/account/', [
            'first_name' => 'Updated',
            'preferred_language' => 'ar',
        ]);
        $this->assertSuccessEnvelope($update, 200, 'Account information updated');
        $update->assertJsonPath('data.first_name', 'Updated')
            ->assertJsonPath('data.preferred_language', 'ar');
    }

    public function test_account_requires_token_auth_header(): void
    {
        $this->assertErrorEnvelope($this->getJson('/api/account/'), 401);
    }

    public function test_public_profile_envelope(): void
    {
        $response = $this->getJson('/api/public-profile/'.$this->volunteer->id.'/');
        $this->assertSuccessEnvelope($response);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'profile_data',
                'user_type',
                'is_volunteer_team',
                'is_public',
                'badge_info',
            ],
        ])->assertJsonPath('data.user_type', 'volunteer');
    }

    public function test_choices_success_and_404_match_django(): void
    {
        $ok = $this->getJson('/api/choices/gender/');
        $this->assertSuccessEnvelope($ok, 200, 'Choices retrieved successfully.');
        $ok->assertJsonStructure([
            'data' => [
                ['id', 'choice_type', 'value_en', 'value_ar'],
            ],
        ])->assertJsonPath('data.0.choice_type', 'gender');

        $missing = $this->getJson('/api/choices/does_not_exist/');
        $this->assertErrorEnvelope($missing, 404, 'No choices found for the given choice type.');
        $missing->assertJsonPath('data', null);
    }

    public function test_banner_images_and_statistics_shape(): void
    {
        $response = $this->getJson('/api/banner-images/');
        $this->assertSuccessEnvelope(
            $response,
            200,
            'Banner images and platform statistics retrieved successfully.'
        );
        $response->assertJsonStructure([
            'data' => [
                'banner_images',
                'statistics' => [
                    'volunteer_count',
                    'volunteer_team_count',
                    'organization_count',
                ],
            ],
        ]);
        $this->assertGreaterThanOrEqual(1, $response->json('data.statistics.volunteer_count'));
    }

    public function test_check_license_requirement_for_volunteer_and_organization(): void
    {
        $vol = $this->withTokenHeader($this->volunteerToken)->getJson('/api/check-license-requirement/');
        $this->assertSuccessEnvelope($vol, 200, 'License requirement retrieved successfully.');
        $vol->assertJsonPath('data.user_role_display', 'Volunteer')
            ->assertJsonPath('data.license_required', false);

        $org = $this->withTokenHeader($this->organizationToken)->getJson('/api/check-license-requirement/');
        $this->assertSuccessEnvelope($org, 200, 'License requirement retrieved successfully.');
        $org->assertJsonPath('data.user_role_display', 'Organization')
            ->assertJsonPath('data.license_required', true);
    }

    public function test_faqs_paginated_like_django_custom_pagination(): void
    {
        Faq::query()->create([
            'question_en' => 'Q2',
            'question_ar' => 'س2',
            'answer_en' => 'A2',
            'answer_ar' => 'ج2',
        ]);

        $response = $this->getJson('/api/faqs/?limit=10');
        $this->assertSuccessEnvelope($response, 200, 'FAQs retrieved successfully');
        $this->assertPaginationMeta($response);
        $response->assertJsonStructure([
            'data' => [
                ['id', 'question_en', 'question_ar', 'answer_en', 'answer_ar', 'created_at', 'updated_at', 'is_deleted', 'deleted_at'],
            ],
        ]);
    }

    public function test_volunteer_profile_endpoints(): void
    {
        $show = $this->withTokenHeader($this->volunteerToken)->getJson('/api/volunteer-profile/');
        $this->assertSuccessEnvelope($show, 200, 'Volunteer profile retrieved successfully.');
        $show->assertJsonPath('data.id', $this->volunteer->volunteerProfile->id);

        $update = $this->withTokenHeader($this->volunteerToken)->patchJson('/api/volunteer-profile/', [
            'nickname' => 'HeroVol',
            'civil_id' => '123456789001',
            'occupation' => 'Engineer',
        ]);
        $this->assertSuccessEnvelope($update, 200, 'Volunteer profile updated successfully.');
        $update->assertJsonPath('data.nickname', 'HeroVol')
            ->assertJsonPath('data.occupation', 'Engineer');

        $list = $this->withTokenHeader($this->organizationToken)->getJson('/api/all-volunteers/');
        $this->assertSuccessEnvelope($list, 200, 'Volunteers retrieved successfully.');
        $this->assertPaginationMeta($list);

        $qr = $this->withTokenHeader($this->volunteerToken)->getJson('/api/volunteer-profile/qr-code/');
        $this->assertSuccessEnvelope($qr, 200, 'QR code details fetched successfully.');
        $qr->assertJsonStructure(['data' => ['volunteer_id', 'qr_code_url', 'name', 'manual_id']]);

        $uuid = $this->volunteer->volunteerProfile->uuid;
        $verify = $this->getJson('/api/verify/'.$uuid.'/');
        $verify->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'volunteer']);
    }

    public function test_organization_profile_endpoints(): void
    {
        $show = $this->withTokenHeader($this->organizationToken)->getJson('/api/organization-profile/');
        $this->assertSuccessEnvelope($show, 200, 'Organizer profile retrieved successfully.');

        $update = $this->withTokenHeader($this->organizationToken)->patchJson('/api/organization-profile/', [
            'company_name' => 'Fursa Org Updated',
            'nickname' => 'orgnick',
        ]);
        $this->assertSuccessEnvelope($update, 200, 'Organizer profile updated successfully.');
        $update->assertJsonPath('data.company_name', 'Fursa Org Updated');

        $this->organization->organizationProfile->update([
            'organization_status' => ApprovalStatus::APPROVED,
        ]);

        $list = $this->withTokenHeader($this->volunteerToken)->getJson('/api/list-organizations/');
        $this->assertSuccessEnvelope($list, 200, 'Organizations retrieved successfully.');
        $this->assertPaginationMeta($list);
    }

    public function test_proxy_image_validation_and_options(): void
    {
        $this->assertErrorEnvelope($this->getJson('/api/proxy-image/'), 400);

        $this->call('OPTIONS', '/api/proxy-image/')
            ->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_social_auth_new_volunteer_returns_auth_token(): void
    {
        $response = $this->postJson('/api/social-auth/', [
            'email' => 'social.vol@test.com',
            'social_media_provider' => 'google',
            'social_media_id' => 'g-123',
            'first_name' => 'Social',
            'last_name' => 'User',
            'user_type' => 'volunteer',
            'civil_id' => '123456789066',
        ]);

        $this->assertSuccessEnvelope($response, 200, 'Login successful. Welcome!');
        $response->assertJsonStructure([
            'data' => ['id', 'email', 'auth_token', 'is_new_user', 'user_type'],
        ])->assertJsonPath('data.email', 'social.vol@test.com')
            ->assertJsonPath('data.is_new_user', true);
    }

    public function test_linkedin_callback_requires_code_and_redirect_uri(): void
    {
        $this->assertErrorEnvelope($this->postJson('/api/linkedin/callback/', []), 422);
    }

    protected function withTokenHeader(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Token '.$token);
    }

    protected function createActiveVolunteer(string $email, string $civilId): array
    {
        $user = User::query()->create([
            'email' => $email,
            'password' => 'Password1',
            'password_length' => 10,
            'user_type' => UserType::VOLUNTEER,
            'civil_id' => $civilId,
            'first_name' => 'Vol',
            'last_name' => 'User',
            'is_active' => true,
            'preferred_language' => 'en',
            'manual_id' => Str::random(22),
        ]);

        VolunteerProfile::query()->create([
            'user_id' => $user->id,
            'is_verified' => true,
            'nickname' => 'volnick_'.Str::random(4),
            'uuid' => (string) Str::uuid(),
        ]);

        $token = ExpiringToken::issueFor($user, 30);

        return [$user, $token->key];
    }

    protected function createActiveOrganization(string $email): array
    {
        $user = User::query()->create([
            'email' => $email,
            'password' => 'Password1',
            'password_length' => 10,
            'user_type' => UserType::ORGANIZATION,
            'first_name' => 'Org',
            'last_name' => 'Admin',
            'is_active' => true,
            'preferred_language' => 'en',
            'manual_id' => Str::random(22),
        ]);

        OrganizationProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Fursa Org',
            'organization_status' => ApprovalStatus::PENDING,
            'nickname' => 'org_'.Str::random(4),
        ]);

        $token = ExpiringToken::issueFor($user, 30);

        return [$user, $token->key];
    }
}
