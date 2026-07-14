<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
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
            'nationality' => ['nullable', 'string'],
            'birth_year' => ['nullable', 'integer'],
            'dob' => ['nullable', 'date'],
            'preferred_language' => ['nullable', 'in:en,ar'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'civil_id' => ['nullable', 'string', 'max:12'],
            'volunteer_is_verified' => ['nullable', 'boolean'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_country_code' => ['nullable', 'string', 'max:10'],
            'emergency_contact_civil_id' => ['nullable', 'string', 'max:12', 'regex:/^[23]\d{11}$/'],
            'emergency_contact_relationship' => ['nullable', 'integer', 'exists:master_choices,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $userType = $this->input('user_type', UserType::VOLUNTEER->value);
            $civilId = trim((string) $this->input('civil_id', ''));

            if ($userType === UserType::VOLUNTEER->value) {
                if ($civilId === '') {
                    $validator->errors()->add('civil_id', 'Civil ID is required / الرقم المدني مطلوب');
                } elseif (User::query()->where('civil_id', $civilId)->exists()) {
                    $validator->errors()->add('civil_id', 'This Civil ID is already registered. / هذا الرقم المدني مسجل بالفعل.');
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
                            $validator->errors()->add($field, 'Emergency contact is required for volunteers under 18');
                        }
                    }
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }
}
