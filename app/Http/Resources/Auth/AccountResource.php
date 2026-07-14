<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** Matches Django AccountInfoSerializer output. */
class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'volunteerProfile.gender.choiceType',
            'emergencyContactRelationship.choiceType',
        ]);

        $profilePic = null;
        if ($this->profile_pic) {
            $profilePic = Storage::disk('public')->url($this->profile_pic);
        } elseif ($this->is_social_login && $this->social_profile_pic_url) {
            $profilePic = $this->social_profile_pic_url;
        }

        $gender = $this->volunteerProfile?->gender;

        return [
            'id' => $this->id,
            'profile_pic' => $profilePic,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'country_code' => $this->country_code,
            'birth_year' => $this->birth_year,
            'password' => null,
            'manual_id' => $this->manual_id,
            'user_type' => $this->user_type?->value ?? $this->user_type,
            'password_length' => $this->password_length,
            'nationality' => $this->nationality?->value ?? $this->nationality,
            'gender_display' => $gender ? [
                'id' => $gender->id,
                'choice_type' => $gender->choiceType?->name,
                'value_en' => $gender->value_en,
                'value_ar' => $gender->value_ar,
            ] : null,
            'preferred_language' => $this->preferred_language?->value ?? $this->preferred_language,
            'civil_id' => $this->civil_id,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_country_code' => $this->emergency_contact_country_code,
            'emergency_contact_civil_id' => $this->emergency_contact_civil_id,
            'emergency_contact_relationship' => $this->emergency_contact_relationship_id,
            'emergency_contact_relationship_display' => $this->emergencyContactRelationship ? [
                'id' => $this->emergencyContactRelationship->id,
                'choice_type' => $this->emergencyContactRelationship->choiceType?->name,
                'value_en' => $this->emergencyContactRelationship->value_en,
                'value_ar' => $this->emergencyContactRelationship->value_ar,
            ] : null,
        ];
    }
}
