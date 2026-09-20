<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\CustomUserResource;
use App\Http\Resources\Volunteer\VolunteerProfileWithUserResource;
use App\Models\AttendancePermission;
use App\Models\VolunteerOpportunity;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * BE-69 — «إذن تحضير»: grant/revoke/list for the attendance-management
 * permission, scoped to a volunteer opportunity. Same three request shapes
 * as `/scan-permissions/` (single subject, deliberately separate table —
 * see AttendancePermission's docblock).
 */
class AttendancePermissionController extends Controller
{
    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->normalizeBulkPermissions($request);

        $data = $request->validate([
            'opportunity_id' => ['required', 'integer', 'exists:volunteer_opportunities,id'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'permissions.*.is_allowed' => ['required', 'boolean'],
        ], [
            'permissions.required' => __('Provide either permissions[] or user_ids[] with is_allowed.'),
        ]);

        $opportunity = VolunteerOpportunity::query()->find($data['opportunity_id']);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error(
                'Only the opportunity creator can update attendance permissions.',
                'فقط منشئ الفرصة يمكنه تحديث أذونات التحضير.',
                403
            );
        }

        $results = [];
        foreach ($data['permissions'] as $entry) {
            $permission = AttendancePermission::query()->updateOrCreate(
                [
                    'user_id' => $entry['user_id'],
                    'opportunity_id' => $data['opportunity_id'],
                ],
                ['is_allowed' => $entry['is_allowed'], 'is_deleted' => false, 'deleted_at' => null]
            );

            $results[] = [
                'user_id' => $entry['user_id'],
                'is_allowed' => $permission->is_allowed,
                'attendance_permission_id' => $permission->id,
            ];
        }

        return ApiResponse::success($results, 'Attendance permissions updated successfully.', 'تم تحديث أذونات التحضير بنجاح.');
    }

    /**
     * Accept the flat `user_ids` + `is_allowed` form by rewriting it into the
     * canonical `permissions` array before validation runs. Mirrors
     * ScanPermissionController::normalizeBulkPermissions().
     */
    protected function normalizeBulkPermissions(Request $request): void
    {
        if ($request->has('permissions')) {
            return;
        }

        $userIds = $request->input('user_ids');
        if ($userIds === null) {
            return;
        }

        if (! is_array($userIds)) {
            $userIds = [$userIds];
        }

        $isAllowed = $request->has('is_allowed')
            ? $request->boolean('is_allowed')
            : true;

        $permissions = [];
        foreach ($userIds as $userId) {
            if ($userId === null || $userId === '') {
                continue;
            }

            $permissions[] = [
                'user_id' => (int) $userId,
                'is_allowed' => $isAllowed,
            ];
        }

        if ($permissions !== []) {
            $request->merge(['permissions' => $permissions]);
        }
    }

    public function list(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunity_id' => ['required', 'integer', 'exists:volunteer_opportunities,id'],
            'search' => ['nullable', 'string'],
        ]);

        $opportunity = VolunteerOpportunity::query()->find($data['opportunity_id']);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        $query = AttendancePermission::query()
            ->notDeleted()
            ->where('opportunity_id', $data['opportunity_id'])
            ->where('is_allowed', true)
            ->with(['user.volunteerProfile']);

        if (! empty($data['search'])) {
            // Same people-picker search as BE-67.4 / scan-permissions.
            $search = $data['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('civil_id', 'like', "%{$search}%")
                    ->orWhere('passport_number', 'like', "%{$search}%");
            });
        }

        $results = $query->get()->map(function (AttendancePermission $permission) {
            $userData = (new CustomUserResource($permission->user))->resolve();
            if ($permission->user?->volunteerProfile) {
                $userData['volunteer_profile'] = (new VolunteerProfileWithUserResource($permission->user->volunteerProfile))->resolve();
            }
            $userData['is_allowed'] = $permission->is_allowed;
            $userData['attendance_permission_id'] = $permission->id;

            return $userData;
        })->values();

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $total = $results->count();
        $items = $results->slice(($page - 1) * $limit, $limit)->values();
        $paginator = new LengthAwarePaginator($items, $total, $limit, $page);

        return ApiResponse::paginated(
            $paginator,
            $items,
            'Attendance permissions retrieved successfully.',
            'تم استرجاع أذونات التحضير بنجاح.'
        );
    }
}
