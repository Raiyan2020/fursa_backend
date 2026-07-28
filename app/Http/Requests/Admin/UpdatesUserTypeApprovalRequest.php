<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatesUserTypeApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requires_approval' => $this->has('requires_approval')
                ? $this->input('requires_approval')
                : '0',
        ]);
    }

    public function rules(): array
    {
        return [
            'requires_approval' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'requires_approval' => __('admin.attributes.requires_approval'),
        ];
    }

    public function messages(): array
    {
        return [
            'requires_approval.required' => __('admin.messages.required'),
            'requires_approval.boolean' => __('admin.messages.boolean'),
        ];
    }
}
