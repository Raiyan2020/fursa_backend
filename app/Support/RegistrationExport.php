<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class RegistrationExport
{
    public static function download($query, string $type): JsonResponse
    {
        // Events have no attendance record (BE-41): an "Attended" column on
        // that export would be meaningless, since nothing ever writes it.
        $includeAttendance = $type !== 'events';

        $rows = $query->with('user')->get()->map(function ($row) use ($includeAttendance) {
            $status = $row->registration_status ?? $row->status;

            $columns = [$row->id, $row->user?->full_name, $row->user?->email,
                $status instanceof \BackedEnum ? $status->value : $status];

            if ($includeAttendance) {
                $columns[] = (int) ($row->is_attended ?? false);
            }

            $columns[] = (int) ($row->is_allowed ?? false);

            return $columns;
        });

        $headers = ['ID', 'Name', 'Email', 'Status'];
        if ($includeAttendance) {
            $headers[] = 'Attended';
        }
        $headers[] = 'Scan allowed';

        $url = XlsxExport::store('exports/'.$type.'/'.Str::uuid().'.xlsx', $headers, $rows);

        return ApiResponse::success(['downloadUrl' => url($url)]);
    }
}
