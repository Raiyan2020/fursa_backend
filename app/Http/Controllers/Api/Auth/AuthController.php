<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\AccountResource;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->authService->register($request->validated());
        } catch (ValidationException $e) {
            return ApiResponse::error('Registration failed.', 'فشل التسجيل.', 400, $e->errors());
        } catch (\Throwable $e) {
            return ApiResponse::error('Registration failed.', 'فشل التسجيل.', 400, $e->getMessage());
        }

        return ApiResponse::success(
            new UserResource($user),
            'OTP has been sent to the email address.',
            'تم إرسال OTP إلى عنوان البريد الإلكتروني.',
            201
        );
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'rememberMe' => ['nullable', 'boolean'],
            'is_opportunity' => ['nullable', 'boolean'],
        ]);

        $email = strtolower(trim($data['email']));
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return ApiResponse::error('Login failed.', 'فشل تسجيل الدخول.', 400, [
                'email' => ['en' => 'User not found.', 'ar' => 'المستخدم غير موجود.'],
            ]);
        }

        if (! $user->is_active) {
            $this->authService->sendAccountActivation($user);

            return ApiResponse::error('Login failed.', 'فشل تسجيل الدخول.', 400, [
                'email' => [
                    'en' => 'Account is inactive. Activation mail resent.',
                    'ar' => 'الحساب غير مفعل. تم إعادة إرسال التفعيل.',
                ],
            ]);
        }

        if ($user->is_social_login) {
            return ApiResponse::error('Login failed.', 'فشل تسجيل الدخول.', 400, [
                'email' => [
                    'en' => 'This account uses social login.',
                    'ar' => 'هذا الحساب يستخدم تسجيل الدخول الاجتماعي.',
                ],
            ]);
        }

        if (! empty($data['is_opportunity']) && $user->user_type !== UserType::VOLUNTEER) {
            return ApiResponse::error('Login failed.', 'فشل تسجيل الدخول.', 400, [
                'email' => [
                    'en' => 'Only volunteers can login for opportunities.',
                    'ar' => 'يسمح للمتطوعين فقط بتسجيل الدخول للفرص.',
                ],
            ]);
        }

        if (! Hash::check($data['password'], (string) $user->password)) {
            return ApiResponse::error('Login failed.', 'فشل تسجيل الدخول.', 400, [
                'password' => ['en' => 'Invalid credentials.', 'ar' => 'بيانات الدخول غير صحيحة.'],
            ]);
        }

        [$user, $token] = $this->authService->login($user, (bool) ($data['rememberMe'] ?? false));
        $payload = (new UserResource($user))->resolve();
        $payload['auth_token'] = $token->key;

        return ApiResponse::success(
            ['data' => $payload],
            'Login successful.',
            'تم تسجيل الدخول بنجاح.'
        );
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', strtolower(trim($data['email'])))->first();

        if (! $user) {
            return ApiResponse::error('Forgot password failed.', 'فشل استعادة كلمة المرور.', 400, [
                'email' => ['en' => 'User not found.', 'ar' => 'المستخدم غير موجود.'],
            ]);
        }

        if ($user->is_social_login) {
            return ApiResponse::error('Forgot password failed.', 'فشل استعادة كلمة المرور.', 400, [
                'email' => [
                    'en' => 'Social login accounts cannot reset password.',
                    'ar' => 'حسابات الدخول الاجتماعي لا يمكنها إعادة تعيين كلمة المرور.',
                ],
            ]);
        }

        $this->authService->sendForgotPassword($user);

        if (config('fursa.authentication_method') === 'OTP') {
            return ApiResponse::success(null, 'Please check your email for the OTP.', 'يرجى التحقق من بريدك الإلكتروني للحصول على OTP.');
        }

        return ApiResponse::success(
            null,
            'Please check your email for the password reset link.',
            'يرجى التحقق من بريدك الإلكتروني للحصول على رابط إعادة تعيين كلمة المرور.'
        );
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
            'token' => ['nullable', 'string'],
            'old_password' => ['nullable', 'string'],
        ]);

        $user = User::query()->where('email', strtolower(trim($data['email'])))->first();
        if (! $user) {
            return ApiResponse::error('Change password failed.', 'فشل تغيير كلمة المرور.', 400, [
                'email' => ['en' => 'User not found.', 'ar' => 'المستخدم غير موجود.'],
            ]);
        }

        if ($user->is_social_login) {
            return ApiResponse::error('Change password failed.', 'فشل تغيير كلمة المرور.', 400, [
                'email' => [
                    'en' => 'Social login accounts cannot change password.',
                    'ar' => 'حسابات الدخول الاجتماعي لا يمكنها تغيير كلمة المرور.',
                ],
            ]);
        }

        try {
            $this->authService->changePassword(
                $user,
                $data['password'],
                $data['token'] ?? null,
                $data['old_password'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error('Change password failed.', 'فشل تغيير كلمة المرور.', 400, $e->getMessage());
        }

        return ApiResponse::success(null, 'Password updated successfully.', 'تم تحديث كلمة المرور بنجاح.');
    }

    public function verifyOtpOrToken(Request $request): JsonResponse
    {
        if (config('fursa.authentication_method') !== 'OTP') {
            return ApiResponse::error(
                'Invalid Request.',
                'طلب غير صالح.',
                400,
                ['detail' => ['en' => 'Invalid Request.', 'ar' => 'طلب غير صالح.']]
            );
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'type' => ['required', Rule::in(['register', 'password'])],
            'otp' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', strtolower(trim($data['email'])))->first();
        if (! $user) {
            return ApiResponse::error('OTP verification failed.', 'فشل التحقق من OTP.', 400, [
                'email' => ['en' => 'User not found.', 'ar' => 'المستخدم غير موجود.'],
            ]);
        }

        try {
            if ($data['type'] === 'register') {
                $token = $this->authService->verifyRegisterOtp($user, $data['otp']);

                return ApiResponse::success([
                    'token' => $token->key,
                    'user_id' => $user->id,
                ], 'OTP verified successfully.', 'تم التحقق من OTP بنجاح.');
            }

            $resetToken = $this->authService->verifyPasswordOtp($user, $data['otp']);

            return ApiResponse::success([
                'token' => $resetToken->token,
                'user_id' => $user->id,
            ], 'OTP verified successfully.', 'تم التحقق من OTP بنجاح.');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error('OTP verification failed.', 'فشل التحقق من OTP.', 400, $e->getMessage());
        }
    }

    public function resendOtpOrToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'type' => ['required', Rule::in(['register', 'password'])],
        ]);

        $user = User::query()->where('email', strtolower(trim($data['email'])))->first();
        if (! $user) {
            return ApiResponse::error('Resend failed.', 'فشلت إعادة الإرسال.', 400, [
                'email' => ['en' => 'User not found.', 'ar' => 'المستخدم غير موجود.'],
            ]);
        }

        if ($data['type'] === 'register') {
            if ($user->is_active) {
                return ApiResponse::error('Resend OTP failed.', 'فشل إعادة إرسال OTP.', 400, [
                    'email' => ['en' => 'Account is already active.', 'ar' => 'الحساب مفعل بالفعل.'],
                ]);
            }
            $this->authService->sendAccountActivation($user);
        } else {
            $this->authService->sendForgotPassword($user);
        }

        if (config('fursa.authentication_method') === 'OTP') {
            return ApiResponse::success(
                null,
                'OTP has been sent to the email address.',
                'تم إرسال OTP إلى عنوان البريد الإلكتروني.'
            );
        }

        return ApiResponse::success(
            null,
            'Account activation link has been sent to the email address.',
            'تم إرسال رابط تفعيل الحساب إلى عنوان البريد الإلكتروني.'
        );
    }

    public function checkUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['nullable', 'email'],
            'nickname' => ['nullable', 'string'],
        ]);

        if (empty($data['email']) && empty($data['nickname'])) {
            return ApiResponse::error('Validation error.', 'خطأ في التحقق.', 400, [
                'error' => [
                    'en' => 'At least one of email or nickname must be provided.',
                    'ar' => 'يجب توفير البريد الإلكتروني أو اسم المستخدم على الأقل.',
                ],
            ]);
        }

        $result = [];
        if (! empty($data['email'])) {
            $exists = User::query()->where('email', strtolower(trim($data['email'])))->exists();
            $result['email'] = ['is_new_user' => ! $exists];
        }
        if (! empty($data['nickname'])) {
            $volunteerExists = \App\Models\VolunteerProfile::query()
                ->whereRaw('LOWER(nickname) = ?', [strtolower($data['nickname'])])
                ->exists();
            $orgExists = \App\Models\OrganizationProfile::query()
                ->whereRaw('LOWER(nickname) = ?', [strtolower($data['nickname'])])
                ->exists();
            $result['nickname'] = ['is_new_user' => ! ($volunteerExists || $orgExists)];
        }

        return ApiResponse::success($result, 'User check completed.', 'تم التحقق من المستخدم.');
    }

    public function account(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success(
            new AccountResource($user),
            'Account information retrieved',
            'تم استرداد معلومات الحساب'
        );
    }

    public function updateAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($request->filled('nationality')) {
            $request->merge([
                'nationality' => \App\Enums\Nationality::normalize($request->input('nationality')),
            ]);
        }
        $data = $request->validate([
            'profile_pic' => ['nullable', 'image'],
            'first_name' => ['nullable', 'string', 'max:150'],
            'last_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['nullable', 'string', 'max:15'],
            'country_code' => ['nullable', 'string', 'max:5'],
            'birth_year' => ['nullable', 'integer'],
            'nationality' => ['nullable', 'string', Rule::in(\App\Enums\Nationality::values())],
            'preferred_language' => ['nullable', 'in:en,ar'],
            'civil_id' => ['nullable', 'string', 'max:12', Rule::unique('users', 'civil_id')->ignore($user->id)],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_country_code' => ['nullable', 'string', 'max:10'],
            'emergency_contact_civil_id' => ['nullable', 'string', 'max:12'],
            'emergency_contact_relationship' => ['nullable', 'integer', 'exists:master_choices,id'],
        ]);

        if (array_key_exists('nationality', $data)) {
            $data['nationality'] = \App\Enums\Nationality::normalize($data['nationality']);
        }

        if (! empty($data['profile_pic'])) {
            $user->profile_pic = $data['profile_pic']->store(config('fursa.storage_path').'/profile_pics', 'public');
            unset($data['profile_pic']);
        }

        if (array_key_exists('emergency_contact_relationship', $data)) {
            $user->emergency_contact_relationship_id = $data['emergency_contact_relationship'];
            unset($data['emergency_contact_relationship']);
        }

        $user->fill($data);
        $user->save();

        return ApiResponse::success(
            new AccountResource($user->fresh()),
            'Account information updated',
            'تم تحديث معلومات الحساب'
        );
    }

    public function socialAuth(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'social_media_provider' => ['required', Rule::in(['google', 'linkedin'])],
            'social_media_id' => ['nullable', 'string'],
            'first_name' => ['nullable', 'string'],
            'last_name' => ['nullable', 'string'],
            'social_profile_pic_url' => ['nullable', 'url'],
            'user_type' => ['nullable', Rule::in(UserType::values())],
            'civil_id' => ['nullable', 'string', 'max:12'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string'],
        ]);

        $email = strtolower(trim($data['email']));
        $user = User::query()->where('email', $email)->first();

        if ($user && ! $user->is_social_login) {
            return ApiResponse::error('Social auth failed.', 'فشل الدخول الاجتماعي.', 400, [
                'email' => [
                    'en' => 'Account exists with password login.',
                    'ar' => 'الحساب موجود بتسجيل دخول بكلمة مرور.',
                ],
            ]);
        }

        if (! $user) {
            $userType = UserType::from($data['user_type'] ?? UserType::VOLUNTEER->value);
            if ($userType === UserType::VOLUNTEER && empty($data['civil_id'])) {
                return ApiResponse::error('Social auth failed.', 'فشل الدخول الاجتماعي.', 400, [
                    'civil_id' => ['en' => 'Civil ID is required', 'ar' => 'الرقم المدني مطلوب'],
                ]);
            }

            $user = $this->authService->register(array_merge($data, [
                'email' => $email,
                'user_type' => $userType->value,
                'volunteer_is_verified' => true,
            ]));
            $user->is_social_login = true;
            $user->is_active = true;
            $user->social_media_provider = $data['social_media_provider'];
            $user->social_media_id = $data['social_media_id'] ?? null;
            $user->social_profile_pic_url = $data['social_profile_pic_url'] ?? null;
            $user->password = null;
            $user->save();
        } else {
            $user->fill([
                'first_name' => $data['first_name'] ?? $user->first_name,
                'last_name' => $data['last_name'] ?? $user->last_name,
                'social_media_id' => $data['social_media_id'] ?? $user->social_media_id,
                'social_profile_pic_url' => $data['social_profile_pic_url'] ?? $user->social_profile_pic_url,
                'social_media_provider' => $data['social_media_provider'],
            ]);
            $user->save();
        }

        $token = $this->authService->issueSocialToken($user);
        $payload = (new UserResource($user->fresh(['volunteerProfile', 'organizationProfile'])))->resolve();
        $payload['auth_token'] = $token->key;

        return ApiResponse::success($payload, 'Login successful. Welcome!', 'تم تسجيل الدخول بنجاح. مرحبًا!');
    }

    public function linkedinCallback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'redirect_uri' => ['required', 'url'],
        ]);

        $tokenUrl = env('LINKEDIN_TOKEN_URL', 'https://www.linkedin.com/oauth/v2/accessToken');
        $userInfoUrl = env('LINKEDIN_USERINFO_URL', 'https://api.linkedin.com/v2/userinfo');

        $tokenResponse = Http::asForm()->post($tokenUrl, [
            'grant_type' => 'authorization_code',
            'code' => $data['code'],
            'redirect_uri' => $data['redirect_uri'],
            'client_id' => env('SOCIAL_AUTH_LINKEDIN_OAUTH2_KEY'),
            'client_secret' => env('SOCIAL_AUTH_LINKEDIN_OAUTH2_SECRET'),
        ]);

        if (! $tokenResponse->successful()) {
            return ApiResponse::error('LinkedIn auth failed.', 'فشل تسجيل LinkedIn.', 400, $tokenResponse->json());
        }

        $accessToken = $tokenResponse->json('access_token');
        $profile = Http::withToken($accessToken)->get($userInfoUrl);

        if (! $profile->successful()) {
            return ApiResponse::error('LinkedIn profile fetch failed.', 'فشل جلب بيانات LinkedIn.', 400, $profile->json());
        }

        $info = $profile->json();

        return ApiResponse::success([
            'linkedin_id' => $info['sub'] ?? null,
            'first_name' => $info['given_name'] ?? null,
            'last_name' => $info['family_name'] ?? null,
            'email' => $info['email'] ?? null,
            'picture' => $info['picture'] ?? null,
            'access_token' => $accessToken,
        ]);
    }

    public function publicProfile(int $userId): JsonResponse
    {
        $user = User::query()
            ->with(['volunteerProfile.currentBadge', 'organizationProfile', 'badge'])
            ->find($userId);

        if (! $user) {
            return ApiResponse::error('User not found.', 'المستخدم غير موجود.', 404);
        }

        $isVolunteerTeam = false;
        $isPublic = true;
        $profileData = [];

        if ($user->isVolunteer() && $user->volunteerProfile) {
            $isPublic = (bool) $user->volunteerProfile->is_public;
            $profileData = [
                'nickname' => $user->volunteerProfile->nickname,
                'total_volunteer_hours' => $user->volunteerProfile->total_volunteer_hours,
                'is_verified' => $user->volunteerProfile->is_verified,
            ];
        } elseif ($user->isOrganization() && $user->organizationProfile) {
            $profileData = [
                'nickname' => $user->organizationProfile->nickname,
                'company_name' => $user->organizationProfile->company_name,
                'organization_status' => $user->organizationProfile->organization_status?->value,
            ];
        }

        return ApiResponse::success([
            'id' => $user->id,
            'profile_data' => $profileData,
            'user_type' => $user->user_type?->value,
            'is_volunteer_team' => $isVolunteerTeam,
            'is_public' => $isPublic,
            'badge_info' => $user->badge ? [
                'id' => $user->badge->id,
                'name' => $user->badge->name,
            ] : null,
        ]);
    }
}
