<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Matches Django UserSerializer output (write_only fields excluded). */
class UserResource extends JsonResource
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

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'dob' => optional($this->dob)?->format('Y-m-d'),
            'birth_year' => $this->birth_year,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'country_code' => $this->country_code,
            'profile_pic' => $this->profilePicUrl($this->resource),
            'is_social_login' => (bool) $this->is_social_login,
            'is_verified' => $isVerified,
            'user_type' => $this->user_type?->value ?? $this->user_type,
            'gender_display' => $this->masterChoicePayload($volunteer?->gender),
            'organizer_type_display' => $this->masterChoicePayload($organization?->organizerType),
            'latitude' => $organization?->latitude,
            'longitude' => $organization?->longitude,
            'nationality' => $this->nationality?->value ?? $this->nationality,
            'manual_id' => $this->manual_id,
            'is_banned' => (bool) $this->is_banned,
            'preferred_language' => $this->preferred_language?->value ?? $this->preferred_language,
            'receive_reminder_emails' => (bool) $this->receive_reminder_emails,
            'civil_id' => $this->civil_id,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_country_code' => $this->emergency_contact_country_code,
            'emergency_contact_civil_id' => $this->emergency_contact_civil_id,
            'emergency_contact_relationship' => $this->emergency_contact_relationship_id,
            'emergency_contact_relationship_display' => $this->masterChoicePayload($this->emergencyContactRelationship),
        ];
    }
}
