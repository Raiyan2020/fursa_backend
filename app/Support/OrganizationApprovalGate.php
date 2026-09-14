<?php

namespace App\Support;

use App\Enums\ApprovalStatus;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * The single definition of "this organization may act".
 *
 * Approval used to be consulted only while issuing a token at login, which made
 * a later rejection inert for the token's whole lifetime. Both the login path
 * and the request middleware now call the same check, so the two cannot drift
 * apart and the response body stays identical wherever the gate closes.
 */
class OrganizationApprovalGate
{
    /**
     * The rejection response for this user, or null when they may proceed.
     *
     * Volunteers and approved organizations pass through untouched.
     */
    public static function denialResponse(?User $user): ?JsonResponse
    {
        if (! $user || $user->user_type !== UserType::ORGANIZATION) {
            return null;
        }

        $status = $user->organizationProfile?->organization_status;

        if ($status === ApprovalStatus::PENDING || $status === null) {
            return ApiResponse::error(
                'Your organization account has not been approved by the admin yet.',
                'لم يتم تأكيد حساب الجهة من قبل الإدارة بعد.',
                403,
                [
                    'organization_status' => [
                        'en' => 'Your organization account is pending admin approval.',
                        'ar' => 'حساب الجهة في انتظار موافقة الإدارة.',
                    ],
                ]
            );
        }

        if ($status === ApprovalStatus::REJECTED) {
            return ApiResponse::error(
                'Your organization account was rejected by the admin.',
                'تم رفض حساب الجهة من قبل الإدارة.',
                403,
                [
                    'organization_status' => [
                        'en' => 'Your organization account was rejected by the admin.',
                        'ar' => 'تم رفض حساب الجهة من قبل الإدارة.',
                    ],
                ]
            );
        }

        if ($status !== ApprovalStatus::APPROVED) {
            return ApiResponse::error(
                'Your organization account is not approved.',
                'حساب الجهة غير معتمد.',
                403
            );
        }

        return null;
    }
}
