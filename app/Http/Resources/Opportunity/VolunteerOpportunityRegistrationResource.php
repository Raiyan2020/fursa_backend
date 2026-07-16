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

        $attendedDates = VolunteerOpportunityAttendance::query()
            ->where('registration_id', $this->id)
            ->where('is_attended', true)
            ->pluck('attended_date')
            ->map(fn ($d) => optional($d)->format('Y-m-d'))
            ->values()
            ->all();

        $isAttendedToday = VolunteerOpportunityAttendance::query()
            ->where('registration_id', $this->id)
            ->whereDate('attended_date', now()->toDateString())
            ->where('is_attended', true)
            ->exists();

        $qrCodeUrl = null;
        if ($volunteerProfile?->qr_code) {
            $qrCodeUrl = Storage::disk('public')->url($volunteerProfile->qr_code);
        }

        return [
            'id' => $this->id,
            'opportunity' => $this->opportunity_id,
            'user' => $this->user_id,
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
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'phone_number' => $user?->phone_number,
        ];
    }
}
