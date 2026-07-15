<?php

namespace App\Http\Resources\Volunteer;

use App\Http\Resources\Auth\CustomUserResource;

/** Matches Django VolunteerProfileWithUserSerializer (nested CustomUser). */
class VolunteerProfileWithUserResource extends VolunteerProfileResource
{
    protected function userPayload(): mixed
    {
        return $this->user
            ? (new CustomUserResource($this->user))->resolve()
            : null;
    }
}
