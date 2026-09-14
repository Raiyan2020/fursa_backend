<?php

namespace App\Http\Controllers\Api\Base;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\LearnServeOpportunity;
use App\Models\OrganizationProfile;
use App\Models\VolunteerOpportunity;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Force-approve a just-created opportunity/event so a local Postman/newman
 * run can exercise registration and certificate flows without a manual
 * admin-dashboard approval step. Same environment gate as
 * `expose_otp_in_response` (config/fursa.php) — a 404 everywhere but
 * local/testing, so this can never do anything in production.
 */
class TestSupportController extends Controller
{
    private const MODELS = [
        'volunteer_opportunity' => VolunteerOpportunity::class,
        'learn_serve_opportunity' => LearnServeOpportunity::class,
        'event' => Event::class,
    ];

    public function approve(string $type, int $id): JsonResponse
    {
        if (! app()->environment(['local', 'testing'])) {
            return ApiResponse::error('Not found.', 'غير موجود.', 404);
        }

        $modelClass = self::MODELS[$type] ?? null;
        $model = $modelClass ? $modelClass::query()->find($id) : null;

        if (! $model) {
            return ApiResponse::error('Not found.', 'غير موجود.', 404);
        }

        $model->approval_status = ApprovalStatus::APPROVED;
        $model->save();

        return ApiResponse::success(
            ['id' => $model->id, 'approval_status' => $model->approval_status->value],
            'Approved for local testing.',
            'تمت الموافقة لأغراض الاختبار المحلي.'
        );
    }

    /**
     * Flips an organization's approval status without touching its existing
     * token, so a Postman/newman run can reproduce BE-36's exact scenario: a
     * token issued while approved, then the admin rejects the org from under
     * it. `EnsureOrganizationApproved` is what's actually being verified here,
     * not this helper.
     */
    public function setOrganizationStatus(int $userId, string $status): JsonResponse
    {
        if (! app()->environment(['local', 'testing'])) {
            return ApiResponse::error('Not found.', 'غير موجود.', 404);
        }

        if (! in_array($status, ApprovalStatus::values(), true)) {
            return ApiResponse::error('Not found.', 'غير موجود.', 404);
        }

        $profile = OrganizationProfile::query()->where('user_id', $userId)->first();

        if (! $profile) {
            return ApiResponse::error('Not found.', 'غير موجود.', 404);
        }

        $profile->organization_status = $status;
        $profile->save();

        return ApiResponse::success(
            ['user_id' => $userId, 'organization_status' => $profile->organization_status->value],
            'Organization status changed for local testing.',
            'تم تغيير حالة الجهة لأغراض الاختبار المحلي.'
        );
    }
}
