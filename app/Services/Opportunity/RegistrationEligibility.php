<?php

namespace App\Services\Opportunity;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\Event;
use App\Models\VolunteerOpportunity;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RegistrationEligibility
{
    public static function reject(object $item): ?JsonResponse
    {
        if ($item->is_deleted || $item->approval_status !== ApprovalStatus::APPROVED
            || ($item instanceof VolunteerOpportunity && ! $item->is_public)) {
            return ApiResponse::error('Item not found.', 'العنصر غير موجود.', 404);
        }
        $status = $item instanceof Event ? $item->event_status : $item->opportunity_status;
        if (! in_array($status, [OpportunityStatus::UPCOMING, OpportunityStatus::INPROGRESS], true)
            || $item->hasEnded() || ! $item->isRegistrationOpen()) {
            return ApiResponse::error('Registration is closed.', 'التسجيل مغلق.', 400);
        }
        $statusColumn = $item instanceof Event ? 'registration_status' : 'status';
        $count = $item->registrations()->notDeleted()->whereIn($statusColumn,
            [ApprovalStatus::PENDING, ApprovalStatus::APPROVED])->count();
        if ($item->participants_needed > 0 && $count >= $item->participants_needed) {
            return ApiResponse::error('No remaining slots.', 'لا توجد أماكن متبقية.', 400);
        }

        return null;
    }
}
