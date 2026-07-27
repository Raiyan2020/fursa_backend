<?php

namespace App\Http\Resources\Website;

use App\Http\Resources\Website\Concerns\BuildsWebsiteFields;
use App\Models\OrganizationProfile;
use App\Models\VolunteerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Website list item for /api/all-profiles/. */
class WebsiteProfileListItemResource extends JsonResource
{
    use BuildsWebsiteFields;

    public function __construct($resource, protected string $type = 'volunteer')
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        if ($this->type === 'volunteer') {
            /** @var VolunteerProfile $profile */
            $profile = $this->resource;
            $profile->loadMissing(['user.volunteerProfile.gender.choiceType']);

            return [
                'id' => $profile->id,
                'nickname' => $profile->nickname,
                'is_public' => (bool) $profile->is_public,
                'user_details' => [
                    'id' => $profile->user->id,
                    'first_name' => $profile->user->first_name,
                    'last_name' => $profile->user->last_name,
                    'profile_pic' => $this->profilePicUrl($profile->user),
                    'gender_display' => $this->websiteChoicePayload($profile->gender),
                    'user_type' => $profile->user->user_type?->value ?? $profile->user->user_type,
                ],
            ];
        }

        /** @var OrganizationProfile $profile */
        $profile = $this->resource;
        $profile->loadMissing(['user']);

        return [
            'id' => $profile->id,
            'nickname' => $profile->nickname,
            'is_public' => true,
            'user_details' => [
                'id' => $profile->user->id,
                'first_name' => $profile->user->first_name,
                'last_name' => $profile->user->last_name,
                'profile_pic' => $this->profilePicUrl($profile->user),
                'gender_display' => null,
                'user_type' => $profile->user->user_type?->value ?? $profile->user->user_type,
            ],
        ];
    }
}
