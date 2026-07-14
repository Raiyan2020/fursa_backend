<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $messageEn = '',
        string $messageAr = '',
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'status' => 'success',
            'code' => $statusCode,
            'message_en' => $messageEn,
            'message_ar' => $messageAr,
            'data' => self::normalizeData($data),
            'meta' => $meta,
        ], $statusCode);
    }

    public static function error(
        string $messageEn = '',
        string $messageAr = '',
        int $statusCode = 400,
        mixed $errors = null,
        mixed $data = null,
        array $meta = []
    ): JsonResponse {
        $payload = [
            'status' => 'error',
            'code' => $statusCode,
            'message_en' => $messageEn,
            'message_ar' => $messageAr,
            'data' => self::normalizeData($data),
            'meta' => $meta,
        ];

        if ($errors !== null) {
            $payload['errors'] = self::normalizeErrors($errors);
        }

        return response()->json($payload, $statusCode);
    }

    public static function paginated(
        LengthAwarePaginator $paginator,
        mixed $data,
        string $messageEn = 'Data retrieved successfully',
        string $messageAr = 'تم استرجاع البيانات بنجاح'
    ): JsonResponse {
        return self::success($data, $messageEn, $messageAr, 200, [
            'pagination' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ],
            'timestamp' => now()->utc()->toIso8601String(),
        ]);
    }

    protected static function normalizeData(mixed $data): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve();
        }

        if ($data instanceof ResourceCollection) {
            return $data->resolve();
        }

        return $data;
    }

    protected static function normalizeErrors(mixed $errors): array
    {
        if (! is_array($errors)) {
            return ['error' => ['en' => (string) $errors, 'ar' => (string) $errors]];
        }

        $normalized = [];
        foreach ($errors as $key => $value) {
            if (is_array($value) && isset($value['en'], $value['ar'])) {
                $normalized[$key] = $value;
                continue;
            }

            if (is_array($value)) {
                $flat = implode(' ', array_map(fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v, $value));
                $normalized[$key] = ['en' => $flat, 'ar' => $flat];
                continue;
            }

            $normalized[$key] = ['en' => (string) $value, 'ar' => (string) $value];
        }

        return $normalized;
    }
}
