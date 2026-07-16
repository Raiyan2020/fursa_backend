<?php

namespace App\Http\Resources\Opportunity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Matches Django LearnServeOpportunityTimeSlotSerializer. */
class LearnServeTimeSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('opportunity');

        return [
            'id' => $this->id,
            'opportunity' => $this->opportunity_id,
            'date' => optional($this->date)?->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'participants_needed' => $this->participants_needed,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'opportunity_start_date' => optional($this->opportunity?->start_date)?->format('Y-m-d'),
            'opportunity_end_date' => optional($this->opportunity?->end_date)?->format('Y-m-d'),
            'opportunity_start_time' => $this->opportunity?->start_time,
            'opportunity_end_time' => $this->opportunity?->end_time,
        ];
    }
}
