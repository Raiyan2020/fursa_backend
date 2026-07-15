<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Matches Django SocialAuthSerializer + to_representation extras. */
class SocialAuthUserResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'volunteerProfile.gender.choiceType',
            'organizationProfile.organizerType.choiceType',
            'emergencyContactRelationship.choiceType',
        ]);

        $volunteer = $this->volunteerProfile;
        $organization = $this->organizationProfile;

        $isVerified = null;
        if ($this->isVolunteer()) {
            $isVerified = (bool) ($volunteer?->is_verified);
        } elseif ($this->isOrganization()) {
            $isVerified = $organization?->isApproved() ?? false;
        }

        $data = [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'social_profile_pic_url' => $this->social_profile_pic_url,
            'social_media_id' => $this->social_media_id,
            'social_media_provider' => $this->social_media_provider?->value ?? $this->social_media_provider,
            'user_type' => $this->user_type?->value ?? $this->user_type,
            'manual_id' => $this->manual_id,
            'is_new_user' => (bool) ($this->resource->_is_new_user ?? false),
            'nickname' => $volunteer?->nickname ?? $organization?->nickname,
            'gender_display' => $this->masterChoicePayload($volunteer?->gender),
            'dob' => optional($this->dob)?->format('Y-m-d'),
            'birth_year' => $this->birth_year,
            'phone_number' => $this->phone_number,
            'country_code' => $this->country_code,
            'organizer_type_display' => $this->masterChoicePayload($organization?->organizerType),
            'registration_number' => $organization?->registration_number,
            'license_number' => $organization?->license_number,
            'is_verified' => $isVerified,
            'is_banned' => (bool) $this->is_banned,
            'latitude' => $organization?->latitude,
            'longitude' => $organization?->longitude,
            'nationality' => $this->nationality?->value ?? $this->nationality,
            'preferred_language' => $this->preferred_language?->value ?? $this->preferred_language,
            'company_name' => $organization?->company_name,
            'civil_id' => $this->civil_id,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_country_code' => $this->emergency_contact_country_code,
            'emergency_contact_civil_id' => $this->emergency_contact_civil_id,
            'emergency_contact_relationship' => $this->emergency_contact_relationship_id,
            'emergency_contact_relationship_display' => $this->masterChoicePayload($this->emergencyContactRelationship),
            'profile_pic' => $this->profilePicUrl($this->resource),
        ];

        if ($this->isVolunteer() && $volunteer) {
            $data['volunteer'] = [
                'id' => $volunteer->id,
                'organization_id' => $volunteer->organization_id,
                'is_verified' => (bool) $volunteer->is_verified,
                'is_public' => (bool) $volunteer->is_public,
            ];
        } elseif ($this->isOrganization() && $organization) {
            $data['organization'] = [
                'id' => $organization->id,
                'organization_status' => $organization->organization_status?->value ?? $organization->organization_status,
            ];
        }

        return $data;
    }
}
