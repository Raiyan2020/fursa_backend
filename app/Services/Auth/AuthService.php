<?php

namespace App\Services\Auth;

use App\Enums\ApprovalStatus;
use App\Enums\UserType;
use App\Enums\VerificationType;
use App\Models\ExpiringToken;
use App\Models\OrganizationDocument;
use App\Models\OrganizationProfile;
use App\Models\OtpVerification;
use App\Models\TokenVerification;
use App\Models\User;
use App\Models\VolunteerProfile;
use App\Services\Mail\DynamicEmailService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthService
{
    public function register(array $data): User
    {
        $userType = UserType::from($data['user_type'] ?? UserType::VOLUNTEER->value);

        $user = new User();
        $user->email = Str::lower(trim($data['email']));
        $user->first_name = $data['first_name'] ?? null;
        $user->last_name = $data['last_name'] ?? null;
        $user->phone_number = $data['phone_number'] ?? null;
        $user->country_code = $data['country_code'] ?? null;
        $user->user_type = $userType;
        $user->preferred_language = $data['preferred_language'] ?? 'en';
        $user->nationality = $data['nationality'] ?? null;
        $user->birth_year = $data['birth_year'] ?? null;
        $user->dob = $data['dob'] ?? null;
        $user->civil_id = $data['civil_id'] ?? null;
        $user->emergency_contact_name = $data['emergency_contact_name'] ?? null;
        $user->emergency_contact_phone = $data['emergency_contact_phone'] ?? null;
        $user->emergency_contact_country_code = $data['emergency_contact_country_code'] ?? null;
        $user->emergency_contact_civil_id = $data['emergency_contact_civil_id'] ?? null;
        $user->emergency_contact_relationship_id = $data['emergency_contact_relationship'] ?? null;
        $user->is_active = false;
        $user->date_joined = now();

        if (! empty($data['password'])) {
            $user->password_length = strlen($data['password']);
            $user->password = $data['password'];
        }

        if (! empty($data['profile_pic'])) {
            $user->profile_pic = $data['profile_pic']->store(config('fursa.storage_path').'/profile_pics', 'public');
        }

        $user->save();

        if ($userType === UserType::VOLUNTEER) {
            VolunteerProfile::query()->create([
                'user_id' => $user->id,
                'nickname' => $data['nickname'] ?? null,
                'gender_id' => $data['gender'] ?? null,
                'organization_id' => $data['organization_id'] ?? null,
                'is_verified' => (bool) ($data['volunteer_is_verified'] ?? false),
            ]);
        }

        if ($userType === UserType::ORGANIZATION) {
            $org = OrganizationProfile::query()->create([
                'user_id' => $user->id,
                'nickname' => $data['nickname'] ?? null,
                'company_name' => $data['company_name'] ?? null,
                'organizer_type_id' => $data['organizer_type'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'license_number' => $data['license_number'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'organization_status' => ApprovalStatus::PENDING,
            ]);

            if (! empty($data['documents']) && is_array($data['documents'])) {
                foreach ($data['documents'] as $document) {
                    OrganizationDocument::query()->create([
                        'organizer_profile_id' => $org->id,
                        'document' => $document->store(config('fursa.storage_path').'/org_documents', 'public'),
                        'uploaded_at' => now(),
                    ]);
                }
            }
        }

        $this->sendAccountActivation($user);

        return $user->fresh(['volunteerProfile', 'organizationProfile']);
    }

    public function login(User $user, bool $rememberMe = false): array
    {
        $days = $rememberMe
            ? (int) config('fursa.token_expiry_days.remember', 30)
            : (int) config('fursa.token_expiry_days.default', 1);

        $token = ExpiringToken::issueFor($user, $days);
        $user->last_login = now();
        $user->save();

        return [$user, $token];
    }

    public function issueSocialToken(User $user): ExpiringToken
    {
        return ExpiringToken::issueFor($user, (int) config('fursa.token_expiry_days.social', 30));
    }

    public function sendAccountActivation(User $user): void
    {
        // OTP is always delivered by email (register / resend / inactive login).
        $otp = OtpVerification::query()->create([
            'user_id' => $user->id,
            'verification_type' => VerificationType::ACCOUNT_ACTIVATION,
        ]);

        $sent = DynamicEmailService::send('account_activation_email', $user, [
            'otp' => $otp->otp,
        ]);

        if (! $sent) {
            $this->sendMail(
                $user->email,
                'Account Activation OTP',
                "Your OTP is: {$otp->otp}"
            );
        }
    }

    public function sendForgotPassword(User $user): void
    {
        // OTP is always delivered by email (forgot-password / resend).
        $otp = OtpVerification::query()->create([
            'user_id' => $user->id,
            'verification_type' => VerificationType::FORGOT_PASSWORD,
        ]);

        $sent = DynamicEmailService::send('forgot_password', $user, [
            'otp' => $otp->otp,
        ]);

        if (! $sent) {
            $this->sendMail(
                $user->email,
                'Password Reset OTP',
                "Your OTP is: {$otp->otp}"
            );
        }
    }

    public function verifyRegisterOtp(User $user, string $otp): ExpiringToken
    {
        $record = OtpVerification::query()
            ->where('user_id', $user->id)
            ->where('verification_type', VerificationType::ACCOUNT_ACTIVATION)
            ->where('otp', $otp)
            ->where('is_used', false)
            ->latest('id')
            ->first();

        if (! $record || $record->isExpired()) {
            throw new \InvalidArgumentException('Invalid or expired OTP');
        }

        $record->is_used = true;
        $record->save();

        $user->is_active = true;
        $user->save();

        if ($user->volunteerProfile) {
            $user->volunteerProfile->is_verified = true;
            $user->volunteerProfile->save();
        }

        return $this->issueSocialToken($user);
    }

    public function verifyPasswordOtp(User $user, string $otp): TokenVerification
    {
        $record = OtpVerification::query()
            ->where('user_id', $user->id)
            ->where('verification_type', VerificationType::FORGOT_PASSWORD)
            ->where('otp', $otp)
            ->where('is_used', false)
            ->latest('id')
            ->first();

        if (! $record || $record->isExpired()) {
            throw new \InvalidArgumentException('Invalid or expired OTP');
        }

        $record->is_used = true;
        $record->save();

        return TokenVerification::query()->create([
            'user_id' => $user->id,
            'verification_type' => VerificationType::FORGOT_PASSWORD,
        ]);
    }

    public function changePassword(User $user, string $password, ?string $token = null, ?string $oldPassword = null): void
    {
        if ($token) {
            $verification = TokenVerification::query()
                ->where('user_id', $user->id)
                ->where('token', $token)
                ->where('verification_type', VerificationType::FORGOT_PASSWORD)
                ->where('is_used', false)
                ->latest('id')
                ->first();

            if (! $verification || $verification->isExpired()) {
                throw new \InvalidArgumentException('Invalid or expired token');
            }

            $verification->is_used = true;
            $verification->save();
        } elseif ($oldPassword) {
            if (! Hash::check($oldPassword, $user->password)) {
                throw new \InvalidArgumentException('Old password is incorrect');
            }
        } else {
            throw new \InvalidArgumentException('Token or old password is required');
        }

        $user->password_length = strlen($password);
        $user->password = $password;
        $user->save();
    }

    protected function sendMail(string $to, string $subject, string $body): void
    {
        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to send auth email: '.$e->getMessage(), ['to' => $to]);
        }
    }
}
