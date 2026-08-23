<?php

namespace App\Services\Opportunity;

use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityAttendance;
use App\Models\VolunteerOpportunityRegistration;
use App\Models\VolunteerProfile;
use App\Models\VolunteerStatistic;
use Illuminate\Support\Facades\DB;

/**
 * Single owner of volunteer-hour bookkeeping for attendance.
 *
 * Recording, editing the hours and undoing a check-in all have to keep the
 * volunteer profile total and the monthly statistic row in step, so they share
 * one adjustment path instead of each doing their own increments.
 */
class AttendanceService
{
    public const VIA_QR = 'qr';

    public const VIA_MANUAL = 'manual';

    /**
     * Record a check-in. $hours defaults to the opportunity's computed length.
     */
    public static function record(
        VolunteerOpportunityRegistration $registration,
        VolunteerOpportunity $opportunity,
        string $attendanceDate,
        float $hours,
        string $via,
        ?int $recordedBy = null
    ): VolunteerOpportunityAttendance {
        $attendance = DB::transaction(function () use ($registration, $attendanceDate, $hours, $via, $recordedBy) {
            // One row per registration per day is enforced by a unique index, so
            // an undone check-in is revived rather than inserted again — that is
            // what makes "undo, then record the right hours" work.
            $attendance = VolunteerOpportunityAttendance::query()
                ->where('registration_id', $registration->id)
                ->whereDate('attended_date', $attendanceDate)
                ->first();

            $attributes = [
                'total_hours' => $hours,
                'is_attended' => true,
                'recorded_via' => $via,
                'recorded_by' => $recordedBy,
                'is_deleted' => false,
                'deleted_at' => null,
            ];

            if ($attendance) {
                $attendance->update($attributes);
            } else {
                $attendance = VolunteerOpportunityAttendance::create($attributes + [
                    'registration_id' => $registration->id,
                    'attended_date' => $attendanceDate,
                ]);
            }

            self::adjustHours($registration->user_id, $attendanceDate, $hours);

            return $attendance;
        });

        SyncService::syncUser($registration->user_id);
        SyncService::syncUser($opportunity->created_by);

        return $attendance;
    }

    /**
     * Change the hours on an existing check-in, applying only the delta.
     */
    public static function updateHours(VolunteerOpportunityAttendance $attendance, float $hours): VolunteerOpportunityAttendance
    {
        $registration = $attendance->registration;
        $delta = round($hours - (float) $attendance->total_hours, 2);

        DB::transaction(function () use ($attendance, $hours, $delta, $registration) {
            $attendance->update(['total_hours' => $hours]);

            if ($delta !== 0.0 && $registration) {
                self::adjustHours(
                    $registration->user_id,
                    $attendance->attended_date->toDateString(),
                    $delta
                );
            }
        });

        if ($registration) {
            SyncService::syncUser($registration->user_id);
            $createdBy = $registration->opportunity?->created_by;
            if ($createdBy) {
                SyncService::syncUser($createdBy);
            }
        }

        return $attendance->refresh();
    }

    /**
     * Undo a check-in: soft-delete it and give back the hours it granted.
     */
    public static function undo(VolunteerOpportunityAttendance $attendance): void
    {
        $registration = $attendance->registration;
        $hours = (float) $attendance->total_hours;

        DB::transaction(function () use ($attendance, $registration, $hours) {
            $attendance->update([
                'is_attended' => false,
                'is_deleted' => true,
                'deleted_at' => now(),
            ]);

            if ($registration) {
                self::adjustHours(
                    $registration->user_id,
                    $attendance->attended_date->toDateString(),
                    -$hours
                );
            }
        });

        if ($registration) {
            SyncService::syncUser($registration->user_id);
            $createdBy = $registration->opportunity?->created_by;
            if ($createdBy) {
                SyncService::syncUser($createdBy);
            }
        }
    }

    /**
     * Apply a signed hour delta to the profile total and the month's statistic
     * row, never letting either fall below zero.
     */
    protected static function adjustHours(int $userId, string $attendanceDate, float $delta): void
    {
        if ($delta === 0.0) {
            return;
        }

        $profile = VolunteerProfile::query()->where('user_id', $userId)->first();
        if ($profile) {
            $profile->total_volunteer_hours = max(0, round((float) $profile->total_volunteer_hours + $delta, 2));
            $profile->save();
        }

        $stat = VolunteerStatistic::query()->firstOrCreate(
            [
                'user_id' => $userId,
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

        $stat->volunteer_hours = max(0, round((float) $stat->volunteer_hours + $delta, 2));
        $stat->save();
    }
}
