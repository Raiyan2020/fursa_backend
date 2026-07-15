<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Matches Django OrganizationPublicSerializer (nested under profile_data). */
class OrganizationPublicProfileResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'organizationProfile.sector.choiceType',
            'organizationProfile.documents',
            'masterInterests.choiceType',
        ]);

        $org = $this->organizationProfile;
        $documents = ($org?->documents ?? collect())
            ->filter(fn ($d) => ! ($d->is_deleted ?? false))
            ->map(fn ($d) => $this->documentUrl($d->document))
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $this->id,
            'profile_pic' => $this->profilePicUrl($this->resource),
            'company_name' => $org?->company_name,
            'registration_number' => $org?->license_number,
            'nickname' => $org?->nickname,
            'field' => $org?->sector?->value_en,
            'documents' => $documents,
            'social_media' => [
                'facebook' => '',
                'twitter' => '',
                'instagram' => '',
                'linkedin' => '',
            ],
            'interest_display' => $this->masterChoiceCollection($this->masterInterests),
            'organization_status' => $org?->organization_status?->value ?? $org?->organization_status,
            'manual_id' => $this->manual_id,
            'full_name' => $this->fullName($this->resource),
            'sector_display' => $this->masterChoicePayload($org?->sector),
            'instagram_link' => $this->instagram_link,
            'whatsapp_link' => $this->whatsapp_link,
            'linkedin_link' => $this->linkedin_link,
            'facebook_link' => $this->facebook_link,
            'twitter_link' => $this->twitter_link,
            'organization_hours' => 0,
            'vol_opportunity_organized' => 0,
            'learn_opportunity_organized' => 0,
            'sponsored' => 0,
        ];
    }
}
