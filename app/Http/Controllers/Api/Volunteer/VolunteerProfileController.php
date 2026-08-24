<?php

namespace App\Http\Controllers\Api\Volunteer;

use App\Http\Controllers\Api\Concerns\HandlesProfileInterests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Volunteer\VolunteerProfileResource;
use App\Http\Resources\Volunteer\VolunteerProfileWithUserResource;
use App\Http\Resources\Volunteer\VolunteerVerificationResource;
use App\Models\VolunteerProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VolunteerProfileController extends Controller
{
    use HandlesProfileInterests;

    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->volunteerProfile()->with([
            'gender.choiceType',
            'currentBadge',
            'user.interests',
            'user.masterInterests.choiceType',
            'user.badge',
            'user.emergencyContactRelationship.choiceType',
        ])->first();

        if (! $profile) {
            return ApiResponse::error('Volunteer profile not found.', 'ملف المتطوع غير موجود.', 404);
        }

        return ApiResponse::success(
            new VolunteerProfileResource($profile),
            'Volunteer profile retrieved successfully.',
            'تم استرجاع ملف المتطوع بنجاح.'
        );
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->volunteerProfile;

        if (! $profile) {
            return ApiResponse::error('Volunteer profile not found.', 'ملف المتطوع غير موجود.', 404);
        }

        if ($request->filled('nationality')) {
            $request->merge([
                'nationality' => \App\Enums\Nationality::normalize($request->input('nationality')),
            ]);
        }

        // Coerce numeric phone fields to strings (frontend may send ints)
        $stringFields = [];
        foreach (['phone_number', 'country_code', 'emergency_contact_phone', 'emergency_contact_country_code', 'emergency_contact_civil_id'] as $field) {
            $value = $request->input($field);
            if (is_int($value) || is_float($value)) {
                $stringFields[$field] = (string) $value;
            }
        }
        if ($stringFields !== []) {
            $request->merge($stringFields);
        }

        $interestIds = $this->extractProfileInterestIds($request);
        if ($interestIds !== null) {
            $request->merge(['interest_ids' => $interestIds]);
        }

        $data = $request->validate([
            'profile_pic' => ['nullable', 'image'],
            'first_name' => ['nullable', 'string', 'max:150'],
            'last_name' => ['nullable', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'experience' => ['nullable', 'string'],
            'health_concerns' => ['nullable', 'in:yes,no'],
            'is_public' => ['nullable', 'boolean'],
            'is_verified' => ['nullable', 'boolean'],
            'gender' => ['nullable', 'integer', 'exists:master_choices,id'],
            'civil_id' => ['required', 'string', 'max:12', Rule::unique('users', 'civil_id')->ignore($user->id)],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'nationality' => ['nullable', 'string', Rule::in(\App\Enums\Nationality::values())],
            'dob' => ['nullable', 'date'],
            'birth_year' => ['nullable', 'integer'],
            'instagram_link' => ['nullable', 'url'],
            'whatsapp_link' => ['nullable', 'url'],
            'linkedin_link' => ['nullable', 'url'],
            'facebook_link' => ['nullable', 'url'],
            'twitter_link' => ['nullable', 'url'],
            'phone_number' => ['nullable', 'string', 'max:15'],
            'country_code' => ['nullable', 'string', 'max:5'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_country_code' => ['nullable', 'string', 'max:10'],
            'emergency_contact_civil_id' => ['nullable', 'string', 'max:12'],
            'emergency_contact_relationship' => ['nullable', 'integer', 'exists:master_choices,id'],
            'interest_ids' => ['nullable', 'array'],
            'interest_ids.*' => ['integer'],
        ]);

        if (array_key_exists('nationality', $data)) {
            $data['nationality'] = \App\Enums\Nationality::normalize($data['nationality']);
        }

        $profile->fill([
            'nickname' => $data['nickname'] ?? $profile->nickname,
            'occupation' => $data['occupation'] ?? $profile->occupation,
            'experience' => $data['experience'] ?? $profile->experience,
            'health_concerns' => $data['health_concerns'] ?? $profile->health_concerns,
            'is_public' => $data['is_public'] ?? $profile->is_public,
            'is_verified' => $data['is_verified'] ?? $profile->is_verified,
            'gender_id' => $data['gender'] ?? $profile->gender_id,
        ]);
        $profile->save();

        if (! empty($data['profile_pic'])) {
            $user->profile_pic = $data['profile_pic']->store(config('fursa.storage_path').'/profile_pics', 'public');
        }
        unset($data['profile_pic']);

        $userPhone = preg_replace('/\D+/', '', (string) (($data['country_code'] ?? $user->country_code).($data['phone_number'] ?? $user->phone_number))) ?: '';
        $emergencyPhone = preg_replace('/\D+/', '', (string) (($data['emergency_contact_country_code'] ?? $user->emergency_contact_country_code).($data['emergency_contact_phone'] ?? $user->emergency_contact_phone))) ?: '';
        if ($userPhone !== '' && $userPhone === $emergencyPhone) {
            return ApiResponse::error(
                'Profile update failed.',
                'فشل تحديث الملف الشخصي.',
                422,
                ['emergency_contact_phone' => [__('validation.custom.emergency_contact_phone.different')]]
            );
        }

        $user->fill([
            'first_name' => $data['first_name'] ?? $user->first_name,
            'last_name' => $data['last_name'] ?? $user->last_name,
            'civil_id' => $data['civil_id'],
            'email' => $data['email'] ?? $user->email,
            'nationality' => $data['nationality'] ?? $user->nationality,
            'dob' => $data['dob'] ?? $user->dob,
            'birth_year' => $data['birth_year'] ?? $user->birth_year,
            'phone_number' => array_key_exists('phone_number', $data) ? $data['phone_number'] : $user->phone_number,
            'country_code' => array_key_exists('country_code', $data) ? $data['country_code'] : $user->country_code,
            'emergency_contact_name' => array_key_exists('emergency_contact_name', $data) ? $data['emergency_contact_name'] : $user->emergency_contact_name,
            'emergency_contact_phone' => array_key_exists('emergency_contact_phone', $data) ? $data['emergency_contact_phone'] : $user->emergency_contact_phone,
            'emergency_contact_country_code' => array_key_exists('emergency_contact_country_code', $data) ? $data['emergency_contact_country_code'] : $user->emergency_contact_country_code,
            'emergency_contact_civil_id' => array_key_exists('emergency_contact_civil_id', $data) ? $data['emergency_contact_civil_id'] : $user->emergency_contact_civil_id,
            'emergency_contact_relationship_id' => array_key_exists('emergency_contact_relationship', $data) ? $data['emergency_contact_relationship'] : $user->emergency_contact_relationship_id,
            'instagram_link' => $data['instagram_link'] ?? $user->instagram_link,
            'whatsapp_link' => $data['whatsapp_link'] ?? $user->whatsapp_link,
            'linkedin_link' => $data['linkedin_link'] ?? $user->linkedin_link,
            'facebook_link' => $data['facebook_link'] ?? $user->facebook_link,
            'twitter_link' => $data['twitter_link'] ?? $user->twitter_link,
        ]);
        $user->save();

        if (array_key_exists('interest_ids', $data)) {
            $interestError = $this->syncProfileInterests($user, $data['interest_ids']);
            if ($interestError) {
                return $interestError;
            }
        }

        $profile = $profile->fresh([
            'gender.choiceType',
            'currentBadge',
            'user.interests',
            'user.masterInterests.choiceType',
            'user.badge',
            'user.emergencyContactRelationship.choiceType',
        ]);

        return ApiResponse::success(
            new VolunteerProfileResource($profile),
            'Volunteer profile updated successfully.',
            'تم تحديث ملف المتطوع بنجاح.'
        );
    }

    public function allVolunteers(Request $request): JsonResponse
    {
        $search = $request->query('search');

        $query = VolunteerProfile::query()
            ->notDeleted()
            ->where('is_verified', true)
            ->whereHas('user', fn ($q) => $q->where('is_banned', false)->where('is_deleted', false))
            ->with([
                'gender.choiceType',
                'currentBadge',
                'user.interests',
                'user.masterInterests.choiceType',
                'user.badge',
                'user.volunteerProfile.gender.choiceType',
                'user.emergencyContactRelationship.choiceType',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nickname', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        return ApiResponse::paginated(
            $paginator,
            VolunteerProfileWithUserResource::collection($paginator->getCollection())->resolve(),
            'Volunteers retrieved successfully.',
            'تم استرجاع المتطوعين بنجاح.'
        );
    }

    public function qrCode(Request $request): JsonResponse
    {
        $profile = $request->user()->volunteerProfile()->with('user')->first();
        if (! $profile) {
            return ApiResponse::error('Volunteer profile not found.', 'ملف المتطوع غير موجود.', 404);
        }

        $url = $profile->qr_code ? getimg($profile->qr_code) : null;
        $user = $profile->user;
        $name = trim(($user?->first_name ?? '').' '.($user?->last_name ?? ''));

        return ApiResponse::success([
            'volunteer_id' => $profile->id,
            'qr_code_url' => $url,
            'name' => $name,
            'manual_id' => $user?->manual_id,
        ], 'QR code details fetched successfully.', 'تم جلب تفاصيل رمز QR بنجاح.');
    }

    public function verifyByUuid(string $uuid): JsonResponse
    {
        $profile = VolunteerProfile::query()->with(['user', 'currentBadge'])->where('uuid', $uuid)->first();
        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed. Volunteer not found.',
                'volunteer' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification successful.',
            'volunteer' => (new VolunteerVerificationResource($profile))->resolve(),
        ]);
    }

}
