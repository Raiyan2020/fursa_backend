<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunity\LearnServeTimeSlotResource;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityAssignment;
use App\Models\LearnServeOpportunityTimeSlot;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearnServeTimeSlotController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $opportunityId = $request->query('opportunity_id');
        if (! $opportunityId) {
            return ApiResponse::error('Opportunity ID is required.', 'معرف الفرصة مطلوب.', 400);
        }

        $opportunity = LearnServeOpportunity::query()->notDeleted()->find($opportunityId);
        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        $slots = LearnServeOpportunityTimeSlot::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunityId)
            ->withCount(['assignments' => fn ($q) => $q->notDeleted()])
            ->get()
            ->filter(function (LearnServeOpportunityTimeSlot $slot) {
                $count = $slot->assignments_count ?? 0;

                return $count < $slot->participants_needed;
            })
            ->values();

        $remaining = $opportunity->participants_needed - LearnServeOpportunityTimeSlot::query()
            ->where('opportunity_id', $opportunityId)
            ->sum('participants_needed');

        return ApiResponse::success([
            'items' => LearnServeTimeSlotResource::collection($slots)->resolve(),
            'remaining_participants' => max(0, $remaining),
        ], 'Time slots retrieved successfully.', 'تم استرجاع الفترات الزمنية بنجاح.');
    }

    public function show(int $id): JsonResponse
    {
        $slot = LearnServeOpportunityTimeSlot::query()->notDeleted()->withCount(['assignments' => fn ($q) => $q->notDeleted()])->find($id);
        if (! $slot) {
            return ApiResponse::error('Time slot not found.', 'الفترة الزمنية غير موجودة.', 404);
        }

        return ApiResponse::success(new LearnServeTimeSlotResource($slot), 'Time slot retrieved successfully.', 'تم استرجاع الفترة الزمنية بنجاح.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunity_id' => ['required', 'integer', 'exists:learn_serve_opportunities,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'participants_needed' => ['required', 'integer', 'min:1'],
        ]);

        $opportunity = LearnServeOpportunity::query()->notDeleted()->find($data['opportunity_id']);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        $slot = LearnServeOpportunityTimeSlot::create($data);

        return ApiResponse::success(new LearnServeTimeSlotResource($slot), 'Time slot created successfully.', 'تم إنشاء الفترة الزمنية بنجاح.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $slot = LearnServeOpportunityTimeSlot::query()->notDeleted()->with('opportunity')->find($id);
        if (! $slot) {
            return ApiResponse::error('Time slot not found.', 'الفترة الزمنية غير موجودة.', 404);
        }

        if ($slot->opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        $data = $request->validate([
            'date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'string'],
            'end_time' => ['sometimes', 'string'],
            'participants_needed' => ['sometimes', 'integer', 'min:1'],
        ]);

        $slot->update($data);

        return ApiResponse::success(new LearnServeTimeSlotResource($slot), 'Time slot updated successfully.', 'تم تحديث الفترة الزمنية بنجاح.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $slot = LearnServeOpportunityTimeSlot::query()->notDeleted()->with('opportunity')->find($id);
        if (! $slot) {
            return ApiResponse::error('Time slot not found.', 'الفترة الزمنية غير موجودة.', 404);
        }

        if ($slot->opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        $slot->softDeleteFlags();

        return ApiResponse::success(null, 'Time slot deleted successfully.', 'تم حذف الفترة الزمنية بنجاح.');
    }

    public function deleteAll(Request $request, int $opportunity_id): JsonResponse
    {
        $opportunity = LearnServeOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->find($opportunity_id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        if (LearnServeOpportunityAssignment::query()
            ->notDeleted()
            ->whereHas('timeSlot', fn ($q) => $q->where('opportunity_id', $opportunity_id))
            ->exists()) {
            return ApiResponse::error(
                'Cannot delete time slots. Volunteers are registered in these time slots.',
                'لا يمكن حذف الفترات الزمنية. المتطوعون مسجلون في هذه الفترات الزمنية.',
                400
            );
        }

        LearnServeOpportunityTimeSlot::query()->where('opportunity_id', $opportunity_id)->delete();

        return ApiResponse::success(null, 'All time slots deleted successfully.', 'تم حذف جميع الفترات الزمنية بنجاح.');
    }
}
