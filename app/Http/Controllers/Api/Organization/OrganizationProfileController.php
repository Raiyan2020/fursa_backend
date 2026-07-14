<?php

namespace App\Http\Controllers\Api\Organization;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\OrganizationDocument;
use App\Models\OrganizationProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrganizationProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->organizationProfile()->with(['organizerType', 'sector', 'documents', 'user'])->first();
        if (! $profile) {
            return ApiResponse::error('Organization profile not found.', 'ملف الجهة غير موجود.', 404);
        }

        return ApiResponse::success(
            $this->transform($profile),
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

        $data = $request->validate([
            'nickname' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'integer', 'exists:master_choices,id'],
            'organizer_type' => ['nullable', 'integer', 'exists:master_choices,id'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'nationality' => ['nullable', 'string'],
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

        return ApiResponse::success(
            $this->transform($profile->fresh(['organizerType', 'sector', 'documents', 'user'])),
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

        return ApiResponse::success(
            $this->transform($profile->fresh(['organizerType', 'sector', 'documents', 'user'])),
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
            ->whereHas('user', fn ($q) => $q->where('is_banned', false)->where('is_deleted', false)->where('id', '!=', $request->user()->id))
            ->with(['user', 'organizerType']);

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
            $paginator->getCollection()->map(fn (OrganizationProfile $p) => $this->transform($p))->values(),
            'Organizations retrieved successfully.',
            'تم استرجاع الجهات بنجاح.'
        );
    }

    protected function transform(OrganizationProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'nickname' => $profile->nickname,
            'company_name' => $profile->company_name,
            'registration_number' => $profile->registration_number,
            'license_number' => $profile->license_number,
            'organization_status' => $profile->organization_status?->value,
            'latitude' => $profile->latitude,
            'longitude' => $profile->longitude,
            'organizer_type' => $profile->organizerType ? [
                'id' => $profile->organizerType->id,
                'value_en' => $profile->organizerType->value_en,
                'value_ar' => $profile->organizerType->value_ar,
            ] : null,
            'sector' => $profile->sector ? [
                'id' => $profile->sector->id,
                'value_en' => $profile->sector->value_en,
                'value_ar' => $profile->sector->value_ar,
            ] : null,
            'documents' => $profile->documents?->where('is_deleted', false)->values()->map(fn ($d) => [
                'id' => $d->id,
                'document' => Storage::disk('public')->url($d->document),
                'uploaded_at' => optional($d->uploaded_at)?->toIso8601String(),
            ]) ?? [],
            'user' => $profile->user ? [
                'id' => $profile->user->id,
                'email' => $profile->user->email,
                'first_name' => $profile->user->first_name,
                'last_name' => $profile->user->last_name,
            ] : null,
        ];
    }
}
