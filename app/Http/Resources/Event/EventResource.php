<?php

namespace App\Http\Resources\Event;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'approval_status' => $this->approval_status?->value,
            'deletion_status' => $this->deletion_status?->value,
            'event_status' => $this->event_status?->value,
            'from_age' => $this->from_age,
            'to_age' => $this->to_age,
            'gender_id' => $this->gender_id,
            'attendance_type_id' => $this->attendance_type_id,
            'event_type_id' => $this->event_type_id,
            'due_date' => $this->due_date?->toIso8601String(),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'registration_required' => (bool) $this->registration_required,
            'participants_needed' => $this->participants_needed,
            'paid_registration' => (bool) $this->paid_registration,
            'registration_fee' => $this->registration_fee,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location_en' => $this->location_en,
            'location_ar' => $this->location_ar,
            'participation_type_id' => $this->participation_type_id,
            'registration_link' => $this->registration_link,
            'created_by' => $this->created_by,
            'license_image' => $this->license_image ? getimg($this->license_image) : null,
            'view_count' => $this->view_count,
            'primary_language' => $this->primary_language?->value,
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'image' => getimg($img->image),
            ])),
            'sponsor_images' => $this->whenLoaded('sponsorImages', fn () => $this->sponsorImages->map(fn ($img) => [
                'id' => $img->id,
                'image' => getimg($img->image),
            ])),
            'interests' => $this->whenLoaded('interests', fn () => $this->interests->pluck('id')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
