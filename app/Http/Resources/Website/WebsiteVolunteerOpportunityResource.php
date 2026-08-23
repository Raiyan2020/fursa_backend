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

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\VolunteerOpportunityRegistration>  $registrations
     */
    protected function isRegisteredInLoaded(
        $registrations,
        Request $request,
        VolunteerOpportunity $opportunity
    ): bool {
        $user = $request->user();

        if (! $user || $opportunity->created_by === $user->id) {
            return false;
        }

        return $registrations->contains(fn ($registration) => (int) $registration->user_id === (int) $user->id);
    }

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
            'location_url' => $opportunity->location_url ?: $opportunity->link,
            'is_registration_closed' => (bool) $opportunity->is_registration_closed,
            'is_registration_open' => $opportunity->isRegistrationOpen(),
            'participants_needed' => ar_num($opportunity->participants_needed),
            'registered_volunteers_count' => ar_num($registrations->count()),
            'opportunity_images' => $this->websiteImageList(opportunity_card_images($images)),
            'created_by' => $this->websiteCreatorId($opportunity->creator),
            'interest_display' => $this->websiteInterestDisplay($opportunity->interests ?? collect()),
            'is_supports_disabled' => (bool) $opportunity->is_supports_disabled,
            'is_urgent' => (bool) $opportunity->is_urgent,
            // "outside Kuwait" classification; emergency is now its own priority.
            'is_relief' => (bool) $opportunity->is_relief,
            'is_emergency' => (bool) $opportunity->is_emergency,
            'is_public' => (bool) $opportunity->is_public,
            // Derived from the already-loaded registrations so the card list
            // does not fire one query per row.
            'is_registered' => $this->isRegisteredInLoaded($registrations, $request, $opportunity),
            'volunteer_category' => $opportunity->volunteer_category?->value,
            'volunteer_category_display' => $opportunity->volunteer_category
                ? [
                    'en' => $opportunity->volunteer_category->labelEn(),
                    'ar' => $opportunity->volunteer_category->labelAr(),
                ]
                : null,
            'beneficiaries_count' => $opportunity->volunteer_category?->countsBeneficiaries()
                ? ar_num((int) ($opportunity->beneficiaries_count ?? 0))
                : null,
            'all_registered_user' => $this->websiteRegisteredUserIds($registrations),
        ];
    }
}
