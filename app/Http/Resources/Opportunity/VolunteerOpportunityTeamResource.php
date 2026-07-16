<?php

namespace App\Http\Resources\Opportunity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerOpportunityTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'opportunity_id' => $this->opportunity_id,
            'team_name_en' => $this->team_name_en,
            'team_name_ar' => $this->team_name_ar,
        ];
    }
}
