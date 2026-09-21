<?php

namespace App\Http\Requests\Volunteer;

use App\Enums\Nationality;
use App\Enums\ResidencyStatus;
use App\Http\Controllers\Api\Concerns\HandlesProfileInterests;
use App\Http\Requests\BaseRequest;
use App\Support\Auth\IdentityDocumentValidator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VolunteerProfileUpdateRequest extends BaseRequest
{
    use HandlesProfileInterests;

    /**
     * Captured in prepareForValidation(), before the identity-field fallback
     * merges run, so it reflects what the client actually sent (BE-56).
     */
    protected bool $identityCheckApplicable = false;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profile_pic' => ['nullable', 'image'],
            'first_name' => ['nullable', 'string', 'max:150'],
            'last_name' => ['nullable', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'experience' => ['nullable', 'string'],
            'health_concerns' => ['nullable', 'in:yes,no'],
            'is_public' => ['nullable', 'boolean'],
            'is_verified' => ['nullable', 'boolean'],
            'gender' => ['nullable', 'integer', 'exists:master_choices,id'],
            // Presence and uniqueness for civil_id / passport_number are enforced by
            // IdentityDocumentValidator below, since which one is required depends
            // on nationality / residency_status.
            'civil_id' => ['nullable', 'string', 'max:12'],
            'passport_number' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'nationality' => ['nullable', 'string', Rule::in(Nationality::personValues())],
            'residency_status' => ['nullable', 'string', Rule::in(ResidencyStatus::values())],
            // BE-77 part B — whether the volunteer speaks Arabic, used to
            // match the non_kuwaiti_arabic/non_arabic opportunity audiences.
            'speaks_arabic' => ['nullable', 'boolean'],
            'dob' => ['nullable', 'date'],
            'birth_year' => ['nullable', 'integer'],
            'instagram_link' => ['nullable', 'url'],
            'whatsapp_link' => ['nullable', 'url'],
            'linkedin_link' => ['nullable', 'url'],
            'facebook_link' => ['nullable', 'url'],
            'twitter_link' => ['nullable', 'url'],
            'receive_reminder_emails' => ['nullable', 'boolean'],
            'phone_number' => ['nullable', 'string', 'max:15'],
            'country_code' => ['nullable', 'string', 'max:5'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_country_code' => ['nullable', 'string', 'max:10'],
            'emergency_contact_civil_id' => ['nullable', 'string', 'max:12'],
            'emergency_contact_relationship' => ['nullable', 'integer', 'exists:master_choices,id'],
            'interest_ids' => ['nullable', 'array'],
            'interest_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        if (! $this->identityCheckApplicable) {
            return;
        }

        $validator->after(function (Validator $validator) {
            IdentityDocumentValidator::validate($this, $validator, $this->user()->id);
        });
    }

    /**
     * The form only ever resends the fields the profile screen edits. The
     * identity-document rule needs to see the whole picture, so a field the
     * client omitted falls back to what is already stored — otherwise a
     * non-resident who registered correctly would be asked for civil_id
     * again on every unrelated save (BE-50).
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        // Must read before the fallback merges below populate these keys,
        // otherwise every request looks like it "touched" identity (BE-56).
        $this->identityCheckApplicable = IdentityDocumentValidator::isApplicable($this, $user);

        if ($this->filled('nationality')) {
            $this->merge(['nationality' => Nationality::normalize($this->input('nationality'))]);
        } elseif (! $this->has('nationality') && $user->nationality) {
            $this->merge(['nationality' => $user->nationality->value]);
        }

        if (! $this->has('residency_status') && $user->residency_status) {
            $this->merge(['residency_status' => $user->residency_status->value]);
        }

        if (! $this->has('civil_id') && $user->civil_id) {
            $this->merge(['civil_id' => $user->civil_id]);
        }

        if (! $this->has('passport_number') && $user->passport_number) {
            $this->merge(['passport_number' => $user->passport_number]);
        }

        $interestIds = $this->extractProfileInterestIds($this);
        if ($interestIds !== null) {
            $this->merge(['interest_ids' => $interestIds]);
        }

        $stringFields = [];
        foreach (['phone_number', 'country_code', 'emergency_contact_phone', 'emergency_contact_country_code', 'emergency_contact_civil_id', 'civil_id', 'passport_number'] as $field) {
            $value = $this->input($field);
            if (is_int($value) || is_float($value)) {
                $stringFields[$field] = (string) $value;
            }
        }
        if ($stringFields !== []) {
            $this->merge($stringFields);
        }
    }
}
