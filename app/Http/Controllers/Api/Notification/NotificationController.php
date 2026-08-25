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
        // A notification deleted from the dashboard soft-deletes the parent row,
        // so the per-user rows must be filtered by it too — otherwise it keeps
        // showing on the site after the admin deletes it.
        $query = UserNotification::query()
            ->notDeleted()
            ->where('user_id', $request->user()->id)
            ->whereHas('notification', fn ($q) => $q->where('is_deleted', false))
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
            ->whereHas('notification', fn ($q) => $q->where('is_deleted', false))
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
        $this->normalizeNotificationIds($request);

        // Marking one notification read is the overwhelmingly common case, so a
        // missing `is_read` means "read" rather than a validation error.
        if (! $request->has('is_read')) {
            $request->merge(['is_read' => true]);
        }

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
        $this->normalizeNotificationIds($request);

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

    /**
     * Accept the shapes clients actually send for a single notification:
     * `notification_id: 5`, `notification_ids: 5`, or `id: 5` — all normalised
     * to the `notification_ids: [5]` array the endpoints validate.
     */
    protected function normalizeNotificationIds(Request $request): void
    {
        if (! $request->has('notification_ids')) {
            foreach (['notification_id', 'id'] as $alias) {
                if ($request->has($alias)) {
                    $request->merge(['notification_ids' => $request->input($alias)]);
                    break;
                }
            }
        }

        $ids = $request->input('notification_ids');

        if ($ids !== null && ! is_array($ids)) {
            $request->merge(['notification_ids' => [$ids]]);
        }
    }
}
