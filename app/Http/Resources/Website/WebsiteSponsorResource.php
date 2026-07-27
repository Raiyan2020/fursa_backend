<?php

namespace App\Http\Resources\Website;

use App\Http\Resources\Website\Concerns\BuildsWebsiteFields;
use App\Models\Sponsor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Website payload for sponsor listings. */
class WebsiteSponsorResource extends JsonResource
{
    use BuildsWebsiteFields;

    public function toArray(Request $request): array
    {
        /** @var Sponsor $sponsor */
        $sponsor = $this->resource;

        $sponsor->loadMissing(['sponsorType.choiceType', 'typeOfSupport.choiceType']);

        return [
            'id' => $sponsor->id,
            'org_name' => $sponsor->org_name,
            'person_name' => $sponsor->person_name,
            'sponsor_logo' => $sponsor->sponsor_logo ? getimg($sponsor->sponsor_logo) : null,
            '_sponsor_type' => $this->websiteChoicePayload($sponsor->sponsorType),
            '_type_of_support' => $this->websiteChoicePayload($sponsor->typeOfSupport),
        ];
    }
}
