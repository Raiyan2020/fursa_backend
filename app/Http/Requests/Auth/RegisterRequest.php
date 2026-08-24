<?php

namespace App\Http\Requests\Auth;

use App\Enums\Nationality;
use App\Enums\UserType;
use App\Http\Requests\BaseRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegisterRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where(fn ($query) => $query->where('is_active', true)->where('is_deleted', false)),
            ],
            'password' => ['nullable', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
            'user_type' => ['nullable', Rule::in(UserType::values())],
            'first_name' => ['nullable', 'string', 'max:150'],
            'last_name' => ['nullable', 'string', 'max:150'],
            'phone_number' => ['nullable', 'string', 'max:15'],
            'country_code' => ['nullable', 'string', 'max:5'],
            'profile_pic' => ['nullable', 'image'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'integer', 'exists:master_choices,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organization_profiles,id'],
            'organizer_type' => ['nullable', 'integer', 'exists:master_choices,id'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'nationality' => ['nullable', 'string', Rule::in(Nationality::values())],
            'birth_year' => ['nullable', 'integer'],
            'dob' => ['nullable', 'date'],
            'preferred_language' => ['nullable', 'in:en,ar'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'civil_id' => ['nullable', 'string', 'max:12'],
            'volunteer_is_verified' => ['nullable', 'boolean'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_country_code' => ['nullable', 'string', 'max:10'],
            'emergency_contact_civil_id' => [
                'nullable',
                'string',
                'max:12',
                'regex:/^[23]\d{11}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $civilId = $this->normalizedCivilId($this->input('civil_id'));
                    $emergencyCivilId = $this->normalizedCivilId($value);

                    if ($civilId !== '' && $emergencyCivilId !== '' && $civilId === $emergencyCivilId) {
                        $fail(__('validation.custom.emergency_contact_civil_id.different'));
                    }
                },
            ],
            'emergency_contact_relationship' => ['nullable', 'integer', 'exists:master_choices,id'],
        ];
    }

    public function attributes(): array
    {
        $keys = array_keys($this->rules());

        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => $this->attributeLabel($key)])
            ->all();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $userType = $this->input('user_type', UserType::VOLUNTEER->value);
            $civilId = trim((string) $this->input('civil_id', ''));

            if ($userType === UserType::VOLUNTEER->value) {
                if ($civilId === '') {
                    $validator->errors()->add(
                        'civil_id',
                        __('validation.required', ['attribute' => $this->attributeLabel('civil_id')])
                    );
                } elseif (User::query()
                    ->where('civil_id', $civilId)
                    ->where('email', '!=', strtolower(trim((string) $this->input('email', ''))))
                    ->exists()) {
                    $validator->errors()->add(
                        'civil_id',
                        __('validation.unique', ['attribute' => $this->attributeLabel('civil_id')])
                    );
                }

                $age = null;
                if ($this->filled('dob')) {
                    $age = now()->diffInYears($this->date('dob'));
                } elseif ($this->filled('birth_year')) {
                    $age = (int) now()->year - (int) $this->input('birth_year');
                }

                if ($age !== null && $age < 18) {
                    foreach ([
                        'emergency_contact_name',
                        'emergency_contact_phone',
                        'emergency_contact_country_code',
                        'emergency_contact_civil_id',
                        'emergency_contact_relationship',
                    ] as $field) {
                        if (! $this->filled($field)) {
                            $validator->errors()->add(
                                $field,
                                __('validation.required', ['attribute' => $this->attributeLabel($field)])
                            );
                        }
                    }
                }
            }

            $this->rejectDuplicateEmergencyContact($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }

        if ($this->filled('nationality')) {
            $this->merge([
                'nationality' => Nationality::normalize($this->input('nationality')),
            ]);
        }

        $this->stringifyNumericFields([
            'phone_number',
            'country_code',
            'civil_id',
            'emergency_contact_phone',
            'emergency_contact_country_code',
            'emergency_contact_civil_id',
            'license_number',
            'registration_number',
            'nickname',
        ]);

        $this->normalizeDocumentUploads();
    }

    protected function rejectDuplicateEmergencyContact(Validator $validator): void
    {
        $userPhone = $this->normalizedPhone(
            $this->input('country_code'),
            $this->input('phone_number')
        );
        $emergencyPhone = $this->normalizedPhone(
            $this->input('emergency_contact_country_code'),
            $this->input('emergency_contact_phone')
        );
        $userLocalPhone = $this->normalizedCivilId($this->input('phone_number'));
        $emergencyLocalPhone = $this->normalizedCivilId($this->input('emergency_contact_phone'));

        $sameFullPhone = $userPhone !== '' && $userPhone === $emergencyPhone;
        $sameLocalPhone = $userLocalPhone !== '' && $userLocalPhone === $emergencyLocalPhone;

        if ($sameFullPhone || $sameLocalPhone) {
            $validator->errors()->add(
                'emergency_contact_phone',
                __('validation.custom.emergency_contact_phone.different')
            );
        }
    }

    protected function normalizedCivilId(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }

    protected function normalizedPhone(mixed $countryCode, mixed $phoneNumber): string
    {
        $digits = preg_replace('/\D+/', '', (string) $countryCode.(string) $phoneNumber);

        return $digits ?: '';
    }

    /**
     * JSON clients often send phone / civil-id values as numbers.
     * Laravel's "string" rule then fails with "must be a string".
     */
    protected function stringifyNumericFields(array $fields): void
    {
        $merged = [];

        foreach ($fields as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);
            if (is_int($value) || is_float($value)) {
                $merged[$field] = (string) $value;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * Multipart clients often append files as repeated "documents" / "certificates"
     * keys without "[]". PHP then exposes a single UploadedFile, which fails the
     * array rule. Normalize to a list, and accept "certificates" as an alias.
     */
    protected function normalizeDocumentUploads(): void
    {
        if (! $this->hasFile('documents') && $this->hasFile('certificates')) {
            $certificates = $this->file('certificates');
            $this->files->set(
                'documents',
                is_array($certificates) ? array_values($certificates) : [$certificates]
            );
        }

        if (! $this->hasFile('documents')) {
            return;
        }

        $documents = $this->file('documents');

        if ($documents instanceof UploadedFile) {
            $this->files->set('documents', [$documents]);
        }
    }
}
