<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunity\VolunteerOpportunityRoleResource;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityAssignment;
use App\Models\VolunteerOpportunityRole;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VolunteerOpportunityRoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = VolunteerOpportunityRole::query()->notDeleted();

        if ($opportunityId = $request->query('opportunity_id')) {
            $query->where('opportunity_id', $opportunityId);
        }

        $paginator = $query->paginate(
            min(100, max(1, (int) $request->query('limit', 20))),
            ['*'],
            'page',
            max(1, (int) $request->query('page', 1))
        );

        return ApiResponse::paginated(
            $paginator,
            VolunteerOpportunityRoleResource::collection($paginator->getCollection()),
            'Roles retrieved successfully.',
            'تم استرجاع الأدوار بنجاح.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $role = VolunteerOpportunityRole::query()->notDeleted()->find($id);
        if (! $role) {
            return ApiResponse::error('Role not found.', 'الدور غير موجود.', 404);
        }

        return ApiResponse::success(new VolunteerOpportunityRoleResource($role), 'Role retrieved successfully.', 'تم استرجاع الدور بنجاح.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunity_id' => ['required', 'integer', 'exists:volunteer_opportunities,id'],
            'role_name_en' => ['required', 'string', 'max:100'],
            'role_name_ar' => ['required', 'string', 'max:100'],
            'instructions_en' => ['nullable', 'string'],
            'instructions_ar' => ['nullable', 'string'],
            'participants_needed' => ['required', 'integer', 'min:1'],
        ]);

        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($data['opportunity_id']);
        if (! $opportunity || ($opportunity->created_by !== $request->user()->id && ! $request->user()->isAdmin())) {
            return ApiResponse::error('You can only create roles for opportunities you created.', 'يمكنك فقط إنشاء أدوار للفرص التي أنشأتها.', 403);
        }

        $role = VolunteerOpportunityRole::create($data);

        return ApiResponse::success(new VolunteerOpportunityRoleResource($role), 'Role created successfully.', 'تم إنشاء الدور بنجاح.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = VolunteerOpportunityRole::query()->notDeleted()->with('opportunity')->find($id);
        if (! $role) {
            return ApiResponse::error('Role not found.', 'الدور غير موجود.', 404);
        }

        if ($role->opportunity->created_by !== $request->user()->id && ! $request->user()->isAdmin()) {
            return ApiResponse::error('You can only edit roles for opportunities you created.', 'يمكنك فقط تعديل الأدوار للفرص التي أنشأتها.', 403);
        }

        $data = $request->validate([
            'role_name_en' => ['sometimes', 'string', 'max:100'],
            'role_name_ar' => ['sometimes', 'string', 'max:100'],
            'instructions_en' => ['nullable', 'string'],
            'instructions_ar' => ['nullable', 'string'],
            'participants_needed' => ['sometimes', 'integer', 'min:1'],
        ]);

        $role->update($data);

        return ApiResponse::success(new VolunteerOpportunityRoleResource($role), 'Role updated successfully.', 'تم تحديث الدور بنجاح.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $role = VolunteerOpportunityRole::query()->notDeleted()->with('opportunity')->find($id);
        if (! $role) {
            return ApiResponse::error('Role not found.', 'الدور غير موجود.', 404);
        }

        if ($role->opportunity->created_by !== $request->user()->id && ! $request->user()->isAdmin()) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        $role->softDeleteFlags();

        return ApiResponse::success(null, 'Role deleted successfully.', 'تم حذف الدور بنجاح.');
    }

    public function deleteAll(Request $request, int $opportunity_id): JsonResponse
    {
        $opportunity = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->find($opportunity_id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        if (VolunteerOpportunityAssignment::query()
            ->notDeleted()
            ->whereHas('role', fn ($q) => $q->where('opportunity_id', $opportunity_id))
            ->exists()) {
            return ApiResponse::error(
                'Cannot delete roles. Volunteers are assigned to these roles.',
                'لا يمكن حذف الأدوار. المتطوعون معينون في هذه الأدوار.',
                400
            );
        }

        VolunteerOpportunityRole::query()->where('opportunity_id', $opportunity_id)->delete();

        return ApiResponse::success(null, 'All roles deleted successfully.', 'تم حذف جميع الأدوار بنجاح.');
    }
}
