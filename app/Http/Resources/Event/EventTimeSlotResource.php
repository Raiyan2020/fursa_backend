<?php

namespace App\Http\Resources\Event;

use App\Models\EventTimeSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventTimeSlot */
class EventTimeSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'date' => $this->date?->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'participants_needed' => $this->participants_needed,
        ];
    }
}
