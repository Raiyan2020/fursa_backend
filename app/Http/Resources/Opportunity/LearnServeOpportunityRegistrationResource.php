<?php

namespace App\Http\Resources\Opportunity;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class LearnServeOpportunityRegistrationResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['user.volunteerProfile', 'opportunity', 'assignment.timeSlot']);

        $volunteerProfile = $this->user?->volunteerProfile;
        $contact = $this->user?->phone_number
            ? ($this->user->country_code ?? '').$this->user->phone_number
            : null;

        return [
            'id' => $this->id,
            'opportunity_id' => $this->opportunity_id,
            'user_id' => $this->user_id,
            'user_name' => $this->fullName($this->user),
            'full_name' => $this->fullName($this->user),
            'user_email' => $this->user?->email,
            'civil_id' => $this->user?->civil_id,
            'passport_number' => $this->user?->passport_number,
            'phone_number' => $this->user?->phone_number,
            'user_contact_number' => $contact,
            'profile_pic' => $this->profilePicUrl($this->user),
            'gender_display' => $this->masterChoicePayload($volunteerProfile?->gender),
            'is_public' => (bool) ($volunteerProfile?->is_public ?? false),
            'registration_date' => optional($this->registration_date)?->toIso8601String(),
            'status' => $this->status?->value,
            'is_attended' => $this->is_attended,
            'is_certified' => $this->is_certified,
            'certificate_image' => $this->certificate_image
                ? getimg($this->certificate_image)
                : null,
            'time_slot' => $this->assignment?->timeSlot ? [
                'id' => $this->assignment->timeSlot->id,
                'date' => optional($this->assignment->timeSlot->date)?->toDateString(),
                'start_time' => $this->assignment->timeSlot->start_time,
                'end_time' => $this->assignment->timeSlot->end_time,
            ] : null,
        ];
    }
}
