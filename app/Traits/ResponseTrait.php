<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

trait ResponseTrait
{
    public function jsonResponse(
        ?string $msg = null,
        int $code = 200,
        mixed $data = [],
        bool $error = false,
        array $errors = [],
        ?string $key = null
    ): JsonResponse {
        return response()->json([
            'key' => $key ?? ($error ? 'fail' : 'success'),
            'msg' => $msg ?? __('apis.data_retrieved_successfully'),
            'code' => $code,
            'response_status' => [
                'error' => $error,
                'validation_errors' => $errors,
            ],
            'data' => $this->checkIfEmpty($data) ? null : $this->normalizePayload($data),
        ], $code);
    }

    protected function checkIfEmpty(mixed $data): bool
    {
        if ($data instanceof AnonymousResourceCollection) {
            return $data->collection->isEmpty();
        }

        return empty($data) && $data !== 0 && $data !== '0' && $data !== false;
    }

    protected function normalizePayload(mixed $data): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve();
        }

        return $data;
    }
}
