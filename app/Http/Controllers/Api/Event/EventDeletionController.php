<?php

namespace App\Http\Controllers\Api\Event;

use App\Enums\DeletionStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventDeletionController extends Controller
{
    public function requestDeletion(Request $request, int $event_id): JsonResponse
    {
        $org = $request->user()->organizationProfile;
        if (! $org) {
            return ApiResponse::error('Only organizations can request event deletion.', 'يمكن فقط للمنظمات طلب حذف الأحداث.', 403);
        }

        $event = Event::query()
            ->notDeleted()
            ->where('id', $event_id)
            ->where('created_by', $org->id)
            ->first();

        if (! $event) {
            return ApiResponse::error('Event not found.', 'الحدث غير موجود.', 404);
        }

        if ($event->deletion_status === DeletionStatus::PENDING) {
            return ApiResponse::error(
                'A deletion request for this event is already pending.',
                'طلب حذف هذا الحدث قيد الانتظار بالفعل.',
                400
            );
        }

        if ($event->due_date && $event->due_date->diffInDays(now()) < 7) {
            return ApiResponse::error(
                'Events can only be deleted up to one week before the due date.',
                'يمكن حذف الأحداث فقط حتى أسبوع واحد قبل تاريخ الاستحقاق.',
                400
            );
        }

        $event->update(['deletion_status' => DeletionStatus::PENDING]);

        return ApiResponse::success(
            null,
            'Your deletion request has been submitted and is awaiting admin approval.',
            'لقد تم تقديم طلب الحذف الخاص بك وهو الآن في انتظار موافقة المسؤول.'
        );
    }

    public function adminAction(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->is_staff && ! $user->is_superuser) {
            return ApiResponse::error('Admin access required.', 'مطلوب وصول المسؤول.', 403);
        }

        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'action' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $event = Event::query()->notDeleted()->find($data['event_id']);
        if (! $event || $event->deletion_status !== DeletionStatus::PENDING) {
            return ApiResponse::error('No pending deletion request for this event.', 'لا يوجد طلب حذف معلق لهذا الحدث.', 400);
        }

        if ($data['action'] === 'approve') {
            $event->update([
                'deletion_status' => DeletionStatus::APPROVED,
                'is_deleted' => true,
                'deleted_at' => now(),
            ]);
            $messageEn = 'Event deletion approved.';
            $messageAr = 'تمت الموافقة على حذف الحدث.';
        } else {
            $event->update([
                'deletion_status' => DeletionStatus::REJECTED,
                'deletion_rejected_reason' => $data['rejection_reason'] ?? null,
            ]);
            $messageEn = 'Event deletion request rejected.';
            $messageAr = 'تم رفض طلب حذف الحدث.';
        }

        return ApiResponse::success(null, $messageEn, $messageAr);
    }
}
