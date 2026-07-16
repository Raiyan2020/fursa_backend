<?php

namespace App\Http\Controllers\Api\Opportunity\Concerns;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\MasterChoice;
use App\Models\OpportunityImage;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait HandlesOpportunities
{
    protected function calculateAge(?int $birthYear): ?int
    {
        if (! $birthYear) {
            return null;
        }

        return (int) date('Y') - $birthYear;
    }

    protected function paginateQuery(Builder $query, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    protected function invalidStatusResponse(string $status): JsonResponse
    {
        $valid = implode(', ', OpportunityStatus::values());

        return ApiResponse::error(
            "Invalid status value: {$status}. Valid options are: {$valid}",
            "قيمة الحالة غير صالحة: {$status}. الخيارات الصالحة هي: {$valid}",
            400
        );
    }

    protected function applyVolunteerPublicFilters(Builder $query, Request $request): Builder|JsonResponse
    {
        $typeId = $request->query('type');
        if ($typeId) {
            $choice = MasterChoice::query()
                ->whereHas('choiceType', fn ($q) => $q->where('name', 'filter-type'))
                ->find($typeId);

            if ($choice && $choice->value_en !== 'Volunteer') {
                return ApiResponse::success(
                    [],
                    'No opportunities available for the selected type.',
                    'لا توجد فرص متاحة لنوع الاختيار المحدد.'
                );
            }
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%");
            });
        }

        if ($startDate = $request->query('start_date')) {
            $query->whereDate('start_date', '>=', $startDate);
        }
        if ($endDate = $request->query('end_date')) {
            $query->whereDate('end_date', '<=', $endDate);
        }

        if ($minHours = $request->query('min_hours')) {
            $query->where('volunteer_hours_per_day', '>=', (float) $minHours);
        }
        if ($maxHours = $request->query('max_hours')) {
            $query->where('volunteer_hours_per_day', '<=', (float) $maxHours);
        }

        $tags = $request->query('tags', []);
        if (! is_array($tags)) {
            $tags = [$tags];
        }
        if ($tags) {
            $query->whereHas('interests', function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->where(function ($iq) use ($tag) {
                        $iq->where('name_en', 'like', "%{$tag}%")
                            ->orWhere('name_ar', 'like', "%{$tag}%");
                    });
                }
            });
        }

        if ($location = $request->query('location')) {
            $query->where(function ($q) use ($location) {
                $q->where('location_en', 'like', "%{$location}%")
                    ->orWhere('location_ar', 'like', "%{$location}%");
            });
        }

        $gender = $request->query('gender');
        if ($gender && $gender !== 'all' && is_numeric($gender)) {
            $query->where('gender_id', (int) $gender);
        }

        if ($minAge = $request->query('min_age')) {
            $query->where('from_age', '>=', (int) $minAge);
        }
        if ($maxAge = $request->query('max_age')) {
            $query->where('to_age', '<=', (int) $maxAge);
        }

        $nationality = $request->query('opportunity_nationality');
        if ($nationality === 'kuwaitis') {
            $query->where('is_kuwaitis', true);
        } elseif ($nationality === 'non-kuwaitis') {
            $query->where('is_kuwaitis', false);
        }

        foreach (['is_relief', 'is_urgent', 'is_supports_disabled'] as $boolField) {
            if ($request->has($boolField)) {
                $query->where($boolField, filter_var($request->query($boolField), FILTER_VALIDATE_BOOLEAN));
            }
        }

        if ($request->boolean('match_my_interest') && $request->user()) {
            $userInterestIds = $request->user()->interests()->pluck('interests.id');
            if ($userInterestIds->isEmpty()) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereHas('interests', fn ($q) => $q->whereIn('interests.id', $userInterestIds));
            }
        }

        if ($status = $request->query('status')) {
            if (! in_array($status, OpportunityStatus::values(), true)) {
                return $this->invalidStatusResponse($status);
            }
            $query->where('opportunity_status', $status);
        }

        return $query;
    }

    protected function canViewVolunteerOpportunity(VolunteerOpportunity $opportunity, ?User $user): bool
    {
        if ($opportunity->is_deleted) {
            return false;
        }

        if ($opportunity->approval_status === ApprovalStatus::APPROVED) {
            return true;
        }

        return $user && $opportunity->created_by === $user->id;
    }

    protected function computeAttendanceHours(VolunteerOpportunity $opportunity): float
    {
        if (! $opportunity->start_time || ! $opportunity->end_time) {
            return 0;
        }

        $start = strtotime($opportunity->start_time);
        $end = strtotime($opportunity->end_time);
        $hours = ($end - $start) / 3600;
        if ($hours < 0) {
            $hours += 24;
        }

        return round($hours, 2);
    }

    protected function updateOpportunityImages(
        Request $request,
        object $opportunity,
        string $resourceClass,
        string $foreignKey,
        array $with = []
    ): JsonResponse {
        if ($opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error(
                'Only the creator of this opportunity can update images.',
                'فقط منشئ هذه الفرصة يمكنه تحديث الصور.',
                403
            );
        }

        foreach ($request->allFiles() as $key => $file) {
            if (str_starts_with($key, 'opportunity_images_') || str_starts_with($key, 'new_opportunity_images_')) {
                OpportunityImage::query()->create([
                    $foreignKey => $opportunity->id,
                    'image' => $file->store('opportunity-images', 'public'),
                    'is_after_completed' => true,
                ]);
            }
        }

        $existingIds = $request->input('existing_image_ids', []);
        if (! is_array($existingIds)) {
            $existingIds = [$existingIds];
        }
        if ($existingIds !== []) {
            OpportunityImage::query()->whereIn('id', $existingIds)->update([$foreignKey => $opportunity->id]);
        }

        if ($with !== []) {
            $opportunity->load($with);
        }

        return ApiResponse::success(
            new $resourceClass($opportunity),
            'Opportunity images updated successfully.',
            'تم تحديث صور الفرصة بنجاح.'
        );
    }
}
