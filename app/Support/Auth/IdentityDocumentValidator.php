<?php

namespace App\Support\Auth;

use App\Enums\Nationality;
use App\Enums\ResidencyStatus;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;

/**
 * The three-way nationality rule (BE-50): a Kuwaiti (or a client that omits
 * `nationality`, preserving old behaviour) must send civil_id; a non-Kuwaiti
 * must first declare residency_status, which then selects civil_id (resident)
 * or passport_number (non_resident). Shared so /register/, the volunteer
 * profile update and social signup can't drift apart the way they did before.
 */
class IdentityDocumentValidator
{
    /**
     * BE-56: a save that touches none of the identity fields, on an account that
     * never answered them either (legacy pre-BE-50 social signups have null
     * nationality/civil_id/passport_number), has nothing to validate — the
     * fallback in the caller can't fill an empty stored value, so running the
     * check anyway 422s a request that never asked about identity at all.
     */
    public static function isApplicable(Request $request, User $user): bool
    {
        foreach (['nationality', 'residency_status', 'civil_id', 'passport_number'] as $field) {
            if ($request->has($field)) {
                return true;
            }
        }

        // BE-57: a non-Kuwaiti row with a stored nationality but no
        // residency_status has not actually finished answering the identity
        // question — validate() cannot pass without one, and the fallback
        // merge in the caller can't fill it from an empty stored value
        // either. Treating it as "already answered" (the BE-56 guard's
        // original check) 422s a request that never touched identity at all,
        // on every row created before residency_status existed.
        if ($user->nationality === Nationality::OTHER && $user->residency_status === null) {
            return false;
        }

        return $user->nationality !== null
            || (string) $user->civil_id !== ''
            || (string) $user->passport_number !== '';
    }

    /**
     * @param  int|null  $ignoreUserId  Pass the current user's id on an update so
     *                                  their own row doesn't collide with itself;
     *                                  omit it for a brand-new registration, where
     *                                  uniqueness is instead checked against email.
     */
    public static function validate(Request $request, Validator $validator, ?int $ignoreUserId = null): void
    {
        $nationality = Nationality::tryFromInput($request->input('nationality'));
        $isNonKuwaiti = $nationality !== null && $nationality !== Nationality::KUWAITIS;

        if (! $isNonKuwaiti) {
            self::requireUniqueCivilId($request, $validator, $ignoreUserId);

            return;
        }

        $residencyStatus = ResidencyStatus::tryFrom((string) $request->input('residency_status', ''));

        if ($residencyStatus === null) {
            $validator->errors()->add(
                'residency_status',
                __('validation.required', ['attribute' => self::attributeLabel('residency_status')])
            );

            return;
        }

        if ($residencyStatus === ResidencyStatus::NON_RESIDENT) {
            self::requireUniquePassportNumber($request, $validator, $ignoreUserId);

            return;
        }

        self::requireUniqueCivilId($request, $validator, $ignoreUserId);
    }

    protected static function requireUniqueCivilId(Request $request, Validator $validator, ?int $ignoreUserId): void
    {
        $civilId = trim((string) $request->input('civil_id', ''));

        if ($civilId === '') {
            $validator->errors()->add(
                'civil_id',
                __('validation.required', ['attribute' => self::attributeLabel('civil_id')])
            );

            return;
        }

        if (self::duplicateExists($request, 'civil_id', $civilId, $ignoreUserId)) {
            $validator->errors()->add(
                'civil_id',
                __('validation.unique', ['attribute' => self::attributeLabel('civil_id')])
            );
        }
    }

    protected static function requireUniquePassportNumber(Request $request, Validator $validator, ?int $ignoreUserId): void
    {
        $passportNumber = trim((string) $request->input('passport_number', ''));

        if ($passportNumber === '') {
            $validator->errors()->add(
                'passport_number',
                __('validation.required', ['attribute' => self::attributeLabel('passport_number')])
            );

            return;
        }

        if (self::duplicateExists($request, 'passport_number', $passportNumber, $ignoreUserId)) {
            $validator->errors()->add(
                'passport_number',
                __('validation.unique', ['attribute' => self::attributeLabel('passport_number')])
            );
        }
    }

    protected static function duplicateExists(Request $request, string $column, string $value, ?int $ignoreUserId): bool
    {
        $query = User::query()->where($column, $value);

        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        } else {
            $query->where('email', '!=', strtolower(trim((string) $request->input('email', ''))));
        }

        return $query->exists();
    }

    protected static function attributeLabel(string $key): string
    {
        $label = __('validation.attributes.'.$key);

        return $label !== 'validation.attributes.'.$key
            ? $label
            : str_replace('_', ' ', $key);
    }
}
