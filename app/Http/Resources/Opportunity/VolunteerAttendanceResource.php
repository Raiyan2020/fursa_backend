<?php

namespace App\Http\Resources\Opportunity;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerAttendanceResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['registration.user', 'registration.opportunity']);

        return [
            'id' => $this->id,
            'registration_id' => $this->registration_id,
            'attended_date' => optional($this->attended_date)?->toDateString(),
            'total_hours' => $this->total_hours,
            'is_attended' => $this->is_attended,
            'volunteer_name' => $this->fullName($this->registration?->user),
            'opportunity_id' => $this->registration?->opportunity_id,
            'opportunity_title_en' => $this->registration?->opportunity?->title_en,
            'opportunity_title_ar' => $this->registration?->opportunity?->title_ar,
        ];
    }
}
