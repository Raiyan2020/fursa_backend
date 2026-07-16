<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Api\Opportunity\Concerns\HandlesOpportunities;
use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunity\LearnServeOpportunityRegistrationResource;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityAssignment;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\LearnServeOpportunityTimeSlot;
use App\Services\Opportunity\SyncService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LearnServeRegistrationController extends Controller
{
    use HandlesOpportunities;

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunity_id' => ['required', 'integer', 'exists:learn_serve_opportunities,id'],
            'time_slot_id' => ['nullable', 'integer', 'exists:learn_serve_opportunity_time_slots,id'],
        ]);

        $user = $request->user();
        $opportunity = LearnServeOpportunity::query()->notDeleted()->find($data['opportunity_id']);
        if (! $opportunity) {
            return ApiResponse::error('Opportunity does not exist.', 'الفرصة غير موجودة.', 404);
        }

        if (LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity->id)
            ->where('user_id', $user->id)
            ->exists()) {
            return ApiResponse::error('You are already registered for this opportunity.', 'أنت مسجل بالفعل في هذه الفرصة.', 400);
        }

        $totalRegistrations = LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity->id)
            ->count();
        if ($totalRegistrations >= $opportunity->participants_needed) {
            return ApiResponse::error('No remaining slots available.', 'لا توجد فتحات متبقية.', 400);
        }

        $timeSlot = null;
        if (! empty($data['time_slot_id'])) {
            $timeSlot = LearnServeOpportunityTimeSlot::query()->notDeleted()->find($data['time_slot_id']);
            if (! $timeSlot || $timeSlot->opportunity_id !== $opportunity->id) {
                return ApiResponse::error('Time slot does not belong to opportunity.', 'الفترة الزمنية لا تنتمي إلى الفرصة.', 400);
            }
        }

        $registration = DB::transaction(function () use ($user, $opportunity, $timeSlot) {
            $registration = LearnServeOpportunityRegistration::create([
                'opportunity_id' => $opportunity->id,
                'user_id' => $user->id,
                'registration_date' => now(),
                'status' => ApprovalStatus::APPROVED,
            ]);

            if ($timeSlot) {
                LearnServeOpportunityAssignment::create([
                    'registration_id' => $registration->id,
                    'time_slot_id' => $timeSlot->id,
                ]);
            }

            return $registration;
        });

        $registration->load(['user', 'assignment.timeSlot']);
        $remaining = max(0, $opportunity->participants_needed - LearnServeOpportunityRegistration::query()->notDeleted()->where('opportunity_id', $opportunity->id)->count());

        return ApiResponse::success(
            (new LearnServeOpportunityRegistrationResource($registration))->resolve(),
            'Successfully registered for the opportunity.',
            'تم التسجيل بنجاح في الفرصة.',
            201
        );
    }

    public function list(Request $request, int $opportunity_id): JsonResponse
    {
        $opportunity = LearnServeOpportunity::query()->notDeleted()->find($opportunity_id);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        $query = LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity_id)
            ->with(['user', 'assignment.timeSlot']);

        if ($search = $request->query('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $paginator = $this->paginateQuery($query, $request);

        return ApiResponse::paginated(
            $paginator,
            LearnServeOpportunityRegistrationResource::collection($paginator->getCollection()),
            'Registrations retrieved successfully.',
            'تم استرجاع التسجيلات بنجاح.'
        );
    }

    public function unregister(Request $request, int $opportunity_id): JsonResponse
    {
        $registration = LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $registration) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        LearnServeOpportunityAssignment::query()
            ->where('registration_id', $registration->id)
            ->get()
            ->each->softDeleteFlags();
        $registration->softDeleteFlags();

        return ApiResponse::success(null, 'Successfully unregistered from the opportunity.', 'تم إلغاء التسجيل من الفرصة بنجاح.');
    }

    public function unregisterUser(Request $request, int $opportunity_id, int $user_id): JsonResponse
    {
        $opportunity = LearnServeOpportunity::query()->notDeleted()->find($opportunity_id);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        $registration = LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity_id)
            ->where('user_id', $user_id)
            ->first();

        if (! $registration) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        LearnServeOpportunityAssignment::query()
            ->where('registration_id', $registration->id)
            ->get()
            ->each->softDeleteFlags();
        $registration->softDeleteFlags();

        return ApiResponse::success(['user_id' => $user_id], 'User successfully removed from the opportunity.', 'تمت إزالة المستخدم من الفرصة بنجاح.');
    }

    public function updateAttendance(Request $request, int $opportunity_id): JsonResponse
    {
        $data = $request->validate([
            'is_attended' => ['required', 'boolean'],
            'mark_all' => ['nullable', 'boolean'],
            'registration_ids' => ['nullable', 'array'],
            'registration_ids.*' => ['integer'],
        ]);

        $opportunity = LearnServeOpportunity::query()->notDeleted()->find($opportunity_id);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        $query = LearnServeOpportunityRegistration::query()->notDeleted()->where('opportunity_id', $opportunity_id);
        if (! ($data['mark_all'] ?? false)) {
            $ids = $data['registration_ids'] ?? [];
            if (empty($ids)) {
                return ApiResponse::error('registration_ids is required unless mark_all is true.', 'مطلوب registration_ids ما لم يكن mark_all صحيحًا.', 400);
            }
            $query->whereIn('id', $ids);
        }

        $userIds = $query->pluck('user_id')->all();
        $updatedCount = $query->update(['is_attended' => $data['is_attended']]);

        foreach ($userIds as $userId) {
            SyncService::syncUser($userId);
        }
        SyncService::syncUser($opportunity->created_by);

        return ApiResponse::success(
            ['updated_count' => $updatedCount],
            "Successfully updated attendance for {$updatedCount} registration(s).",
            "تم تحديث الحضور بنجاح لـ {$updatedCount} تسجيل(ات)."
        );
    }
}
