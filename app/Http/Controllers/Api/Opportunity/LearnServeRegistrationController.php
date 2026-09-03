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
use App\Services\Opportunity\RegistrationManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

        if ($closed = $this->rejectIfRegistrationClosed($opportunity)) {
            return $closed;
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
            ->whereIn('status', [ApprovalStatus::PENDING, ApprovalStatus::APPROVED])
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
                'status' => ApprovalStatus::PENDING,
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
        RegistrationManagementService::notifyStatus($registration->user, $opportunity, ApprovalStatus::PENDING);
        $remaining = max(0, $opportunity->participants_needed - LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity->id)
            ->whereIn('status', [ApprovalStatus::PENDING, ApprovalStatus::APPROVED])
            ->count());

        return ApiResponse::success(
            (new LearnServeOpportunityRegistrationResource($registration))->resolve(),
            'Registration submitted and is pending organizer approval.',
            'تم إرسال التسجيل وهو قيد موافقة الجهة المنظمة.',
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

        if ($status = $request->query('status')) {
            $query->where('status', $status);
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

        $registration->update(['status' => ApprovalStatus::REJECTED]);
        $registration->loadMissing('user');
        if ($registration->user) {
            RegistrationManagementService::notifyStatus($registration->user, $opportunity, ApprovalStatus::REJECTED);
        }

        return ApiResponse::success(['user_id' => $user_id, 'status' => ApprovalStatus::REJECTED->value], 'Registration rejected successfully.', 'تم رفض التسجيل بنجاح.');
    }

    public function bulkStatus(Request $request, int $opportunity_id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(ApprovalStatus::values())],
            'registration_ids' => ['required', 'array', 'min:1'],
            'registration_ids.*' => ['integer'],
        ]);

        $opportunity = LearnServeOpportunity::query()->notDeleted()->find($opportunity_id);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        $registrations = LearnServeOpportunityRegistration::query()->notDeleted()
            ->where('opportunity_id', $opportunity_id)
            ->whereIn('id', $data['registration_ids'])
            ->with('user')
            ->get();
        $status = ApprovalStatus::from($data['status']);

        foreach ($registrations as $registration) {
            $registration->update(['status' => $status]);
            if ($registration->user) {
                RegistrationManagementService::notifyStatus($registration->user, $opportunity, $status);
            }
        }

        return ApiResponse::success(
            ['updated_count' => $registrations->count(), 'status' => $status->value],
            'Registration statuses updated successfully.',
            'تم تحديث حالات التسجيل بنجاح.'
        );
    }

    public function messageRegistrants(Request $request, int $opportunity_id): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'all' => ['nullable', 'boolean'],
            'registration_ids' => ['nullable', 'array'],
            'registration_ids.*' => ['integer'],
            'status' => ['nullable', Rule::in(ApprovalStatus::values())],
        ]);

        $opportunity = LearnServeOpportunity::query()->notDeleted()->find($opportunity_id);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }
        if (! ($data['all'] ?? false) && empty($data['registration_ids'])) {
            return ApiResponse::error('Select registrations or set all to true.', 'اختر تسجيلات أو اجعل all تساوي true.', 400);
        }

        $query = LearnServeOpportunityRegistration::query()->notDeleted()
            ->where('opportunity_id', $opportunity_id)
            ->with('user');
        if (! ($data['all'] ?? false)) {
            $query->whereIn('id', $data['registration_ids']);
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $sent = 0;
        foreach ($query->get() as $registration) {
            if ($registration->user && RegistrationManagementService::sendOrganizerMessage($registration->user, $data['subject'], $data['message'])) {
                $sent++;
            }
        }

        return ApiResponse::success(['sent_count' => $sent], 'Message sent successfully.', 'تم إرسال الرسالة بنجاح.');
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

        if ($opportunity->preparationValidUntil() && now()->gt($opportunity->preparationValidUntil())) {
            return ApiResponse::error(
                'Attendance can only be updated during the opportunity or within one week after the end date.',
                'يمكن تحديث الحضور خلال الفرصة أو خلال أسبوع بعد تاريخ النهاية فقط.',
                400
            );
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
