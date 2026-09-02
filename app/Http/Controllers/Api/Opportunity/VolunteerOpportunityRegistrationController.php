<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Api\Opportunity\Concerns\HandlesOpportunities;
use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunity\VolunteerOpportunityRegistrationResource;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityAssignment;
use App\Models\VolunteerOpportunityAttendance;
use App\Models\VolunteerOpportunityRegistration;
use App\Models\VolunteerOpportunityRole;
use App\Models\VolunteerOpportunityTeam;
use App\Services\Mail\DynamicEmailService;
use App\Services\Notification\NotificationService;
use App\Services\Opportunity\RegistrationManagementService;
use App\Services\Opportunity\AttendanceService;
use App\Support\ApiResponse;
use App\Support\XlsxExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VolunteerOpportunityRegistrationController extends Controller
{
    use HandlesOpportunities;

    public function index(Request $request): JsonResponse
    {
        $query = VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->with(['user', 'assignment.role', 'assignment.team']);

        if ($opportunityId = $request->query('opportunity_id')) {
            $query->where('opportunity_id', $opportunityId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        foreach (['role_id', 'team_id'] as $param) {
            $ids = (array) $request->query($param, []);
            if ($ids) {
                $query->whereHas('assignment', fn ($q) => $q->whereIn("{$param}", $ids));
            }
        }

        if ($search = $request->query('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('download')) {
            return $this->downloadRegistrations($request, $query->distinct());
        }

        $paginator = $this->paginateQuery($query->distinct(), $request);

        return ApiResponse::paginated(
            $paginator,
            VolunteerOpportunityRegistrationResource::collection($paginator->getCollection()),
            'Registrations retrieved successfully.',
            'تم استرجاع التسجيلات بنجاح.'
        );
    }

    protected function downloadRegistrations(Request $request, Builder $query): JsonResponse
    {
        $data = validator($request->query(), [
            'opportunity_id' => ['required', 'integer', 'exists:volunteer_opportunities,id'],
            'mark_attendance' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false])],
            'date' => ['nullable', 'date'],
        ])->validate();

        $markAttendance = $request->boolean('mark_attendance');
        if ($markAttendance && empty($data['date'])) {
            return ApiResponse::error(
                'The date field is required when mark_attendance is true.',
                'حقل التاريخ مطلوب عند تفعيل تسجيل الحضور.',
                422
            );
        }

        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($data['opportunity_id']);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error(
                'Only the opportunity creator can download its registration sheet.',
                'يمكن لمنشئ الفرصة فقط تنزيل كشف المسجلين.',
                403
            );
        }

        $registrations = $query->get();
        $attendanceDate = ! empty($data['date'])
            ? \Carbon\Carbon::parse($data['date'])->toDateString()
            : null;
        $markedCount = 0;
        $alreadyMarkedCount = 0;

        if ($markAttendance && $attendanceDate) {
            if (! $opportunity->isWithinPreparationWindow($attendanceDate)) {
                return ApiResponse::error(
                    'The attendance date is outside the allowed check-in window.',
                    'تاريخ الحضور خارج نافذة تسجيل الحضور المسموح بها.',
                    400
                );
            }

            foreach ($registrations->where('status', ApprovalStatus::APPROVED) as $registration) {
                $alreadyMarked = VolunteerOpportunityAttendance::query()
                    ->notDeleted()
                    ->where('registration_id', $registration->id)
                    ->whereDate('attended_date', $attendanceDate)
                    ->where('is_attended', true)
                    ->exists();

                if ($alreadyMarked) {
                    $alreadyMarkedCount++;
                    continue;
                }

                AttendanceService::record(
                    $registration,
                    $opportunity,
                    $attendanceDate,
                    $this->computeAttendanceHours($opportunity),
                    AttendanceService::VIA_MANUAL,
                    $request->user()->id
                );
                $markedCount++;
            }
        }

        $rows = $registrations->map(function (VolunteerOpportunityRegistration $registration) use ($attendanceDate) {
            $user = $registration->user;
            $assignment = $registration->assignment;
            $attended = $attendanceDate
                ? VolunteerOpportunityAttendance::query()->notDeleted()
                    ->where('registration_id', $registration->id)
                    ->whereDate('attended_date', $attendanceDate)
                    ->where('is_attended', true)
                    ->exists()
                : false;

            return [
                $registration->id,
                trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')),
                $user?->email,
                trim(($user?->country_code ?? '').($user?->phone_number ?? '')),
                $registration->status?->value ?? (string) $registration->status,
                $assignment?->role?->role_name_en,
                $assignment?->team?->team_name_en,
                $user?->civil_id,
                $user?->passport_number,
                optional($registration->registration_date)?->toIso8601String(),
                $attendanceDate,
                $attendanceDate ? ($attended ? 'Yes' : 'No') : '',
            ];
        });

        $path = 'exports/volunteer-opportunity-'.$opportunity->id.'-registrations-'
            .now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.xlsx';
        $downloadUrl = XlsxExport::store($path, [
            'Registration ID', 'Full Name', 'Email', 'Phone', 'Status', 'Role', 'Team',
            'Civil ID', 'Passport Number', 'Registration Date', 'Attendance Date', 'Attended',
        ], $rows, 'Registered Volunteers');

        return ApiResponse::success([
            'downloadUrl' => $downloadUrl,
            'download_url' => $downloadUrl,
            'file_format' => 'xlsx',
            'registrations_count' => $registrations->count(),
            'attendance_marked_count' => $markedCount,
            'attendance_already_marked_count' => $alreadyMarkedCount,
        ], 'Registration sheet generated successfully.', 'تم إنشاء كشف المسجلين بنجاح.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunity_id' => ['required', 'integer', 'exists:volunteer_opportunities,id'],
            'role_id' => ['nullable', 'integer', 'exists:volunteer_opportunity_roles,id'],
            'team_id' => ['nullable', 'integer', 'exists:volunteer_opportunity_teams,id'],
            'organization_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($data['opportunity_id']);
        if (! $opportunity) {
            return ApiResponse::error('Opportunity does not exist.', 'الفرصة غير موجودة.', 404);
        }

        if ($closed = $this->rejectIfRegistrationClosed($opportunity)) {
            return $closed;
        }

        if (VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity->id)
            ->where('user_id', $user->id)
            ->exists()) {
            return ApiResponse::error(
                'You are already registered for this opportunity.',
                'أنت مسجل بالفعل في هذه الفرصة.',
                400
            );
        }

        // effectiveBirthYear() falls back to `dob`, so an account that has a
        // birth date but no birth_year is no longer rejected.
        $userAge = $this->calculateAge($user->effectiveBirthYear());
        if ($userAge === null) {
            return ApiResponse::error(
                'Please provide your birth year to check age eligibility.',
                'يرجى تقديم سنة ميلادك للتحقق من أهلية العمر.',
                400
            );
        }

        $fromAge = $opportunity->from_age ?? 7;
        $toAge = $opportunity->to_age;
        if ($toAge === null) {
            if ($userAge < $fromAge) {
                return ApiResponse::error(
                    'Sorry, you cannot register. This opportunity is restricted to a different age group.',
                    'عذرًا، لا يمكنك التسجيل. هذه الفرصة مخصصة لفئة عمرية مختلفة.',
                    400
                );
            }
        } elseif ($userAge < $fromAge || $userAge > $toAge) {
            return ApiResponse::error(
                'Sorry, you cannot register. This opportunity is restricted to a different age group.',
                'عذرًا، لا يمكنك التسجيل. هذه الفرصة مخصصة لفئة عمرية مختلفة.',
                400
            );
        }

        $role = null;
        $team = null;
        if (! empty($data['role_id'])) {
            $role = VolunteerOpportunityRole::query()->notDeleted()->find($data['role_id']);
            if (! $role || $role->opportunity_id !== $opportunity->id) {
                return ApiResponse::error('Role does not belong to opportunity.', 'الدور لا ينتمي إلى الفرصة.', 400);
            }
            $assigned = VolunteerOpportunityAssignment::query()
                ->notDeleted()
                ->where('role_id', $role->id)
                ->whereHas('registration', fn ($q) => $q->notDeleted()
                    ->where('opportunity_id', $opportunity->id)
                    ->whereIn('status', [ApprovalStatus::PENDING, ApprovalStatus::APPROVED]))
                ->count();
            if ($assigned >= $role->participants_needed) {
                return ApiResponse::error('The role has no remaining slots available.', 'الدور ليس لديه أي فتحات متبقية متاحة.', 400);
            }
        }

        if (! empty($data['team_id'])) {
            $team = VolunteerOpportunityTeam::query()->notDeleted()->find($data['team_id']);
            if (! $team || $team->opportunity_id !== $opportunity->id) {
                return ApiResponse::error('Team does not belong to opportunity.', 'الفريق لا ينتمي إلى الفرصة.', 400);
            }
        }

        $registration = DB::transaction(function () use ($user, $opportunity, $role, $team) {
            $registration = VolunteerOpportunityRegistration::create([
                'opportunity_id' => $opportunity->id,
                'user_id' => $user->id,
                'registration_date' => now(),
                'status' => ApprovalStatus::PENDING,
            ]);

            if ($role || $team) {
                VolunteerOpportunityAssignment::create([
                    'registration_id' => $registration->id,
                    'role_id' => $role?->id,
                    'team_id' => $team?->id,
                ]);
            }

            return $registration;
        });

        $registration->load(['user', 'assignment.role', 'assignment.team']);
        $totalAssigned = VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity->id)
            ->whereIn('status', [ApprovalStatus::PENDING, ApprovalStatus::APPROVED])
            ->count();

        RegistrationManagementService::notifyStatus($registration->user, $opportunity, ApprovalStatus::PENDING);

        return ApiResponse::success([
            'registration' => (new VolunteerOpportunityRegistrationResource($registration))->resolve(),
            'assignment_id' => $registration->assignment?->id,
            'remaining_slots' => max(0, $opportunity->participants_needed - $totalAssigned),
            'user_age' => $userAge,
            'required_age_from' => $fromAge,
            'required_age_to' => $toAge,
            'meets_age_requirement' => true,
        ], 'Registration submitted and is pending organizer approval.', 'تم إرسال التسجيل وهو قيد موافقة الجهة المنظمة.', 201);
    }

    /**
     * Emails the volunteer their registration details and raises a notification.
     *
     * Failures are logged, never surfaced: a mail outage must not roll back a
     * registration the volunteer already completed.
     */
    protected function sendRegistrationConfirmation(
        VolunteerOpportunity $opportunity,
        VolunteerOpportunityRegistration $registration
    ): void {
        $user = $registration->user;
        if (! $user) {
            return;
        }

        $titleEn = $opportunity->title_en ?? 'Opportunity';
        $titleAr = $opportunity->title_ar ?? $titleEn;
        $startDate = optional($opportunity->start_date)->toDateString() ?? '-';
        $endDate = optional($opportunity->end_date)->toDateString() ?? '-';
        $location = $opportunity->location_ar ?: ($opportunity->location_en ?: '-');

        try {
            NotificationService::createForUsers(
                "Registration confirmed: {$titleEn}",
                "تم تأكيد تسجيلك: {$titleAr}",
                "You are registered for '{$titleEn}' starting {$startDate}.",
                "تم تسجيلك في '{$titleAr}' التي تبدأ في {$startDate}.",
                [$user->id]
            );
        } catch (\Throwable $e) {
            Log::warning('Registration notification failed', [
                'opportunity_id' => $opportunity->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            DynamicEmailService::send('volunteer_registration_confirmation', $user, [
                'opportunity_title_en' => $titleEn,
                'opportunity_title_ar' => $titleAr,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => (string) ($opportunity->start_time ?? '-'),
                'end_time' => (string) ($opportunity->end_time ?? '-'),
                'location' => $location,
                'location_url' => (string) ($opportunity->location_url ?: $opportunity->link ?: ''),
                'role' => (string) ($registration->assignment?->role?->name_en ?? '-'),
                'team' => (string) ($registration->assignment?->team?->name_en ?? '-'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Registration confirmation email failed', [
                'opportunity_id' => $opportunity->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updateAssignment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'registration' => ['required', 'integer', 'exists:volunteer_opportunity_registrations,id'],
            'role' => ['nullable', 'integer', 'exists:volunteer_opportunity_roles,id'],
            'team' => ['nullable', 'integer', 'exists:volunteer_opportunity_teams,id'],
        ]);

        $registration = VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->with('opportunity')
            ->find($data['registration']);

        if (! $registration || $registration->opportunity?->is_deleted) {
            return ApiResponse::error('Registration does not exist or has been deleted.', 'التسجيل غير موجود أو تم حذفه.', 404);
        }

        if ($registration->opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error(
                'You can only update assignments for opportunities you created.',
                'يمكنك فقط تحديث التعيينات للفرص التي أنشأتها.',
                403
            );
        }

        $assignment = VolunteerOpportunityAssignment::query()
            ->notDeleted()
            ->firstOrCreate(['registration_id' => $registration->id]);

        if (! empty($data['role'])) {
            $role = VolunteerOpportunityRole::query()->notDeleted()->find($data['role']);
            if (! $role || $role->opportunity_id !== $registration->opportunity_id) {
                return ApiResponse::error('Role does not belong to opportunity.', 'الدور لا ينتمي إلى الفرصة.', 400);
            }
            $assignment->role_id = $role->id;
        }

        if (! empty($data['team'])) {
            $team = VolunteerOpportunityTeam::query()->notDeleted()->find($data['team']);
            if (! $team || $team->opportunity_id !== $registration->opportunity_id) {
                return ApiResponse::error('Team does not belong to opportunity.', 'الفريق لا ينتمي إلى الفرصة.', 400);
            }
            $assignment->team_id = $team->id;
        }

        $assignment->save();
        $registration->load(['user', 'assignment.role', 'assignment.team']);

        return ApiResponse::success(
            new VolunteerOpportunityRegistrationResource($registration),
            'Assignment updated successfully.',
            'تم تحديث التعيين بنجاح.'
        );
    }

    public function directRegister(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunity_id' => ['required', 'integer', 'exists:volunteer_opportunities,id'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($data['opportunity_id']);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error(
                'You do not have permission to directly register volunteers for this opportunity.',
                'ليس لديك إذن لتسجيل المتطوعين مباشرة لهذه الفرصة.',
                403
            );
        }

        $successful = [];
        $failed = [];

        foreach ($data['user_ids'] as $userId) {
            $volunteerUser = User::query()->notDeleted()->find($userId);
            if (! $volunteerUser) {
                $failed[] = ['user_id' => $userId, 'error' => 'User does not exist.'];
                continue;
            }

            if (VolunteerOpportunityRegistration::query()
                ->notDeleted()
                ->where('opportunity_id', $opportunity->id)
                ->where('user_id', $userId)
                ->exists()) {
                $failed[] = ['user_id' => $userId, 'error' => 'User is already registered.'];
                continue;
            }

            $registration = VolunteerOpportunityRegistration::create([
                'opportunity_id' => $opportunity->id,
                'user_id' => $userId,
                'registration_date' => now(),
                'status' => ApprovalStatus::APPROVED,
            ]);
            $assignment = VolunteerOpportunityAssignment::create(['registration_id' => $registration->id]);
            $registration->load(['user', 'assignment']);

            $successful[] = [
                'user_id' => $userId,
                'user_name' => trim(($volunteerUser->first_name ?? '').' '.($volunteerUser->last_name ?? '')),
                'registration' => (new VolunteerOpportunityRegistrationResource($registration))->resolve(),
                'assignment_id' => $assignment->id,
            ];
        }

        $totalAssigned = VolunteerOpportunityRegistration::query()->notDeleted()
            ->where('opportunity_id', $opportunity->id)
            ->whereIn('status', [ApprovalStatus::PENDING, ApprovalStatus::APPROVED])
            ->count();

        return ApiResponse::success([
            'successful_registrations' => $successful,
            'failed_registrations' => $failed,
            'success_count' => count($successful),
            'failed_count' => count($failed),
            'remaining_slots' => max(0, $opportunity->participants_needed - $totalAssigned),
        ], 'Direct registration processed.', 'تمت معالجة التسجيل المباشر.', count($successful) > 0 ? 201 : 400);
    }

    public function directUnregister(Request $request): JsonResponse
    {
        $userIds = $request->input('user_ids', []);
        if ($request->filled('user_id') && empty($userIds)) {
            $userIds = [$request->input('user_id')];
        }

        $data = $request->validate([
            'opportunity_id' => ['required', 'integer', 'exists:volunteer_opportunities,id'],
        ]);

        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($data['opportunity_id']);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        if (empty($userIds)) {
            return ApiResponse::error('At least one user ID is required.', 'مطلوب معرف مستخدم واحد على الأقل.', 400);
        }

        $removed = 0;
        foreach ($userIds as $userId) {
            $registration = VolunteerOpportunityRegistration::query()
                ->notDeleted()
                ->where('opportunity_id', $opportunity->id)
                ->where('user_id', $userId)
                ->first();

            if ($registration) {
                $registration->update(['status' => ApprovalStatus::REJECTED]);
                $registration->loadMissing('user');
                if ($registration->user) {
                    RegistrationManagementService::notifyStatus($registration->user, $opportunity, ApprovalStatus::REJECTED);
                }
                $removed++;
            }
        }

        return ApiResponse::success(
            ['rejected_count' => $removed, 'removed_count' => $removed],
            'Registrations rejected successfully.',
            'تم رفض التسجيلات بنجاح.'
        );
    }

    public function unregister(Request $request, int $opportunity_id): JsonResponse
    {
        $registration = VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $registration) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        VolunteerOpportunityAssignment::query()
            ->where('registration_id', $registration->id)
            ->get()
            ->each->softDeleteFlags();
        $registration->softDeleteFlags();

        return ApiResponse::success(null, 'Successfully unregistered from the opportunity.', 'تم إلغاء التسجيل من الفرصة بنجاح.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $registration = VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->with(['user.volunteerProfile', 'assignment.role', 'assignment.team'])
            ->find($id);

        if (! $registration) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        return ApiResponse::success(
            new VolunteerOpportunityRegistrationResource($registration),
            'Registration retrieved successfully.',
            'تم استرداد التسجيل بنجاح.'
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $registration = VolunteerOpportunityRegistration::query()->notDeleted()->with(['opportunity', 'user'])->find($id);
        if (! $registration) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        if ($registration->opportunity?->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(ApprovalStatus::values())],
        ]);

        $registration->update($data);
        RegistrationManagementService::notifyStatus(
            $registration->user,
            $registration->opportunity,
            ApprovalStatus::from($data['status'])
        );

        return ApiResponse::success(
            new VolunteerOpportunityRegistrationResource($registration->fresh(['user.volunteerProfile', 'assignment.role', 'assignment.team'])),
            'Registration updated successfully.',
            'تم تحديث التسجيل بنجاح.'
        );
    }

    public function bulkStatus(Request $request, int $opportunity_id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(ApprovalStatus::values())],
            'registration_ids' => ['required', 'array', 'min:1'],
            'registration_ids.*' => ['integer'],
        ]);

        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($opportunity_id);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        $registrations = VolunteerOpportunityRegistration::query()->notDeleted()
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

        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($opportunity_id);
        if (! $opportunity || $opportunity->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        if (! ($data['all'] ?? false) && empty($data['registration_ids'])) {
            return ApiResponse::error('Select registrations or set all to true.', 'اختر تسجيلات أو اجعل all تساوي true.', 400);
        }

        $query = VolunteerOpportunityRegistration::query()->notDeleted()
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

    public function destroy(Request $request, int $id): JsonResponse
    {
        $registration = VolunteerOpportunityRegistration::query()->notDeleted()->with(['opportunity', 'user'])->find($id);
        if (! $registration) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        if ($registration->opportunity?->created_by !== $request->user()->id) {
            return ApiResponse::error('Permission denied.', 'تم رفض الإذن.', 403);
        }

        $registration->update(['status' => ApprovalStatus::REJECTED]);
        if ($registration->user) {
            RegistrationManagementService::notifyStatus($registration->user, $registration->opportunity, ApprovalStatus::REJECTED);
        }

        return ApiResponse::success(['status' => ApprovalStatus::REJECTED->value], 'Registration rejected successfully.', 'تم رفض التسجيل بنجاح.');
    }
}
