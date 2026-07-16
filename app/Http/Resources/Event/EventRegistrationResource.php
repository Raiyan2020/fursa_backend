<?php

namespace App\Http\Resources\Event;

use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventRegistration */
class EventRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'user_id' => $this->user_id,
            'time_slot_id' => $this->time_slot_id,
            'registration_date' => $this->registration_date?->toIso8601String(),
            'registration_status' => $this->registration_status?->value,
            'is_attended' => (bool) $this->is_attended,
            'payment_status' => $this->payment_status?->value,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
                'email' => $this->user->email,
                'phone_number' => $this->user->phone_number,
            ]),
            'event' => $this->whenLoaded('event', fn () => new EventResource($this->event)),
        ];
    }
}
