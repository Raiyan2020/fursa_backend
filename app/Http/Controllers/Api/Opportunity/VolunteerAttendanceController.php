<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Http\Controllers\Api\Opportunity\Concerns\HandlesOpportunities;
use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunity\VolunteerAttendanceResource;
use App\Models\ScanPermission;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityAttendance;
use App\Models\VolunteerOpportunityRegistration;
use App\Models\VolunteerProfile;
use App\Models\VolunteerStatistic;
use App\Services\Opportunity\AttendanceService;
use App\Services\Opportunity\SyncService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VolunteerAttendanceController extends Controller
{
    use HandlesOpportunities;

    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunity_id' => ['nullable', 'integer', 'exists:volunteer_opportunities,id'],
            'event_id' => ['nullable', 'integer'],
            'volunteer_uuid' => ['nullable', 'string'],
            'volunteer_ids' => ['nullable', 'array'],
            'volunteer_ids.*' => ['string'],
            'attendance_date' => ['nullable', 'date'],
        ]);

        if (empty($data['opportunity_id']) && empty($data['event_id'])) {
            return ApiResponse::error("Either 'opportunity_id' or 'event_id' must be provided.", "يجب توفير إما 'opportunity_id' أو 'event_id'.", 400);
        }

        if (! empty($data['opportunity_id']) && ! empty($data['event_id'])) {
            return ApiResponse::error("Only one of 'opportunity_id' or 'event_id' must be provided.", "يجب توفير واحد فقط من 'opportunity_id' أو 'event_id'.", 400);
        }

        if (empty($data['volunteer_uuid']) && empty($data['volunteer_ids'])) {
            return ApiResponse::error("Either 'volunteer_uuid' or 'volunteer_ids' must be provided.", "يجب توفير 'volunteer_uuid' أو 'volunteer_ids'.", 400);
        }

        if (! empty($data['event_id'])) {
            return ApiResponse::error('Event attendance is not implemented yet.', 'حضور الحدث غير مطبق بعد.', 501);
        }

        $attendanceDate = isset($data['attendance_date'])
            ? \Carbon\Carbon::parse($data['attendance_date'])->toDateString()
            : now()->toDateString();

        $uuids = $data['volunteer_uuid'] ? [$data['volunteer_uuid']] : $data['volunteer_ids'];
        $isSingle = count($uuids) === 1;

        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($data['opportunity_id']);
        if (! $opportunity) {
            return ApiResponse::error('Opportunity does not exist.', 'الفرصة غير موجودة.', 404);
        }

        if (! $opportunity->isWithinPreparationWindow($attendanceDate)) {
            return ApiResponse::error(
                'You cannot record attendance outside the opportunity dates or more than one week after the end date.',
                'لا يمكنك تسجيل الحضور خارج تواريخ الفرصة أو بعد أكثر من أسبوع من تاريخ النهاية.',
                400
            );
        }

        if (! $this->canManageAttendance($opportunity, $request->user()->id)) {
            return ApiResponse::error(
                "You don't have permission to manage attendance for this opportunity.",
                'ليس لديك إذن لإدارة الحضور لهذه الفرصة.',
                403
            );
        }

        $responses = [];
        foreach ($uuids as $rawUuid) {
            $validUuid = $this->validateUuid($rawUuid);
            if (! $validUuid) {
                if ($isSingle) {
                    return ApiResponse::error('Scanned QR code is not valid.', 'رمز QR الممسوح غير صالح.', 400);
                }
                $responses[] = ['uuid' => $rawUuid, 'status' => 'invalid'];
                continue;
            }

            $volunteer = VolunteerProfile::query()->where('uuid', $validUuid)->first();
            if (! $volunteer) {
                if ($isSingle) {
                    return ApiResponse::error('Volunteer does not exist in the platform.', 'المتطوع غير موجود في المنصة.', 404);
                }
                $responses[] = ['uuid' => $validUuid, 'status' => 'not_found'];
                continue;
            }

            $registration = VolunteerOpportunityRegistration::query()
                ->notDeleted()
                ->where('opportunity_id', $opportunity->id)
                ->where('user_id', $volunteer->user_id)
                ->first();

            if (! $registration) {
                if ($isSingle) {
                    return ApiResponse::error('This user is not registered for this opportunity.', 'هذا المستخدم غير مسجل لهذه الفرصة.', 400);
                }
                $responses[] = ['uuid' => $validUuid, 'status' => 'not_registered'];
                continue;
            }

            if (VolunteerOpportunityAttendance::query()
                ->notDeleted()
                ->where('registration_id', $registration->id)
                ->whereDate('attended_date', $attendanceDate)
                ->where('is_attended', true)
                ->exists()) {
                if ($isSingle) {
                    return ApiResponse::error('Attendance already recorded for today.', 'تم تسجيل الحضور بالفعل لهذا اليوم.', 409);
                }
                $responses[] = ['uuid' => $validUuid, 'status' => 'already_attended'];
                continue;
            }

            AttendanceService::record(
                $registration,
                $opportunity,
                $attendanceDate,
                $this->computeAttendanceHours($opportunity, $attendanceDate),
                AttendanceService::VIA_QR,
                $request->user()->id
            );

            if ($isSingle) {
                return ApiResponse::success(null, 'Attendance recorded successfully.', 'تم تسجيل الحضور بنجاح.');
            }

            $responses[] = ['uuid' => $validUuid, 'status' => 'success'];
        }

        if (collect($responses)->contains(fn ($r) => $r['status'] === 'success')) {
            return ApiResponse::success(
                ['results' => $responses],
                'Attendance recorded successfully for one or more volunteers.',
                'تم تسجيل الحضور بنجاح لمتطوع واحد أو أكثر.'
            );
        }

        return ApiResponse::error(
            'No attendance recorded. Please check scanned codes.',
            'لم يتم تسجيل أي حضور. يرجى التحقق من الرموز الممسوحة.',
            400,
            null,
            ['results' => $responses]
        );
    }

    public function history(Request $request): JsonResponse
    {
        $query = VolunteerOpportunityAttendance::query()
            ->notDeleted()
            ->with(['registration.user', 'registration.opportunity']);

        if ($opportunityId = $request->query('opportunity_id')) {
            $query->whereHas('registration', fn ($q) => $q->where('opportunity_id', $opportunityId));
        }

        if ($volunteerUuid = $request->query('volunteer_uuid')) {
            $query->whereHas('registration.user.volunteerProfile', fn ($q) => $q->where('uuid', $volunteerUuid));
        }

        if ($registrationId = $request->query('registration_id')) {
            $query->where('registration_id', $registrationId);
        }

        if ($startDate = $request->query('start_date')) {
            $query->whereDate('attended_date', '>=', $startDate);
        }
        if ($endDate = $request->query('end_date')) {
            $query->whereDate('attended_date', '<=', $endDate);
        }

        if (! $request->user()->isAdmin() && ! $request->user()->is_staff) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('registration.opportunity', fn ($oq) => $oq->where('created_by', $request->user()->id))
                    ->orWhereHas('registration', fn ($rq) => $rq->where('user_id', $request->user()->id));
            });
        }

        $query->orderByDesc('attended_date');
        $totalHours = round((float) $query->sum('total_hours'), 2);

        $paginator = $this->paginateQuery($query, $request);
        $payload = ApiResponse::paginated(
            $paginator,
            VolunteerAttendanceResource::collection($paginator->getCollection()),
            'Attendance records retrieved successfully.',
            'تم استرداد سجلات الحضور بنجاح.'
        )->getData(true);

        $payload['total_hours'] = $totalHours;

        return response()->json($payload, 200);
    }

    /**
     * Manual check-in, usable alongside QR for the same opportunity.
     *
     * The publisher picks whichever path suits the volunteer in front of them;
     * hours may be overridden here because manual check-ins often cover only
     * part of a shift.
     */
    public function manual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunity_id' => ['required', 'integer', 'exists:volunteer_opportunities,id'],
            'registration_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'volunteer_uuid' => ['nullable', 'string'],
            'attendance_date' => ['nullable', 'date'],
            'total_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
        ]);

        if (empty($data['registration_id']) && empty($data['user_id']) && empty($data['volunteer_uuid'])) {
            return ApiResponse::error(
                "One of 'registration_id', 'user_id' or 'volunteer_uuid' must be provided.",
                "يجب توفير 'registration_id' أو 'user_id' أو 'volunteer_uuid'.",
                400
            );
        }

        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($data['opportunity_id']);
        if (! $opportunity) {
            return ApiResponse::error('Opportunity does not exist.', 'الفرصة غير موجودة.', 404);
        }

        if (! $this->canManageAttendance($opportunity, $request->user()->id)) {
            return ApiResponse::error(
                "You don't have permission to manage attendance for this opportunity.",
                'ليس لديك إذن لإدارة الحضور لهذه الفرصة.',
                403
            );
        }

        $attendanceDate = isset($data['attendance_date'])
            ? \Carbon\Carbon::parse($data['attendance_date'])->toDateString()
            : now()->toDateString();

        if (! $opportunity->isWithinPreparationWindow($attendanceDate)) {
            return ApiResponse::error(
                'The check-in window for this opportunity is closed.',
                'نافذة التحضير لهذه الفرصة مغلقة.',
                400
            );
        }

        $registration = $this->resolveRegistration($opportunity, $data);
        if (! $registration) {
            return ApiResponse::error(
                'This user is not registered for this opportunity.',
                'هذا المستخدم غير مسجل لهذه الفرصة.',
                400
            );
        }

        $alreadyAttended = VolunteerOpportunityAttendance::query()
            ->notDeleted()
            ->where('registration_id', $registration->id)
            ->whereDate('attended_date', $attendanceDate)
            ->where('is_attended', true)
            ->exists();

        if ($alreadyAttended) {
            return ApiResponse::error(
                'Attendance already recorded for this date.',
                'تم تسجيل الحضور بالفعل لهذا التاريخ.',
                409
            );
        }

        $hours = array_key_exists('total_hours', $data) && $data['total_hours'] !== null
            ? round((float) $data['total_hours'], 2)
            : $this->computeAttendanceHours($opportunity, $attendanceDate);

        $attendance = AttendanceService::record(
            $registration,
            $opportunity,
            $attendanceDate,
            $hours,
            AttendanceService::VIA_MANUAL,
            $request->user()->id
        );

        return ApiResponse::success(
            new VolunteerAttendanceResource($attendance->load(['registration.user', 'registration.opportunity'])),
            'Attendance recorded successfully.',
            'تم تسجيل الحضور بنجاح.'
        );
    }

    /**
     * Correct the hours on a check-in that already happened, QR or manual.
     */
    public function updateHours(Request $request, int $attendance_id): JsonResponse
    {
        $data = $request->validate([
            'total_hours' => ['required', 'numeric', 'min:0', 'max:24'],
        ]);

        $attendance = VolunteerOpportunityAttendance::query()
            ->notDeleted()
            ->with(['registration.opportunity'])
            ->find($attendance_id);

        if (! $attendance) {
            return ApiResponse::error('Attendance record not found.', 'لم يتم العثور على سجل الحضور.', 404);
        }

        $opportunity = $attendance->registration?->opportunity;
        if (! $opportunity || ! $this->canManageAttendance($opportunity, $request->user()->id)) {
            return ApiResponse::error(
                "You don't have permission to manage attendance for this opportunity.",
                'ليس لديك إذن لإدارة الحضور لهذه الفرصة.',
                403
            );
        }

        $attendance = AttendanceService::updateHours($attendance, round((float) $data['total_hours'], 2));

        return ApiResponse::success(
            new VolunteerAttendanceResource($attendance->load(['registration.user', 'registration.opportunity'])),
            'Attendance hours updated successfully.',
            'تم تحديث ساعات الحضور بنجاح.'
        );
    }

    /**
     * Undo a check-in and give back the hours it had granted.
     */
    public function undo(Request $request, int $attendance_id): JsonResponse
    {
        $attendance = VolunteerOpportunityAttendance::query()
            ->notDeleted()
            ->with(['registration.opportunity'])
            ->find($attendance_id);

        if (! $attendance) {
            return ApiResponse::error('Attendance record not found.', 'لم يتم العثور على سجل الحضور.', 404);
        }

        $opportunity = $attendance->registration?->opportunity;
        if (! $opportunity || ! $this->canManageAttendance($opportunity, $request->user()->id)) {
            return ApiResponse::error(
                "You don't have permission to manage attendance for this opportunity.",
                'ليس لديك إذن لإدارة الحضور لهذه الفرصة.',
                403
            );
        }

        AttendanceService::undo($attendance);

        return ApiResponse::success(
            null,
            'Attendance undone successfully.',
            'تم التراجع عن الحضور بنجاح.'
        );
    }

    /**
     * Reopen a check-in window that already closed.
     *
     * Only an admin can do this; publishers who miss the window have to ask.
     */
    public function reopenWindow(Request $request, int $opportunity_id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return ApiResponse::error('Admin access required.', 'مطلوب وصول المسؤول.', 403);
        }

        $data = $request->validate([
            'reopen_until' => ['nullable', 'date'],
            'extra_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);

        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($opportunity_id);
        if (! $opportunity) {
            return ApiResponse::error('Opportunity does not exist.', 'الفرصة غير موجودة.', 404);
        }

        if (! empty($data['reopen_until'])) {
            $until = \Carbon\Carbon::parse($data['reopen_until']);
        } else {
            // Extend from whichever boundary is later so a second reopen still moves forward.
            $base = $opportunity->preparationValidUntil() ?? now();
            if ($base->lt(now())) {
                $base = now();
            }
            $until = $base->copy()->addHours((int) ($data['extra_hours'] ?? 24));
        }

        if ($until->lte(now())) {
            return ApiResponse::error(
                'The reopen date must be in the future.',
                'يجب أن يكون تاريخ إعادة الفتح في المستقبل.',
                422
            );
        }

        $opportunity->update(['preparation_reopened_until' => $until]);

        return ApiResponse::success(
            [
                'id' => $opportunity->id,
                'preparation_reopened_until' => $until->toIso8601String(),
                'preparation_valid_until' => $opportunity->fresh()->preparationValidUntil()?->toIso8601String(),
                'is_preparation_window_closed' => $opportunity->fresh()->isPreparationWindowClosed(),
            ],
            'Check-in window reopened successfully.',
            'تم إعادة فتح نافذة التحضير بنجاح.'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveRegistration(
        VolunteerOpportunity $opportunity,
        array $data
    ): ?VolunteerOpportunityRegistration {
        $query = VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity->id);

        if (! empty($data['registration_id'])) {
            return $query->where('id', $data['registration_id'])->first();
        }

        if (! empty($data['user_id'])) {
            return $query->where('user_id', $data['user_id'])->first();
        }

        $uuid = $this->validateUuid($data['volunteer_uuid'] ?? null);
        if (! $uuid) {
            return null;
        }

        $profile = VolunteerProfile::query()->where('uuid', $uuid)->first();

        return $profile ? $query->where('user_id', $profile->user_id)->first() : null;
    }

    protected function canManageAttendance(VolunteerOpportunity $opportunity, int $userId): bool
    {
        if ($opportunity->created_by === $userId) {
            return true;
        }

        return ScanPermission::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity->id)
            ->where('user_id', $userId)
            ->where('is_allowed', true)
            ->exists();
    }

    protected function validateUuid(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $value = trim($raw, " \t\n\r\0\x0B\"'");

        return Str::isUuid($value) ? $value : null;
    }
}
