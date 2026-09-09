<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Interest;
use App\Models\MasterChoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Attaches tags to an opportunity or event.
 *
 * The platform has two tag vocabularies and they had drifted apart:
 *
 *  - `master_choices` — what `/api/choices/{type}/` serves, what the profile
 *    endpoints accept, and where production data actually lives (the
 *    `master_choice_*` pivots).
 *  - `interests` — a legacy table the opportunity/event write endpoints were
 *    still validating against with `exists:interests,id`.
 *
 * So a client reading tag ids from `/api/choices/…` and posting them to an
 * opportunity got a 422 for every id, and the read payload came back empty
 * because nothing was ever attached. This resolves ids against whichever
 * vocabulary they belong to, preferring master choices.
 */
trait SyncsOpportunityInterests
{
    /**
     * Each resource has its own curated tag vocabulary, served by
     * /api/choices/{choiceType}/:
     *
     *   volunteer opportunities → volunteer_opportunity_interest
     *   learn & serve           → learnserve_opportunity_interest
     *   events                  → event_interest
     *
     * Scoping to it means an id from the wrong context fails with a clear 422
     * naming the right endpoint, instead of quietly attaching a foreign tag.
     *
     * @param  array<int, mixed>|null  $rawIds
     * @return array{attached: int, vocabulary: string|null, unknown: list<int>}
     *
     * @throws ValidationException
     */
    protected function syncOpportunityInterests(Model $model, mixed $rawIds, ?string $choiceType = null): array
    {
        if ($rawIds === null) {
            return ['attached' => 0, 'vocabulary' => null, 'unknown' => []];
        }

        if (! is_array($rawIds)) {
            $rawIds = [$rawIds];
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => filter_int($id), $rawIds),
            static fn ($id) => $id !== null
        )));

        if ($ids === []) {
            $this->detachAllInterests($model);

            return ['attached' => 0, 'vocabulary' => null, 'unknown' => []];
        }

        $masterQuery = MasterChoice::query()
            ->notDeleted()
            ->whereIn('id', $ids);

        if ($choiceType !== null) {
            $masterQuery->whereHas(
                'choiceType',
                fn ($q) => $q->where('name', $choiceType)->notDeleted()
            );
        }

        $masterIds = $masterQuery->pluck('id')->all();

        $legacyIds = Interest::query()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        // Prefer master choices — that is the vocabulary /api/choices/* hands out.
        if ($masterIds !== []) {
            $model->masterInterests()->sync($masterIds);

            // Mirror onto the legacy pivot for any id that also exists there, so
            // older readers keep working during the transition.
            $mirror = array_values(array_intersect($masterIds, $legacyIds));
            if ($mirror !== []) {
                $model->interests()->sync($mirror);
            }

            $unknown = array_values(array_diff($ids, $masterIds));
            if ($unknown !== []) {
                Log::info('Some tag ids were not recognised as master choices', [
                    'model' => $model::class,
                    'model_id' => $model->getKey(),
                    'unknown' => $unknown,
                ]);
            }

            return [
                'attached' => count($masterIds),
                'vocabulary' => 'master_choice',
                'unknown' => $unknown,
            ];
        }

        if ($legacyIds !== []) {
            $model->interests()->sync($legacyIds);

            return [
                'attached' => count($legacyIds),
                'vocabulary' => 'interest',
                'unknown' => array_values(array_diff($ids, $legacyIds)),
            ];
        }

        // Neither vocabulary recognised them. Fail loudly and name the endpoint
        // that serves the ids this resource expects.
        $hint = $choiceType !== null
            ? __('apis.unknown_interest_ids_scoped', ['endpoint' => "/api/choices/{$choiceType}/"])
            : __('apis.unknown_interest_ids');

        throw ValidationException::withMessages(
            array_fill_keys(
                array_map(static fn ($id) => "interest_ids.{$id}", array_keys($ids)),
                [$hint]
            )
        );
    }

    private function detachAllInterests(Model $model): void
    {
        $model->masterInterests()->sync([]);
        $model->interests()->sync([]);
    }
}
