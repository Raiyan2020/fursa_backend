<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Controller;
use App\Http\Resources\Notification\UserNotificationResource;
use App\Models\UserNotification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = UserNotification::query()
            ->notDeleted()
            ->where('user_id', $request->user()->id)
            ->with('notification')
            ->latest();

        if ($request->query('is_read') === 'true') {
            $query->where('is_read', true);
        } elseif ($request->query('is_read') === 'false') {
            $query->where('is_read', false);
        }

        $unreadCount = UserNotification::query()
            ->notDeleted()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        $pageParam = $request->query('page');
        $limitParam = $request->query('limit');

        if ($pageParam !== null || $limitParam !== null) {
            $page = max(1, (int) ($pageParam ?? 1));
            $limit = min(100, max(1, (int) ($limitParam ?? 20)));
            $paginator = $query->paginate($limit, ['*'], 'page', $page);

            $response = ApiResponse::paginated(
                $paginator,
                UserNotificationResource::collection($paginator->getCollection()),
                'Data retrieved successfully.',
                'تم استرجاع البيانات بنجاح.'
            );
            $payload = $response->getData(true);
            $payload['unread_count'] = $unreadCount;

            return response()->json($payload, $response->getStatusCode());
        }

        $items = UserNotificationResource::collection($query->get())->resolve();

        return response()->json([
            'key' => 'success',
            'msg' => app()->getLocale() === 'en'
                ? 'Data retrieved successfully.'
                : 'تم استرجاع البيانات بنجاح.',
            'code' => 200,
            'response_status' => ['error' => false, 'validation_errors' => []],
            'data' => $items,
            'unread_count' => $unreadCount,
        ], 200);
    }

    public function markRead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notification_ids' => ['nullable', 'array'],
            'notification_ids.*' => ['integer'],
            'mark_all' => ['nullable', 'boolean'],
            'is_read' => ['required', 'boolean'],
        ]);

        if (empty($data['notification_ids']) && empty($data['mark_all'])) {
            return ApiResponse::error(
                "Either 'notification_ids' or 'mark_all' must be provided.",
                "يجب تقديم 'notification_ids' أو 'mark_all'.",
                400
            );
        }
        if (! empty($data['notification_ids']) && ! empty($data['mark_all'])) {
            return ApiResponse::error(
                "Only one of 'notification_ids' or 'mark_all' should be provided.",
                "يجب تقديم 'notification_ids' أو 'mark_all' فقط.",
                400
            );
        }

        $query = UserNotification::query()
            ->notDeleted()
            ->where('user_id', $request->user()->id);

        if (! empty($data['mark_all'])) {
            $query->update(['is_read' => $data['is_read']]);
        } else {
            $query->whereIn('id', $data['notification_ids'])->update(['is_read' => $data['is_read']]);
        }

        if ($data['is_read']) {
            return ApiResponse::success(null, 'Notifications marked as read.', 'تم وضع علامة مقروءة على الإشعارات.');
        }

        return ApiResponse::success(null, 'Notifications marked as unread.', 'تم وضع علامة غير مقروءة على الإشعارات.');
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notification_ids' => ['nullable', 'array'],
            'notification_ids.*' => ['integer'],
            'delete_all' => ['nullable', 'boolean'],
        ]);

        if (empty($data['notification_ids']) && empty($data['delete_all'])) {
            return ApiResponse::error(
                "Either 'notification_ids' or 'delete_all' must be provided.",
                "يجب تقديم 'notification_ids' أو 'delete_all'.",
                400
            );
        }
        if (! empty($data['notification_ids']) && ! empty($data['delete_all'])) {
            return ApiResponse::error(
                "Only one of 'notification_ids' or 'delete_all' should be provided.",
                "يجب تقديم 'notification_ids' أو 'delete_all' فقط.",
                400
            );
        }

        $query = UserNotification::query()
            ->notDeleted()
            ->where('user_id', $request->user()->id);

        if (! empty($data['delete_all'])) {
            $query->update(['is_deleted' => true, 'deleted_at' => now()]);
        } else {
            $query->whereIn('id', $data['notification_ids'])->update(['is_deleted' => true, 'deleted_at' => now()]);
        }

        return ApiResponse::success(null, 'Notifications deleted successfully.', 'تم حذف الإشعارات بنجاح.');
    }
}
