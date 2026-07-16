<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\CustomUserResource;
use App\Http\Resources\Volunteer\VolunteerProfileWithUserResource;
use App\Models\Event;
use App\Models\ScanPermission;
use App\Models\VolunteerOpportunity;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanPermissionController extends Controller
{
    public function bulkUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunity_id' => ['nullable', 'integer', 'exists:volunteer_opportunities,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'permissions.*.is_allowed' => ['required', 'boolean'],
        ]);

        if (empty($data['opportunity_id']) && empty($data['event_id'])) {
            return ApiResponse::error('Either opportunity_id or event_id is required.', 'مطلوب opportunity_id أو event_id.', 400);
        }

        if (! empty($data['opportunity_id'])) {
            $opportunity = VolunteerOpportunity::query()->find($data['opportunity_id']);
            if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
                return ApiResponse::error(
                    'Only the opportunity creator can update scan permissions.',
                    'فقط منشئ الفرصة يمكنه تحديث أذونات المسح.',
                    403
                );
            }
        }

        if (! empty($data['event_id'])) {
            $event = Event::query()->with('organization')->find($data['event_id']);
            if (! $event || $event->organization?->user_id !== $request->user()->id) {
                return ApiResponse::error(
                    'Only the event creator can update scan permissions.',
                    'فقط منشئ الحدث يمكنه تحديث أذونات المسح.',
                    403
                );
            }
        }

        $results = [];
        foreach ($data['permissions'] as $entry) {
            $permission = ScanPermission::query()->updateOrCreate(
                [
                    'user_id' => $entry['user_id'],
                    'opportunity_id' => $data['opportunity_id'] ?? null,
                    'event_id' => $data['event_id'] ?? null,
                ],
                ['is_allowed' => $entry['is_allowed'], 'is_deleted' => false, 'deleted_at' => null]
            );

            $results[] = [
                'user_id' => $entry['user_id'],
                'is_allowed' => $permission->is_allowed,
                'scan_permission_id' => $permission->id,
            ];
        }

        return ApiResponse::success($results, 'Scan permissions updated successfully.', 'تم تحديث أذونات المسح بنجاح.');
    }

    public function list(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunity_id' => ['nullable', 'integer', 'exists:volunteer_opportunities,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'search' => ['nullable', 'string'],
        ]);

        if (empty($data['opportunity_id']) && empty($data['event_id'])) {
            return ApiResponse::error('Either opportunity_id or event_id is required.', 'مطلوب opportunity_id أو event_id.', 400);
        }

        if (! empty($data['opportunity_id'])) {
            $opportunity = VolunteerOpportunity::query()->find($data['opportunity_id']);
            if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
                return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
            }
        }

        $query = ScanPermission::query()
            ->notDeleted()
            ->where('is_allowed', true)
            ->with(['user.volunteerProfile']);

        if (! empty($data['opportunity_id'])) {
            $query->where('opportunity_id', $data['opportunity_id']);
        } else {
            $query->where('event_id', $data['event_id']);
        }

        if (! empty($data['search'])) {
            $search = $data['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $results = $query->get()->map(function (ScanPermission $scan) use ($request) {
            $userData = (new CustomUserResource($scan->user))->resolve();
            if ($scan->user?->volunteerProfile) {
                $userData['volunteer_profile'] = (new VolunteerProfileWithUserResource($scan->user->volunteerProfile))->resolve();
            }
            $userData['is_allowed'] = $scan->is_allowed;
            $userData['scan_permission_id'] = $scan->id;

            return $userData;
        })->values();

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $total = $results->count();
        $items = $results->slice(($page - 1) * $limit, $limit)->values();
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator($items, $total, $limit, $page);

        return ApiResponse::paginated(
            $paginator,
            $items,
            'Scan permissions retrieved successfully.',
            'تم استرجاع أذونات المسح بنجاح.'
        );
    }
}
