<?php

namespace App\Http\Controllers\Api\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\VolunteerProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VolunteerProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->volunteerProfile()->with(['gender', 'organization', 'currentBadge', 'user'])->first();

        if (! $profile) {
            return ApiResponse::error('Volunteer profile not found.', 'ملف المتطوع غير موجود.', 404);
        }

        return ApiResponse::success(
            $this->transform($profile),
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

        $data = $request->validate([
            'nickname' => ['nullable', 'string', 'max:50'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'experience' => ['nullable', 'string'],
            'health_concerns' => ['nullable', 'in:yes,no'],
            'is_public' => ['nullable', 'boolean'],
            'is_verified' => ['nullable', 'boolean'],
            'gender' => ['nullable', 'integer', 'exists:master_choices,id'],
            'civil_id' => ['required', 'string', 'max:12', Rule::unique('users', 'civil_id')->ignore($user->id)],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'nationality' => ['nullable', 'string'],
            'dob' => ['nullable', 'date'],
            'birth_year' => ['nullable', 'integer'],
            'instagram_link' => ['nullable', 'url'],
            'whatsapp_link' => ['nullable', 'url'],
            'linkedin_link' => ['nullable', 'url'],
            'facebook_link' => ['nullable', 'url'],
            'twitter_link' => ['nullable', 'url'],
            'interest_ids' => ['nullable', 'array'],
            'interest_ids.*' => ['integer', 'exists:interests,id'],
        ]);

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

        $user->fill([
            'civil_id' => $data['civil_id'],
            'email' => $data['email'] ?? $user->email,
            'nationality' => $data['nationality'] ?? $user->nationality,
            'dob' => $data['dob'] ?? $user->dob,
            'birth_year' => $data['birth_year'] ?? $user->birth_year,
            'instagram_link' => $data['instagram_link'] ?? $user->instagram_link,
            'whatsapp_link' => $data['whatsapp_link'] ?? $user->whatsapp_link,
            'linkedin_link' => $data['linkedin_link'] ?? $user->linkedin_link,
            'facebook_link' => $data['facebook_link'] ?? $user->facebook_link,
            'twitter_link' => $data['twitter_link'] ?? $user->twitter_link,
        ]);
        $user->save();

        if (isset($data['interest_ids'])) {
            $user->interests()->sync($data['interest_ids']);
        }

        return ApiResponse::success(
            $this->transform($profile->fresh(['gender', 'organization', 'currentBadge', 'user'])),
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
            ->with(['user', 'currentBadge']);

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
            $paginator->getCollection()->map(fn (VolunteerProfile $p) => $this->transform($p))->values(),
            'Volunteers retrieved successfully.',
            'تم استرجاع المتطوعين بنجاح.'
        );
    }

    public function qrCode(Request $request): JsonResponse
    {
        $profile = $request->user()->volunteerProfile;
        if (! $profile) {
            return ApiResponse::error('Volunteer profile not found.', 'ملف المتطوع غير موجود.', 404);
        }

        $url = $profile->qr_code ? Storage::disk('public')->url($profile->qr_code) : null;

        return ApiResponse::success([
            'display_url' => $url,
            'download_url' => $url,
            'uuid' => $profile->uuid,
            'verify_url' => rtrim(config('fursa.frontend_host'), '/').'/verify/'.$profile->uuid,
        ], 'QR code details fetched successfully.', 'تم جلب تفاصيل رمز QR بنجاح.');
    }

    public function verifyByUuid(string $uuid): JsonResponse
    {
        $profile = VolunteerProfile::query()->with(['user', 'currentBadge'])->where('uuid', $uuid)->first();
        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Volunteer not found',
                'volunteer' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Volunteer verified',
            'volunteer' => $this->transform($profile),
        ]);
    }

    protected function transform(VolunteerProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'nickname' => $profile->nickname,
            'occupation' => $profile->occupation,
            'experience' => $profile->experience,
            'health_concerns' => $profile->health_concerns,
            'is_public' => $profile->is_public,
            'is_verified' => $profile->is_verified,
            'uuid' => $profile->uuid,
            'qr_code' => $profile->qr_code ? Storage::disk('public')->url($profile->qr_code) : null,
            'total_volunteer_hours' => $profile->total_volunteer_hours,
            'total_opportunities' => $profile->total_opportunities,
            'total_certificates' => $profile->total_certificates,
            'current_year_hours' => $profile->current_year_hours,
            'gender' => $profile->gender ? [
                'id' => $profile->gender->id,
                'value_en' => $profile->gender->value_en,
                'value_ar' => $profile->gender->value_ar,
            ] : null,
            'badge' => $profile->currentBadge ? [
                'id' => $profile->currentBadge->id,
                'name' => $profile->currentBadge->name,
            ] : null,
            'user' => $profile->user ? [
                'id' => $profile->user->id,
                'first_name' => $profile->user->first_name,
                'last_name' => $profile->user->last_name,
                'email' => $profile->user->email,
                'civil_id' => $profile->user->civil_id,
                'nationality' => $profile->user->nationality?->value ?? $profile->user->nationality,
            ] : null,
        ];
    }
}
