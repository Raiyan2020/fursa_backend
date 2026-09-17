<?php

namespace App\Http\Controllers\Api\Opportunity\Concerns;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * BE-61 Part C — shared gate for the organizer-scans-volunteer endpoints
 * being retired (scan/, scan-permissions/*).
 *
 * Kept behind `fursa.organizer_scan_flow_enabled` rather than deleted, so the
 * code is ready without going live before the frontend drops its matching
 * screens in the same release.
 */
trait GatesOrganizerScanFlow
{
    protected function rejectIfOrganizerScanRetired(): ?JsonResponse
    {
        if (config('fursa.organizer_scan_flow_enabled')) {
            return null;
        }

        return ApiResponse::error(
            'This endpoint has been retired. Volunteers now check themselves in — see self-scan.',
            'تم إيقاف هذا المسار. المتطوع الآن يسجل حضوره بنفسه — راجع التحضير الذاتي.',
            410
        );
    }
}
