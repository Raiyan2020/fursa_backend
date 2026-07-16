<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunity\VolunteerOpportunityTeamResource;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityTeam;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VolunteerOpportunityTeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = VolunteerOpportunityTeam::query()->notDeleted();

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
            VolunteerOpportunityTeamResource::collection($paginator->getCollection()),
            'Teams retrieved successfully.',
            'تم استرجاع الفرق بنجاح.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $team = VolunteerOpportunityTeam::query()->notDeleted()->find($id);
        if (! $team) {
            return ApiResponse::error('Team not found.', 'الفريق غير موجود.', 404);
        }

        return ApiResponse::success(new VolunteerOpportunityTeamResource($team), 'Team retrieved successfully.', 'تم استرجاع الفريق بنجاح.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunity_id' => ['required', 'integer', 'exists:volunteer_opportunities,id'],
            'team_name_en' => ['required', 'string', 'max:255'],
            'team_name_ar' => ['required', 'string', 'max:255'],
        ]);

        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($data['opportunity_id']);
        if (! $opportunity || ($opportunity->created_by !== $request->user()->id && ! $request->user()->isAdmin())) {
            return ApiResponse::error('You can only create teams for opportunities you created.', 'يمكنك فقط إنشاء فرق للفرص التي أنشأتها.', 403);
        }

        $team = VolunteerOpportunityTeam::create($data);

        return ApiResponse::success(new VolunteerOpportunityTeamResource($team), 'Team created successfully.', 'تم إنشاء الفريق بنجاح.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $team = VolunteerOpportunityTeam::query()->notDeleted()->with('opportunity')->find($id);
        if (! $team) {
            return ApiResponse::error('Team not found.', 'الفريق غير موجود.', 404);
        }

        if ($team->opportunity->created_by !== $request->user()->id && ! $request->user()->isAdmin()) {
            return ApiResponse::error('You can only edit teams for opportunities you created.', 'يمكنك فقط تعديل الفرق للفرص التي أنشأتها.', 403);
        }

        $data = $request->validate([
            'team_name_en' => ['sometimes', 'string', 'max:255'],
            'team_name_ar' => ['sometimes', 'string', 'max:255'],
        ]);

        $team->update($data);

        return ApiResponse::success(new VolunteerOpportunityTeamResource($team), 'Team updated successfully.', 'تم تحديث الفريق بنجاح.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $team = VolunteerOpportunityTeam::query()->notDeleted()->with('opportunity')->find($id);
        if (! $team) {
            return ApiResponse::error('Team not found.', 'الفريق غير موجود.', 404);
        }

        if ($team->opportunity->created_by !== $request->user()->id && ! $request->user()->isAdmin()) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        $team->softDeleteFlags();

        return ApiResponse::success(null, 'Team deleted successfully.', 'تم حذف الفريق بنجاح.');
    }
}
