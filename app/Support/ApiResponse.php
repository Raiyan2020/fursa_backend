<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Unified API envelope (Tanal-compatible).
 *
 * {
 *   "key": "success|fail",
 *   "msg": "...",
 *   "code": 200,
 *   "response_status": { "error": false, "validation_errors": [] },
 *   "data": {}
 * }
 */
class ApiResponse
{
    /**
     * New: success($data, $msg, $code = 200)
     * Legacy: success($data, $messageEn, $messageAr, $code = 200)
     */
    public static function success(
        mixed $data = null,
        ?string $msg = null,
        mixed $third = 200,
        mixed $fourth = null
    ): JsonResponse {
        [$resolvedMsg, $code] = self::resolveMsgAndCode($msg, $third, $fourth, __('apis.data_retrieved_successfully'));

        return self::make(
            key: 'success',
            msg: $resolvedMsg,
            code: $code,
            data: $data,
            error: false,
            errors: []
        );
    }

    /**
     * Preferred error helper.
     */
    public static function fail(
        ?string $msg = null,
        int $code = 400,
        array $errors = [],
        mixed $data = null
    ): JsonResponse {
        return self::make(
            key: 'fail',
            msg: $msg ?? __('apis.operation_failed'),
            code: $code,
            data: $data,
            error: true,
            errors: $errors
        );
    }

    /**
     * Legacy: error($messageEn, $messageAr, $statusCode, $errors, $data)
     */
    public static function error(
        ?string $messageEn = null,
        ?string $messageAr = null,
        int $statusCode = 400,
        mixed $errors = null,
        mixed $data = null
    ): JsonResponse {
        $msg = app()->getLocale() === 'en'
            ? ($messageEn ?: $messageAr)
            : ($messageAr ?: $messageEn);

        return self::fail(
            msg: $msg ?: __('apis.operation_failed'),
            code: $statusCode,
            errors: self::normalizeErrors($errors),
            data: $data
        );
    }

    /**
     * New: paginated($paginator, $items, $msg)
     * Legacy: paginated($paginator, $items, $messageEn, $messageAr)
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        mixed $items,
        ?string $msg = null,
        mixed $third = null
    ): JsonResponse {
        if ($items instanceof JsonResource) {
            $items = $items->resolve();
        }

        if (is_string($third)) {
            $msg = app()->getLocale() === 'en' ? ($msg ?: $third) : $third;
        }

        return self::success([
            'items' => $items,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ], $msg ?? __('apis.data_retrieved_successfully'));
    }

    protected static function resolveMsgAndCode(
        ?string $msg,
        mixed $third,
        mixed $fourth,
        string $defaultMsg
    ): array {
        // Legacy bilingual: success($data, $en, $ar) or success($data, $en, $ar, $code)
        if (is_string($third)) {
            $resolved = app()->getLocale() === 'en' ? ($msg ?: $third) : $third;

            return [$resolved ?: $defaultMsg, is_int($fourth) ? $fourth : 200];
        }

        return [$msg ?: $defaultMsg, is_int($third) ? $third : 200];
    }

    protected static function make(
        string $key,
        string $msg,
        int $code,
        mixed $data,
        bool $error,
        array $errors
    ): JsonResponse {
        if ($data instanceof JsonResource) {
            $data = $data->resolve();
        }

        $isEmpty = empty($data) && $data !== 0 && $data !== '0' && $data !== false;

        return response()->json([
            'key' => $key,
            'msg' => $msg,
            'code' => $code,
            'response_status' => [
                'error' => $error,
                'validation_errors' => $errors,
            ],
            'data' => $isEmpty ? null : $data,
        ], $code);
    }

    protected static function normalizeErrors(mixed $errors): array
    {
        if ($errors === null) {
            return [];
        }

        if (! is_array($errors)) {
            return ['error' => [(string) $errors]];
        }

        $first = reset($errors);
        if (is_array($first) && array_is_list($first) && isset($first[0]) && is_string($first[0])) {
            return $errors;
        }

        $normalized = [];
        foreach ($errors as $key => $value) {
            if (is_array($value) && isset($value['en'], $value['ar'])) {
                $normalized[$key] = [
                    app()->getLocale() === 'en' ? (string) $value['en'] : (string) $value['ar'],
                ];
                continue;
            }

            if (is_array($value)) {
                if (array_is_list($value)) {
                    $normalized[$key] = array_map(
                        static fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v,
                        $value
                    );
                } else {
                    $normalized[$key] = [implode(' ', array_map('strval', $value))];
                }
                continue;
            }

            $normalized[$key] = [(string) $value];
        }

        return $normalized;
    }
}
