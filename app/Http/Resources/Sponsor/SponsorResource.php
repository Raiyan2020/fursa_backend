<?php

namespace App\Http\Resources\Sponsor;

use App\Models\Sponsor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sponsor */
class SponsorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sponsor_type_id' => $this->sponsor_type_id,
            'org_name' => $this->org_name,
            'org_type_id' => $this->org_type_id,
            'person_name' => $this->person_name,
            'email' => $this->email,
            'country_code' => $this->country_code,
            'phone_number' => $this->phone_number,
            'type_of_support_id' => $this->type_of_support_id,
            'sponsorship_details' => $this->sponsorship_details,
            'why_interested' => $this->why_interested,
            'resources_expected' => $this->resources_expected,
            'sponsor_logo' => $this->sponsor_logo ? getimg($this->sponsor_logo) : null,
            'approval_status' => $this->approval_status?->value,
            'preferred_language' => $this->preferred_language?->value,
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($doc) => [
                'id' => $doc->id,
                'document' => getimg($doc->document),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
