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

        // BE-72 — `is_allowed` is a real scan-permission field. On the
        // learn-serve participants export it reads a column the model
        // doesn't have, so it always shows 0 and means nothing.
        $includeScanAllowed = $type !== 'learn-serve';

        // BE-72 — the participants screen shows contact number and the same
        // four guardian columns BE-68 added to the volunteers export.
        $includeGuardianColumns = $type === 'learn-serve';

        $rows = $query->with('user.emergencyContactRelationship')->get()->map(
            function ($row) use ($includeAttendance, $includeScanAllowed, $includeGuardianColumns) {
                $status = $row->registration_status ?? $row->status;
                $user = $row->user;

                $columns = [$row->id, $user?->full_name, $user?->email,
                    $status instanceof \BackedEnum ? $status->value : $status];

                if ($includeAttendance) {
                    $columns[] = (int) ($row->is_attended ?? false);
                }

                if ($includeScanAllowed) {
                    $columns[] = (int) ($row->is_allowed ?? false);
                }

                if ($includeGuardianColumns) {
                    $columns[] = trim(($user?->country_code ?? '').($user?->phone_number ?? ''));
                    $columns[] = $user?->emergency_contact_name;
                    $columns[] = $user?->emergency_contact_phone;
                    $columns[] = $user?->emergency_contact_civil_id;
                    $columns[] = $user?->emergencyContactRelationship?->value_ar;
                }

                return $columns;
            }
        );

        $headers = ['ID', 'Name', 'Email', 'Status'];
        if ($includeAttendance) {
            $headers[] = 'Attended';
        }
        if ($includeScanAllowed) {
            $headers[] = 'Scan allowed';
        }
        if ($includeGuardianColumns) {
            $headers = [...$headers, 'Phone', 'Guardian Name', 'Guardian Phone', 'Guardian Civil ID', 'Guardian Relationship'];
        }

        $url = XlsxExport::store('exports/'.$type.'/'.Str::uuid().'.xlsx', $headers, $rows);

        return ApiResponse::success(['downloadUrl' => url($url)]);
    }
}
