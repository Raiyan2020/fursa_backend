<?php

namespace App\Http\Resources\Organization;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use App\Models\Config;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Matches Django OrganizerProfileSerializer. */
class OrganizationProfileResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'sector.choiceType',
            'organizerType.choiceType',
            'documents',
            'user.interests',
            'user.masterInterests.choiceType',
            'user.badge',
        ]);

        $user = $this->user;
        $documents = ($this->documents ?? collect())
            ->filter(fn ($d) => ! ($d->is_deleted ?? false))
            ->values();

        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'company_name' => $this->company_name,
            'sector_display' => $this->masterChoicePayload($this->sector),
            'organizer_type_display' => $this->masterChoicePayload($this->organizerType),
            'registration_number' => $this->registration_number,
            'license_number' => $this->license_number,
            'instagram_link' => $user?->instagram_link,
            'whatsapp_link' => $user?->whatsapp_link,
            'linkedin_link' => $user?->linkedin_link,
            'facebook_link' => $user?->facebook_link,
            'twitter_link' => $user?->twitter_link,
            'socialMedia' => $this->socialMediaList($user),
            'interests' => ($user?->interests ?? collect())->map(fn ($i) => $this->interestPayload($i))->values()->all(),
            'documents' => OrganizationDocumentResource::collection($documents)->resolve(),
            'badge_info' => $this->badgeInfoPayload($user?->badge),
            'longitude' => $this->longitude,
            'latitude' => $this->latitude,
            'bank_name' => $this->bank_name,
            'bank_account_holder_name' => $this->bank_account_holder_name,
            'bank_account_number' => $this->bank_account_number,
            'requires_bank_details_for_paid_opportunities' => $this->resource->isIndividualOrAssociationPublisher(),
            // BE-74 — the publish form has no LearnServeOpportunity yet when a
            // publisher is setting the price, so it reads the fee here instead
            // of the opportunity resource (BE-71). Same Config value, same method.
            'platform_fee_percentage' => Config::platformFeePercentage(),
            'nationality' => $user?->nationality?->value ?? $user?->nationality,
            'interest_display' => $this->masterChoiceCollection($user?->masterInterests),
            'is_volunteer_team' => ($this->organizerType?->value_en === 'Volunteer Team'),
            'organization_hours' => $this->organization_hours,
            'learn_opportunity_organized' => $this->learn_opportunity_organized,
            'vol_opportunity_organized' => $this->vol_opportunity_organized,
            'sponsored_count' => $this->sponsored_count,
        ];
    }
}
