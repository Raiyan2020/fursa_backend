<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatesLicenseRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'license_required' => $this->has('license_required')
                ? $this->input('license_required')
                : '0',
        ]);
    }

    public function rules(): array
    {
        return [
            'license_required' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'license_required' => __('admin.attributes.license_required'),
        ];
    }

    public function messages(): array
    {
        return [
            'license_required.required' => __('admin.messages.required'),
            'license_required.boolean' => __('admin.messages.boolean'),
        ];
    }
}
