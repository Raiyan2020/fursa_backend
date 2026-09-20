<?php

namespace App\Support\Opportunity;

/**
 * Validation rules shared by the dashboard and API opportunity writers.
 *
 * Surface-specific fields (creator, approval state, republish media, etc.) stay
 * in their controllers. Fields representing the same database columns live
 * here so a record accepted by one surface can be saved by the other (BE-44).
 */
class OpportunityValidationRules
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function core(
        bool $partial = false,
        bool $participantsRequired = true,
        bool $secondaryContentRequired = true,
        bool $dueDateRequired = false
    ): array {
        $required = $partial ? 'sometimes' : 'required';
        $participantsPresence = $participantsRequired ? $required : 'nullable';
        $secondaryPresence = $secondaryContentRequired ? $required : 'nullable';
        // BE-66 — required again on volunteer opportunities only. The column
        // itself stays nullable: legacy rows created while it was optional
        // rely on falling back to `end_date`, so this is validation-only.
        $dueDatePresence = $dueDateRequired ? $required : 'nullable';

        return [
            'title_en' => [$required, 'string', 'max:255'],
            'title_ar' => [$secondaryPresence, 'string', 'max:255'],
            'description_en' => [$secondaryPresence, 'string'],
            'description_ar' => [$secondaryPresence, 'string'],
            'start_date' => [$required, 'date'],
            'end_date' => array_values(array_filter([
                $required,
                'date',
                $partial ? null : 'after_or_equal:start_date',
            ])),
            'due_date' => [$dueDatePresence, 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location_en' => ['nullable', 'string', 'max:255'],
            'location_ar' => ['nullable', 'string', 'max:255'],
            'from_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'to_age' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:from_age'],
            'participants_needed' => [$participantsPresence, 'integer', 'min:'.($participantsRequired ? 1 : 0)],
        ];
    }
}
