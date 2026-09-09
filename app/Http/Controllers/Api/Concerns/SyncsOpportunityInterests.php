<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Enums\InterestType;
use App\Models\Interest;
use App\Models\MasterChoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait SyncsOpportunityInterests
{
    /** Keep picker IDs on the master pivot; bridge legacy readers by name, never by numeric ID. */
    protected function syncOpportunityInterests(Model $model, mixed $rawIds, ?string $choiceType = null): array
    {
        if ($rawIds === null) {
            return ['attached' => 0, 'vocabulary' => null, 'unknown' => []];
        }
        $ids = array_values(array_unique(array_map('intval', (array) $rawIds)));
        $choices = MasterChoice::query()->notDeleted()->whereIn('id', $ids)
            ->when($choiceType, fn ($q) => $q->whereHas('choiceType', fn ($q) => $q->notDeleted()->where('name', $choiceType)))
            ->get();
        $unknown = array_diff($ids, $choices->pluck('id')->all());
        if ($unknown !== []) {
            $errors = [];
            foreach ($ids as $index => $id) {
                if (in_array($id, $unknown, true)) {
                    $errors["interest_ids.$index"] = [__('apis.unknown_interest_ids_scoped', ['endpoint' => "/api/choices/$choiceType/"])];
                }
            }
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($model, $choices, $choiceType) {
            $type = match ($choiceType) {
                'event_interest' => InterestType::EVENT,
                'learnserve_opportunity_interest' => InterestType::LEARNSHARE,
                default => InterestType::VOLUNTEER,
            };
            $legacyIds = $choices->map(function ($choice) use ($type) {
                $name = trim($choice->value_en);

                return (Interest::whereRaw('LOWER(TRIM(name_en)) = ?', [mb_strtolower($name)])->first()
                    ?? Interest::create(['name_en' => $name, 'name_ar' => $choice->value_ar ?: $name, 'interest_type' => $type]))->id;
            });
            $model->masterInterests()->sync($choices->pluck('id'));
            $model->interests()->sync($legacyIds);
        });

        return ['attached' => count($ids), 'vocabulary' => 'master_choice', 'unknown' => []];
    }
}
