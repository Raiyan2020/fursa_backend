<?php

namespace App\Http\Resources\Website;

use App\Http\Resources\Website\Concerns\BuildsWebsiteFields;
use App\Models\VolunteerOpportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Website list/card payload for volunteer opportunities. */
class WebsiteVolunteerOpportunityResource extends JsonResource
{
    use BuildsWebsiteFields;

    public function toArray(Request $request): array
    {
        /** @var VolunteerOpportunity $opportunity */
        $opportunity = $this->resource;

        $opportunity->loadMissing(['creator', 'interests', 'images', 'registrations']);
        $images = $opportunity->images?->filter(fn ($image) => ! $image->is_deleted) ?? collect();
        $registrations = $opportunity->registrations?->filter(fn ($registration) => ! $registration->is_deleted) ?? collect();

        return [
            'id' => $opportunity->id,
            'opportunity_type' => 'volunteer_opportunity',
            'title_en' => $opportunity->title_en,
            'title_ar' => $opportunity->title_ar,
            'opportunity_status' => $opportunity->opportunity_status?->value ?? $opportunity->opportunity_status,
            'due_date' => $this->formatDateTime($opportunity->due_date),
            'start_date' => $this->formatDate($opportunity->start_date),
            'end_date' => $this->formatDate($opportunity->end_date),
            'start_time' => $opportunity->start_time,
            'end_time' => $opportunity->end_time,
            'from_age' => ar_num($opportunity->from_age),
            'to_age' => ar_num($opportunity->to_age),
            'location_en' => $opportunity->location_en,
            'location_ar' => $opportunity->location_ar,
            'participants_needed' => ar_num($opportunity->participants_needed),
            'registered_volunteers_count' => ar_num($registrations->count()),
            'opportunity_images' => $this->websiteImageList(opportunity_card_images($images)),
            'created_by' => $this->websiteCreatorId($opportunity->creator),
            'interest_display' => $this->websiteInterestDisplay($opportunity->interests ?? collect()),
            'is_supports_disabled' => (bool) $opportunity->is_supports_disabled,
            'is_urgent' => (bool) $opportunity->is_urgent,
            'is_relief' => (bool) $opportunity->is_relief,
            'all_registered_user' => $this->websiteRegisteredUserIds($registrations),
        ];
    }
}
