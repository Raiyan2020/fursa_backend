<?php

namespace App\Http\Controllers\Api\Opportunity\Concerns;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\VolunteerCategory;
use App\Http\Controllers\Api\Concerns\AppliesAudienceFilters;
use App\Http\Controllers\Api\Concerns\AppliesOpportunityStatusFilter;
use App\Models\Interest;
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
    use AppliesAudienceFilters;
    use AppliesOpportunityStatusFilter;

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
                ->find(filter_int($typeId) ?? $typeId);

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
            $query->whereDate('start_date', '>=', to_western_digits($startDate));
        }
        if ($endDate = $request->query('end_date')) {
            $query->whereDate('end_date', '<=', to_western_digits($endDate));
        }

        $minHours = filter_float($request->query('min_hours'));
        if ($minHours !== null) {
            $query->where('volunteer_hours_per_day', '>=', $minHours);
        }
        $maxHours = filter_float($request->query('max_hours'));
        if ($maxHours !== null) {
            $query->where('volunteer_hours_per_day', '<=', $maxHours);
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
                    ->orWhere('location_ar', 'like', "%{$location}%")
                    ->orWhere('map_desc', 'like', "%{$location}%");
            });
        }

        $this->applyGenderAudienceFilter($query, $request);
        $this->applyAgeAudienceFilter($query, $request);

        $nationality = $request->query('opportunity_nationality');
        if ($nationality === 'kuwaitis') {
            $query->where('is_kuwaitis', true);
        } elseif ($nationality === 'non-kuwaitis') {
            $query->where('is_kuwaitis', false);
        }

        foreach (['is_relief', 'is_urgent', 'is_supports_disabled'] as $boolField) {
            if ($request->has($boolField)) {
                $bool = filter_bool($request->query($boolField));
                if ($bool !== null) {
                    $query->where($boolField, $bool);
                }
            }
        }

        if ($category = $request->query('volunteer_category')) {
            if (VolunteerCategory::tryFrom($category)) {
                $query->where('volunteer_category', $category);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if ($request->boolean('match_my_interest') && $request->user()) {
            $userInterestIds = $this->resolveUserInterestIds($request->user());
            if ($userInterestIds === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereHas('interests', fn ($q) => $q->whereIn('interests.id', $userInterestIds));
            }
        }

        if ($status = $request->query('status')) {
            if ($this->applyOpportunityStatusFilter($query, $status) === null) {
                return $this->invalidStatusResponse($status);
            }
        }

        return $query;
    }

    /**
     * The Interest ids to match an opportunity against for "match my interest".
     *
     * A user's interests are stored in one of two places depending on which ids
     * the profile screen submitted:
     *
     *  - `masterInterests` — MasterChoice rows of choice type `user_interest`.
     *    This is what the current profile UI writes.
     *  - `interests` — the legacy Interest table, which is also what
     *    opportunities are tagged with.
     *
     * Opportunities are only ever tagged with Interest rows, so a user who
     * picked interests through the normal UI had nothing to intersect against
     * and the filter returned zero for everyone. Master choices are therefore
     * translated to their Interest equivalents by name, and the two sets are
     * merged.
     *
     * @return list<int>
     */
    protected function resolveUserInterestIds(User $user): array
    {
        $legacyIds = $user->interests()->pluck('interests.id')->all();

        $masterNames = $user->masterInterests()
            ->pluck('master_choices.value_en')
            ->filter()
            ->all();

        $translatedIds = [];
        if ($masterNames !== []) {
            $translatedIds = Interest::query()
                ->where(function ($query) use ($masterNames) {
                    foreach ($masterNames as $name) {
                        $query->orWhereRaw('LOWER(TRIM(name_en)) = ?', [mb_strtolower(trim((string) $name))]);
                    }
                })
                ->pluck('id')
                ->all();
        }

        return array_values(array_unique(array_map('intval', array_merge($legacyIds, $translatedIds))));
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

    /**
     * Hours to credit for attending on a given date.
     *
     * When the opportunity has a per-day schedule, that day's slot wins, since
     * each day may run different hours. Otherwise the opportunity-wide
     * start_time/end_time applies as before.
     */
    protected function computeAttendanceHours(VolunteerOpportunity $opportunity, ?string $date = null): float
    {
        if ($date !== null) {
            $slot = $opportunity->slotForDate($date);
            if ($slot) {
                return $slot->durationInHours();
            }
        }

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

    /**
     * Attach announcement images on create/update (is_after_completed=false), matching Django serializers.
     */
    protected function storeAnnouncementImagesFromRequest(Request $request, object $opportunity, string $foreignKey): void
    {
        foreach ($request->allFiles() as $key => $file) {
            if (! is_object($file) || ! method_exists($file, 'store')) {
                continue;
            }

            $isAfterCompleted = false;

            if (str_starts_with($key, 'new_opportunity_images_')) {
                $isAfterCompleted = false;
            } elseif (str_starts_with($key, 'opportunity_images_')) {
                $idx = substr($key, strrpos($key, '_') + 1);
                $isAfterCompleted = filter_var(
                    $request->input("opportunity_images_is_after_completed_{$idx}", false),
                    FILTER_VALIDATE_BOOLEAN
                );
            } else {
                continue;
            }

            OpportunityImage::query()->create([
                $foreignKey => $opportunity->id,
                'image' => $file->store('opportunity-images', 'public'),
                'is_after_completed' => $isAfterCompleted,
            ]);
        }
    }

    /**
     * Dedicated after-completion gallery upload endpoint (is_after_completed=true).
     */
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
            if (! is_object($file) || ! method_exists($file, 'store')) {
                continue;
            }

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

    protected function rejectIfRegistrationClosed(object $opportunity): ?JsonResponse
    {
        if (method_exists($opportunity, 'isRegistrationOpen') && ! $opportunity->isRegistrationOpen()) {
            return ApiResponse::error(
                'Registration is closed for this opportunity.',
                'التسجيل مغلق لهذه الفرصة.',
                400
            );
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function opportunitySnapshot(object $opportunity): array
    {
        return $opportunity->only([
            'title_en', 'title_ar', 'description_en', 'description_ar',
            'start_date', 'end_date', 'due_date', 'start_time', 'end_time',
            'location_en', 'location_ar', 'location_url', 'participants_needed',
            'map_desc', 'latitude', 'longitude',
            'from_age', 'to_age', 'link', 'is_registration_closed',
        ]);
    }
}
