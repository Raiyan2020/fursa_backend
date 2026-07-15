<?php

namespace App\Http\Controllers\Api\Organization;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Organization\OrganizationDocumentResource;
use App\Http\Resources\Organization\OrganizationListResource;
use App\Http\Resources\Organization\OrganizationProfileResource;
use App\Models\OrganizationDocument;
use App\Models\OrganizationProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->organizationProfile()->with([
            'organizerType.choiceType',
            'sector.choiceType',
            'documents',
            'user.interests',
            'user.masterInterests.choiceType',
            'user.badge',
        ])->first();

        if (! $profile) {
            return ApiResponse::error('Organization profile not found.', 'ملف الجهة غير موجود.', 404);
        }

        return ApiResponse::success(
            new OrganizationProfileResource($profile),
            'Organizer profile retrieved successfully.',
            'تم استرجاع ملف الجهة بنجاح.'
        );
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->organizationProfile;
        if (! $profile) {
            return ApiResponse::error('Organization profile not found.', 'ملف الجهة غير موجود.', 404);
        }

        if ($request->filled('nationality')) {
            $request->merge([
                'nationality' => \App\Enums\Nationality::normalize($request->input('nationality')),
            ]);
        }

        $data = $request->validate([
            'nickname' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'integer', 'exists:master_choices,id'],
            'organizer_type' => ['nullable', 'integer', 'exists:master_choices,id'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'nationality' => ['nullable', 'string', Rule::in(\App\Enums\Nationality::values())],
            'instagram_link' => ['nullable', 'url'],
            'whatsapp_link' => ['nullable', 'url'],
            'linkedin_link' => ['nullable', 'url'],
            'facebook_link' => ['nullable', 'url'],
            'twitter_link' => ['nullable', 'url'],
        ]);

        $profile->fill([
            'nickname' => $data['nickname'] ?? $profile->nickname,
            'company_name' => $data['company_name'] ?? $profile->company_name,
            'sector_id' => $data['sector'] ?? $profile->sector_id,
            'organizer_type_id' => $data['organizer_type'] ?? $profile->organizer_type_id,
            'registration_number' => $data['registration_number'] ?? $profile->registration_number,
            'license_number' => $data['license_number'] ?? $profile->license_number,
            'latitude' => $data['latitude'] ?? $profile->latitude,
            'longitude' => $data['longitude'] ?? $profile->longitude,
        ]);
        $profile->save();

        $user->fill([
            'nationality' => $data['nationality'] ?? $user->nationality,
            'instagram_link' => $data['instagram_link'] ?? $user->instagram_link,
            'whatsapp_link' => $data['whatsapp_link'] ?? $user->whatsapp_link,
            'linkedin_link' => $data['linkedin_link'] ?? $user->linkedin_link,
            'facebook_link' => $data['facebook_link'] ?? $user->facebook_link,
            'twitter_link' => $data['twitter_link'] ?? $user->twitter_link,
        ]);
        $user->save();

        $profile = $profile->fresh([
            'organizerType.choiceType',
            'sector.choiceType',
            'documents',
            'user.interests',
            'user.masterInterests.choiceType',
            'user.badge',
        ]);

        return ApiResponse::success(
            new OrganizationProfileResource($profile),
            'Organizer profile updated successfully.',
            'تم تحديث ملف الجهة بنجاح.'
        );
    }

    public function updateDocuments(Request $request): JsonResponse
    {
        $profile = $request->user()->organizationProfile;
        if (! $profile) {
            return ApiResponse::error('Organization profile not found.', 'ملف الجهة غير موجود.', 404);
        }

        $data = $request->validate([
            'existing_ids' => ['nullable', 'array'],
            'existing_ids.*' => ['integer'],
            'new_documents' => ['nullable', 'array'],
            'new_documents.*' => ['file'],
        ]);

        $keepIds = $data['existing_ids'] ?? [];
        $profile->documents()->whereNotIn('id', $keepIds)->each(function (OrganizationDocument $doc) {
            $doc->softDeleteFlags();
        });

        if (! empty($data['new_documents'])) {
            foreach ($data['new_documents'] as $file) {
                OrganizationDocument::query()->create([
                    'organizer_profile_id' => $profile->id,
                    'document' => $file->store(config('fursa.storage_path').'/org_documents', 'public'),
                    'uploaded_at' => now(),
                ]);
            }
        }

        $documents = $profile->documents()
            ->where(function ($q) {
                $q->where('is_deleted', false)->orWhereNull('is_deleted');
            })
            ->get();

        return ApiResponse::success(
            OrganizationDocumentResource::collection($documents)->resolve(),
            'Documents updated successfully.',
            'تم تحديث المستندات بنجاح.'
        );
    }

    public function listOrganizations(Request $request): JsonResponse
    {
        $name = $request->query('name');
        $query = OrganizationProfile::query()
            ->notDeleted()
            ->where('organization_status', ApprovalStatus::APPROVED)
            ->whereHas('user', fn ($q) => $q->where('is_banned', false)->where('is_deleted', false)->where('id', '!=', $request->user()->id));

        if ($name) {
            $query->where(function ($q) use ($name) {
                $q->where('company_name', 'like', "%{$name}%")
                    ->orWhere('nickname', 'like', "%{$name}%");
            });
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        return ApiResponse::paginated(
            $paginator,
            OrganizationListResource::collection($paginator->getCollection())->resolve(),
            'Organizations retrieved successfully.',
            'تم استرجاع الجهات بنجاح.'
        );
    }
}
