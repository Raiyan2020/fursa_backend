<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Matches Django opportunity.CustomUserSerializer (nested in all-volunteers).
 */
class CustomUserResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'volunteerProfile.gender.choiceType',
            'emergencyContactRelationship.choiceType',
        ]);

        $volunteer = $this->volunteerProfile;
        $countryCode = $this->country_code;
        $phone = $this->phone_number;
        $userContact = null;
        if ($phone) {
            $userContact = $countryCode ? "{$countryCode}-{$phone}" : $phone;
        }

        $nickname = null;
        if ($this->isVolunteer()) {
            $nickname = $volunteer?->nickname;
        } elseif ($this->isOrganization()) {
            $nickname = $this->fullName($this->resource);
        }

        $profilePic = null;
        if ($this->profile_pic) {
            $profilePic = Storage::disk('public')->url($this->profile_pic);
        }

        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'user_type' => $this->user_type?->value ?? $this->user_type,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName($this->resource),
            'profile_pic' => $profilePic,
            'country_code' => $countryCode,
            'phone_number' => $phone,
            'nationality' => $this->nationality?->value ?? $this->nationality,
            'dob' => optional($this->dob)?->format('Y-m-d'),
            'birth_year' => $this->birth_year,
            'instagram_link' => $this->instagram_link,
            'whatsapp_link' => $this->whatsapp_link,
            'linkedin_link' => $this->linkedin_link,
            'facebook_link' => $this->facebook_link,
            'twitter_link' => $this->twitter_link,
            'gender_display' => $this->masterChoicePayload($volunteer?->gender),
            'is_public' => (bool) ($volunteer?->is_public ?? false),
            'user_contact_number' => $userContact,
            'nickname' => $nickname,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_country_code' => $this->emergency_contact_country_code,
            'emergency_contact_civil_id' => $this->emergency_contact_civil_id,
            'emergency_contact_relationship' => $this->emergency_contact_relationship_id,
            'emergency_contact_relationship_display' => $this->masterChoicePayload($this->emergencyContactRelationship),
        ];
    }
}
