<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Interest;
use App\Models\MasterChoice;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HandlesProfileInterests
{
    /**
     * Profile tags come from master_choices (user_interest). The frontend may send
     * interest_ids, interests, tags, master_interest_ids, or _interests — all with master choice IDs.
     *
     * @return list<int>|null
     */
    protected function extractProfileInterestIds(Request $request): ?array
    {
        $raw = null;

        foreach (['interest_ids', 'interests', 'tags', 'master_interest_ids', '_interests'] as $field) {
            if ($request->has($field)) {
                $raw = $request->input($field);
                break;
            }
        }

        if ($raw === null) {
            return null;
        }

        if (! is_array($raw)) {
            $raw = [$raw];
        }

        return array_values(array_filter(array_map(
            static fn ($id) => filter_int($id),
            $raw
        ), static fn ($id) => $id !== null));
    }

    /**
     * @param  list<int>  $ids
     */
    protected function syncProfileInterests(User $user, array $ids): ?JsonResponse
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            $user->masterInterests()->sync([]);
            $user->interests()->sync([]);

            return null;
        }

        $masterIds = MasterChoice::query()
            ->notDeleted()
            ->whereHas('choiceType', fn ($query) => $query->where('name', 'user_interest')->notDeleted())
            ->whereIn('id', $ids)
            ->pluck('id');

        if ($masterIds->count() === count($ids)) {
            $user->masterInterests()->sync($masterIds);

            return null;
        }

        $legacyInterestIds = Interest::query()->whereIn('id', $ids)->pluck('id');
        if ($legacyInterestIds->count() === count($ids)) {
            $user->interests()->sync($legacyInterestIds);

            return null;
        }

        return ApiResponse::error(
            'One or more interests are invalid.',
            'واحد أو أكثر من الاهتمامات غير صالح.',
            422,
            ['interest_ids' => ['One or more selected interests are invalid.']]
        );
    }
}
