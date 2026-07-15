<?php

namespace App\Http\Resources\Volunteer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Matches Django VolunteerVerificationSerializer. */
class VolunteerVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('user');

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'email' => $this->user?->email,
            'nickname' => $this->nickname,
            'is_verified' => (bool) $this->is_verified,
            'is_public' => (bool) $this->is_public,
            'total_volunteer_hours' => (float) ($this->total_volunteer_hours ?? 0),
            'total_opportunities' => $this->total_opportunities ?? 0,
            'total_certificates' => $this->total_certificates ?? 0,
            'opportunities_organized' => $this->opportunities_organized ?? 0,
            'current_rank' => $this->current_rank,
            'current_year_hours' => (float) ($this->current_year_hours ?? 0),
            'current_badge' => $this->current_badge_id,
        ];
    }
}
