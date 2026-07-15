<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Matches Django VolunteerPublicSerializer (nested under profile_data). */
class VolunteerPublicProfileResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'volunteerProfile.gender.choiceType',
            'volunteerProfile.currentBadge',
            'masterInterests.choiceType',
        ]);

        $profile = $this->volunteerProfile;
        $badge = $profile?->currentBadge;
        $currentBadge = null;
        if ($badge) {
            $colors = [
                'GOLD' => '#FFD700',
                'SILVER' => '#C0C0C0',
                'BRONZE' => '#CD7F32',
            ];
            $currentBadge = [
                'name' => $badge->name,
                'description' => $badge->description,
                'color' => $colors[strtoupper((string) $badge->name)] ?? null,
            ];
        }

        return [
            'id' => $this->id,
            'full_name' => $this->fullName($this->resource),
            'nickname' => $profile?->nickname,
            'profile_pic' => $this->profilePicUrl($this->resource),
            'gender_display' => $this->masterChoicePayload($profile?->gender),
            'interest_display' => $this->masterChoiceCollection($this->masterInterests),
            'occupation' => $profile?->occupation,
            'experience' => $profile?->experience,
            'current_badge' => $currentBadge,
            'manual_id' => $this->manual_id,
            'instagram_link' => $this->instagram_link,
            'whatsapp_link' => $this->whatsapp_link,
            'linkedin_link' => $this->linkedin_link,
            'facebook_link' => $this->facebook_link,
            'twitter_link' => $this->twitter_link,
            'total_volunteer_hours' => round((float) ($profile?->total_volunteer_hours ?? 0), 2),
            'total_opportunities' => round((float) ($profile?->total_opportunities ?? 0), 2),
            'total_certificates' => $profile?->total_certificates ?? 0,
            'opportunities_organized' => round((float) ($profile?->opportunities_organized ?? 0), 2),
        ];
    }
}
