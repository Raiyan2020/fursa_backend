<?php

namespace App\Http\Resources\Website;

use App\Http\Resources\Website\Concerns\BuildsWebsiteFields;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Website public profile payload. */
class WebsitePublicProfileResource extends JsonResource
{
    use BuildsWebsiteFields;

    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $user->loadMissing([
            'volunteerProfile.currentBadge',
            'volunteerProfile.gender.choiceType',
            'organizationProfile.organizerType',
            'organizationProfile.sector.choiceType',
            'organizationProfile.documents',
            'masterInterests.choiceType',
            'badge',
        ]);

        $isVolunteerTeam = $user->organizationProfile?->organizerType?->value_en === 'Volunteer Team';
        $profileData = null;

        if ($user->isVolunteer()) {
            $profile = $user->volunteerProfile;
            $profileData = [
                'id' => $user->id,
                // Privacy: a volunteer's real name is not published. Viewers get
                // the picture, badge, username, current status and interests.
                'full_name' => null,
                'display_name' => $profile?->nickname ?: __('volunteer'),
                'nickname' => $profile?->nickname,
                'profile_pic' => $this->profilePicUrl($user),
                'gender_display' => $this->websiteChoicePayload($profile?->gender),
                'interest_display' => $this->masterChoiceCollection($user->masterInterests),
                'occupation' => $profile?->occupation,
                'current_status' => $profile?->occupation,
                'experience' => $profile?->experience,
                'manual_id' => $user->manual_id,
                'instagram_link' => $user->instagram_link,
                'whatsapp_link' => $user->whatsapp_link,
                'linkedin_link' => $user->linkedin_link,
                'facebook_link' => $user->facebook_link,
                'twitter_link' => $user->twitter_link,
                'total_volunteer_hours' => ar_num(round((float) ($profile?->total_volunteer_hours ?? 0), 2)),
                'total_opportunities' => ar_num(round((float) ($profile?->total_opportunities ?? 0), 2)),
                'total_certificates' => ar_num($profile?->total_certificates ?? 0),
                'opportunities_organized' => ar_num(round((float) ($profile?->opportunities_organized ?? 0), 2)),
                // Replaces the certificates counter on the profile card —
                // see VolunteerProfileResource for the same field on the
                // owner's own view. Certificates keep their own tab.
                'is_expert' => $this->isExpert($user),
                'development_opportunities_count' => $this->developmentActivityCount($user),
                'counter_visibility' => [
                    'volunteer_hours' => true,
                    'volunteer_opportunities' => true,
                    'development' => $this->developmentActivityCount($user) > 0,
                    'certificates' => false,
                    'sponsorship' => false,
                ],
                'statistics' => [
                    'all_time' => [
                        'total_hours' => ar_num(round((float) ($profile?->total_volunteer_hours ?? 0), 2)),
                        'total_opportunities' => ar_num(round((float) ($profile?->total_opportunities ?? 0), 2)),
                        'total_certificates' => ar_num($profile?->total_certificates ?? 0),
                        'opportunities_organized' => ar_num(round((float) ($profile?->opportunities_organized ?? 0), 2)),
                    ],
                ],
            ];
        } elseif ($user->isOrganization()) {
            $organization = $user->organizationProfile;
            $profileData = [
                'id' => $user->id,
                'nickname' => $organization?->nickname,
                'company_name' => $organization?->company_name,
                'profile_pic' => $this->profilePicUrl($user),
                'registration_number' => ar_num($organization?->registration_number),
                'sector_display' => $this->websiteChoicePayload($organization?->sector),
                'interest_display' => $this->masterChoiceCollection($user->masterInterests),
                'documents' => collect($organization?->documents ?? [])->map(fn ($document) => [
                    'id' => $document->id,
                    'document' => getimg($document->document),
                ])->values()->all(),
                'instagram_link' => $user->instagram_link,
                'whatsapp_link' => $user->whatsapp_link,
                'linkedin_link' => $user->linkedin_link,
                'facebook_link' => $user->facebook_link,
                'twitter_link' => $user->twitter_link,
                'organization_hours' => ar_num($organization?->organization_hours),
                'learn_opportunity_organized' => ar_num($organization?->learn_opportunity_organized),
                'vol_opportunity_organized' => ar_num($organization?->vol_opportunity_organized),
                'sponsored' => ar_num($organization?->sponsored_count),
                'statistics' => [
                    'all_time' => [
                        'total_hours' => ar_num($organization?->organization_hours),
                        'opportunities_organized' => ar_num(($organization?->learn_opportunity_organized ?? 0) + ($organization?->vol_opportunity_organized ?? 0)),
                    ],
                ],
            ];
        }

        $badgeInfo = null;
        if ($user->isVolunteer() && $user->volunteerProfile?->currentBadge) {
            $badgeInfo = $this->badgeInfoPayload($user->volunteerProfile->currentBadge);
        } elseif ($user->isOrganization() && $user->badge) {
            $badgeInfo = $this->badgeInfoPayload($user->badge);
        }

        return [
            'id' => $user->id,
            'profile_data' => $profileData,
            'user_type' => $user->user_type?->value ?? $user->user_type,
            'is_volunteer_team' => (bool) $isVolunteerTeam,
            'is_public' => $user->isVolunteer()
                ? (bool) ($user->volunteerProfile?->is_public)
                : true,
            'badge_info' => $badgeInfo,
        ];
    }
}
