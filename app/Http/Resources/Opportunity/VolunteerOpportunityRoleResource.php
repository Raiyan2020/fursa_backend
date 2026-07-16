<?php

namespace App\Http\Resources\Opportunity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerOpportunityRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'opportunity_id' => $this->opportunity_id,
            'role_name_en' => $this->role_name_en,
            'role_name_ar' => $this->role_name_ar,
            'instructions_en' => $this->instructions_en,
            'instructions_ar' => $this->instructions_ar,
            'participants_needed' => $this->participants_needed,
        ];
    }
}
