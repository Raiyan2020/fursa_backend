<?php

namespace App\Http\Requests;

use App\Traits\ResponseTrait;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseRequest extends FormRequest
{
    use ResponseTrait;

    protected function attributeLabel(string $key): string
    {
        $label = __('validation.attributes.'.$key);

        return $label !== 'validation.attributes.'.$key
            ? $label
            : str_replace('_', ' ', $key);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            $this->jsonResponse(
                msg: __('apis.validation_error'),
                code: 422,
                error: true,
                errors: $validator->errors()->toArray(),
                key: 'fail',
            )
        );
    }
}
