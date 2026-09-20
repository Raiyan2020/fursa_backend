<?php

namespace App\Http\Resources\Opportunity;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use App\Models\VolunteerOpportunityAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** Matches Django VolunteerOpportunityRegistrationSerializer read output. */
class VolunteerOpportunityRegistrationResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'user.volunteerProfile',
            'user.emergencyContactRelationship',
            'opportunity',
            'assignment.role',
            'assignment.team',
        ]);

        $user = $this->user;
        $assignment = $this->assignment;
        $volunteerProfile = $user?->volunteerProfile;

        $contact = null;
        if ($user?->phone_number) {
            $contact = ($user->country_code ?? '').$user->phone_number;
        }

        $attendanceRecords = VolunteerOpportunityAttendance::query()
            ->where('registration_id', $this->id)
            ->where('is_attended', true)
            ->orderBy('attended_date')
            ->get();

        $attendedDates = $attendanceRecords
            ->pluck('attended_date')
            ->map(fn ($d) => optional($d)->format('Y-m-d'))
            ->values()
            ->all();

        // BE-67.2 — the attendance id was never returned, so the organizer's
        // edit-hours / undo actions only worked for check-ins made in the
        // current browser session (remembered client-side) and vanished on
        // refresh. This is what those actions actually key on.
        $attendances = $attendanceRecords
            ->map(fn ($attendance) => [
                'id' => $attendance->id,
                'attended_date' => optional($attendance->attended_date)->format('Y-m-d'),
                'total_hours' => $attendance->total_hours,
                'checked_in_at' => optional($attendance->checked_in_at)?->toIso8601String(),
                'checked_out_at' => optional($attendance->checked_out_at)?->toIso8601String(),
            ])
            ->values()
            ->all();

        $isAttendedToday = VolunteerOpportunityAttendance::query()
            ->where('registration_id', $this->id)
            ->whereDate('attended_date', now()->toDateString())
            ->where('is_attended', true)
            ->exists();

        $qrCodeUrl = null;
        if ($volunteerProfile?->qr_code) {
            $qrCodeUrl = getimg($volunteerProfile->qr_code);
        }

        return [
            'id' => $this->id,
            'opportunity' => $this->opportunity_id,
            // BE-67.1 — was a bare id, which orphaned every `user.*` read on
            // both frontends (guardian fields, the Excel export). `user_id`
            // stays available too since the unregister call depends on it.
            'user' => $user ? [
                'id' => $user->id,
                'emergency_contact_name' => $user->emergency_contact_name,
                'emergency_contact_phone' => $user->emergency_contact_phone,
                'emergency_contact_civil_id' => $user->emergency_contact_civil_id,
                'emergency_contact_relationship_display' => $this->masterChoicePayload($user->emergencyContactRelationship),
            ] : null,
            'user_id' => $this->user_id,
            'registration_date' => optional($this->registration_date)?->toIso8601String(),
            'status' => $this->status?->value ?? $this->status,
            'full_name' => $this->fullName($user),
            'user_email' => $user?->email,
            'team' => $assignment?->team ? [
                'id' => $assignment->team->id,
                'name_en' => $assignment->team->team_name_en,
                'name_ar' => $assignment->team->team_name_ar,
            ] : null,
            'role' => $assignment?->role ? [
                'id' => $assignment->role->id,
                'name_en' => $assignment->role->role_name_en,
                'name_ar' => $assignment->role->role_name_ar,
            ] : null,
            'user_contact_number' => $contact,
            'qr_code_url' => $qrCodeUrl,
            'volunteer_uuid' => $volunteerProfile?->uuid ? (string) $volunteerProfile->uuid : null,
            'is_attended' => $isAttendedToday,
            'date_wise_attended' => $attendedDates,
            'attendances' => $attendances,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'phone_number' => $user?->phone_number,
            'civil_id' => $user?->civil_id,
            'passport_number' => $user?->passport_number,
        ];
    }
}
