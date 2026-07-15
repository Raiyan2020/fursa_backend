<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Matches Django PublicProfileSerializer. */
class PublicProfileResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'volunteerProfile.currentBadge',
            'volunteerProfile.gender.choiceType',
            'organizationProfile.organizerType',
            'organizationProfile.sector.choiceType',
            'organizationProfile.documents',
            'badge',
            'masterInterests.choiceType',
        ]);

        $profileData = null;
        if ($this->isVolunteer()) {
            $profileData = (new VolunteerPublicProfileResource($this->resource))->resolve();
        } elseif ($this->isOrganization()) {
            $profileData = (new OrganizationPublicProfileResource($this->resource))->resolve();
        }

        $isVolunteerTeam = $this->organizationProfile?->organizerType?->value_en === 'Volunteer Team';

        $badgeInfo = null;
        if ($this->isVolunteer() && $this->volunteerProfile?->currentBadge) {
            $badgeInfo = $this->badgeInfoPayload($this->volunteerProfile->currentBadge);
        } elseif ($this->isOrganization() && $this->badge) {
            $badgeInfo = $this->badgeInfoPayload($this->badge);
        }

        return [
            'id' => $this->id,
            'profile_data' => $profileData,
            'user_type' => $this->user_type?->value ?? $this->user_type,
            'is_volunteer_team' => (bool) $isVolunteerTeam,
            'is_public' => $this->isVolunteer()
                ? (bool) ($this->volunteerProfile?->is_public)
                : false,
            'badge_info' => $badgeInfo,
        ];
    }
}
