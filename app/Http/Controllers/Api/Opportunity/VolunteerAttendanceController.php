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

        if (empty($data['attendance_date'])) {
            if ($opportunity->start_date && $opportunity->end_date) {
                if ($attendanceDate < $opportunity->start_date->toDateString() || $attendanceDate > $opportunity->end_date->toDateString()) {
                    return ApiResponse::error(
                        'You cannot record attendance before or after the opportunity dates.',
                        'لا يمكنك تسجيل الحضور قبل أو بعد تواريخ الفرصة.',
                        400
                    );
                }
            } elseif ($opportunity->start_date && $attendanceDate !== $opportunity->start_date->toDateString()) {
                return ApiResponse::error(
                    'Attendance can only be recorded on the scheduled date.',
                    'يمكن تسجيل الحضور فقط في التاريخ المحدد.',
                    400
                );
            }
        }

        $hasPermission = $opportunity->created_by === $request->user()->id
            || ScanPermission::query()
                ->notDeleted()
                ->where('opportunity_id', $opportunity->id)
                ->where('user_id', $request->user()->id)
                ->where('is_allowed', true)
                ->exists();

        if (! $hasPermission) {
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

            DB::transaction(function () use ($registration, $opportunity, $attendanceDate, $volunteer) {
                $hours = $this->computeAttendanceHours($opportunity);
                VolunteerOpportunityAttendance::create([
                    'registration_id' => $registration->id,
                    'attended_date' => $attendanceDate,
                    'total_hours' => $hours,
                    'is_attended' => true,
                ]);

                $volunteer->increment('total_volunteer_hours', $hours);

                $stat = VolunteerStatistic::query()->firstOrCreate(
                    [
                        'user_id' => $volunteer->user_id,
                        'year' => (int) date('Y', strtotime($attendanceDate)),
                        'month' => (int) date('n', strtotime($attendanceDate)),
                    ],
                    [
                        'volunteer_hours' => 0,
                        'opportunities_participated' => 0,
                        'opportunities_organized' => 0,
                        'certificates_earned' => 0,
                    ]
                );
                $stat->increment('volunteer_hours', $hours);
            });

            SyncService::syncUser($volunteer->user_id);
            SyncService::syncUser($opportunity->created_by);

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

    protected function validateUuid(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $value = trim($raw, " \t\n\r\0\x0B\"'");

        return Str::isUuid($value) ? $value : null;
    }
}
