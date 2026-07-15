<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Matches Django AccountInfoSerializer output. */
class AccountResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'volunteerProfile.gender.choiceType',
            'emergencyContactRelationship.choiceType',
        ]);

        return [
            'id' => $this->id,
            'profile_pic' => $this->profilePicUrl($this->resource),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName($this->resource),
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'country_code' => $this->country_code,
            'birth_year' => $this->birth_year,
            'password' => $this->getRawOriginal('password'),
            'manual_id' => $this->manual_id,
            'user_type' => $this->user_type?->value ?? $this->user_type,
            'password_length' => $this->password_length,
            'nationality' => $this->nationality?->value ?? $this->nationality,
            'gender_display' => $this->masterChoicePayload($this->volunteerProfile?->gender),
            'preferred_language' => $this->preferred_language?->value ?? $this->preferred_language,
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
