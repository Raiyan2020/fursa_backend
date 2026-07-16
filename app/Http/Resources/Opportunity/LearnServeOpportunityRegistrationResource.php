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
        $this->resource->loadMissing(['user', 'opportunity', 'assignment.timeSlot']);

        return [
            'id' => $this->id,
            'opportunity_id' => $this->opportunity_id,
            'user_id' => $this->user_id,
            'user_name' => $this->fullName($this->user),
            'user_email' => $this->user?->email,
            'registration_date' => optional($this->registration_date)?->toIso8601String(),
            'status' => $this->status?->value,
            'is_attended' => $this->is_attended,
            'is_certified' => $this->is_certified,
            'certificate_image' => $this->certificate_image
                ? Storage::disk('public')->url($this->certificate_image)
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
