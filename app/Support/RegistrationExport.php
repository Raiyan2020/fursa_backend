<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class RegistrationExport
{
    public static function download($query, string $type): JsonResponse
    {
        $rows = $query->with('user')->get()->map(function ($row) {
            $status = $row->registration_status ?? $row->status;

            return [$row->id, $row->user?->full_name, $row->user?->email,
                $status instanceof \BackedEnum ? $status->value : $status,
                (int) ($row->is_attended ?? false), (int) ($row->is_allowed ?? false)];
        });
        $url = XlsxExport::store('exports/'.$type.'/'.Str::uuid().'.xlsx',
            ['ID', 'Name', 'Email', 'Status', 'Attended', 'Scan allowed'], $rows);

        return ApiResponse::success(['downloadUrl' => url($url)]);
    }
}
