<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\MasterChoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesAudienceFilters
{
    /**
     * Gender filter: match selected gender + "Both" + unrestricted (null).
     * Accepts master_choices id, or male/female/both (en/ar).
     */
    protected function applyGenderAudienceFilter(Builder $query, Request $request, string $column = 'gender_id'): void
    {
        $gender = $request->query('gender');
        if ($gender === null || $gender === '' || $gender === 'all') {
            return;
        }

        $selectedIds = $this->resolveOpportunityGenderIds($gender);
        if ($selectedIds === []) {
            return;
        }

        $bothIds = MasterChoice::query()
            ->notDeleted()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'opportunity_gender'))
            ->where(function ($q) {
                $q->whereRaw('LOWER(value_en) = ?', ['both'])
                    ->orWhere('value_ar', 'كلاهما');
            })
            ->pluck('id')
            ->all();

        $allowed = array_values(array_unique(array_merge($selectedIds, $bothIds)));

        $query->where(function (Builder $q) use ($column, $allowed) {
            $q->whereIn($column, $allowed)->orWhereNull($column);
        });
    }

    /**
     * Age filter using range overlap so a volunteer's age / age range matches suitable opportunities.
     * Supports min_age, max_age, and single age.
     */
    protected function applyAgeAudienceFilter(Builder $query, Request $request): void
    {
        $minAge = filter_int($request->query('min_age'));
        $maxAge = filter_int($request->query('max_age'));
        $age = filter_int($request->query('age'));

        if ($age !== null) {
            $minAge ??= $age;
            $maxAge ??= $age;
        }

        if ($minAge !== null) {
            $query->where(function (Builder $q) use ($minAge) {
                $q->whereNull('to_age')->orWhere('to_age', '>=', $minAge);
            });
        }

        if ($maxAge !== null) {
            $query->where(function (Builder $q) use ($maxAge) {
                $q->whereNull('from_age')->orWhere('from_age', '<=', $maxAge);
            });
        }
    }

    /**
     * @return list<int>
     */
    protected function resolveOpportunityGenderIds(mixed $gender): array
    {
        $genderId = filter_int($gender);
        if ($genderId !== null) {
            return [$genderId];
        }

        $value = strtolower(trim((string) $gender));
        $map = [
            'male' => ['male', 'ذكر', 'm'],
            'female' => ['female', 'أنثى', 'f'],
            'both' => ['both', 'كلاهما'],
        ];

        $aliases = null;
        foreach ($map as $canonical => $list) {
            if (in_array($value, $list, true) || $value === $canonical) {
                $aliases = $list;
                break;
            }
        }

        if ($aliases === null) {
            return [];
        }

        return MasterChoice::query()
            ->notDeleted()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'opportunity_gender'))
            ->where(function ($q) use ($aliases) {
                foreach ($aliases as $alias) {
                    $q->orWhereRaw('LOWER(value_en) = ?', [strtolower($alias)])
                        ->orWhere('value_ar', $alias);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
