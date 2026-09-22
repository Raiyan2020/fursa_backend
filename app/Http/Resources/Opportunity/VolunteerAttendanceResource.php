<?php

namespace App\Http\Resources\Opportunity;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use App\Http\Resources\Concerns\ResolvesOpportunitySerializerFields;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerAttendanceResource extends JsonResource
{
    use ResolvesApiPayloads;
    use ResolvesOpportunitySerializerFields;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['registration.user', 'registration.opportunity']);

        return [
            'id' => $this->id,
            'registration_id' => $this->registration_id,
            'attended_date' => optional($this->attended_date)?->toDateString(),
            'total_hours' => $this->total_hours,
            'is_attended' => $this->is_attended,
            'recorded_via' => $this->recorded_via,
            'checked_in_at' => optional($this->checked_in_at)?->toIso8601String(),
            'checked_out_at' => optional($this->checked_out_at)?->toIso8601String(),
            'self_check_out_closes_at' => $this->selfCheckOutClosesAt(),
            'volunteer_name' => $this->fullName($this->registration?->user),
            'opportunity_id' => $this->registration?->opportunity_id,
            'opportunity_title_en' => $this->registration?->opportunity?->title_en,
            'opportunity_title_ar' => $this->registration?->opportunity?->title_ar,
        ];
    }

    /**
     * BE-75 Part B — the departure deadline (session end + grace), so the
     * frontend no longer has to derive it client-side. Only meaningful while
     * a check-out is pending. Shared with selfAttendanceState().
     */
    protected function selfCheckOutClosesAt(): ?string
    {
        $opportunity = $this->registration?->opportunity;
        if (! $opportunity) {
            return null;
        }

        return $this->selfCheckOutClosesAtFor($opportunity, $this->resource);
    }
}
