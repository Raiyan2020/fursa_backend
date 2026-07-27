<?php

namespace App\Http\Resources\Website;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Website login / session user payload. */
class WebsiteLoginUserResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $user->loadMissing(['volunteerProfile', 'organizationProfile']);

        $payload = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'profile_pic' => $this->profilePicUrl($user),
            'social_media_id' => $user->social_media_id,
            'social_media_provider' => $user->social_media_provider?->value ?? $user->social_media_provider,
            'user_type' => $user->user_type?->value ?? $user->user_type,
            'manual_id' => $user->manual_id,
            'is_new_user' => (bool) ($user->_is_new_user ?? false),
            'is_verified' => $this->isVerified($user),
            'is_banned' => (bool) $user->is_banned,
        ];

        if ($organization = $user->organizationProfile) {
            $payload['organization'] = [
                'id' => $organization->id,
                'organization_status' => $organization->organization_status?->value ?? $organization->organization_status,
            ];
        }

        return $payload;
    }

    protected function isVerified(User $user): ?bool
    {
        if ($user->isVolunteer()) {
            return (bool) ($user->volunteerProfile?->is_verified);
        }

        if ($user->isOrganization()) {
            return $user->organizationProfile?->isApproved() ?? false;
        }

        return null;
    }
}
