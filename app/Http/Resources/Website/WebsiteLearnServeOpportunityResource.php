<?php

namespace App\Http\Resources\Website;

use App\Http\Resources\Website\Concerns\BuildsWebsiteFields;
use App\Models\LearnServeOpportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Website list/card payload for learn & serve opportunities. */
class WebsiteLearnServeOpportunityResource extends JsonResource
{
    use BuildsWebsiteFields;

    public function toArray(Request $request): array
    {
        /** @var LearnServeOpportunity $opportunity */
        $opportunity = $this->resource;

        $opportunity->loadMissing(['creator', 'interests', 'images', 'registrations', 'format.choiceType', 'learningType.choiceType']);
        $images = $opportunity->images?->filter(fn ($image) => ! $image->is_deleted) ?? collect();
        $registrations = $opportunity->registrations?->filter(fn ($registration) => ! $registration->is_deleted) ?? collect();

        $viewer = $request->user();
        $isRegistered = $viewer !== null
            && $opportunity->created_by !== $viewer->id
            && $registrations->contains(fn ($registration) => (int) $registration->user_id === (int) $viewer->id);

        return [
            'id' => $opportunity->id,
            'opportunity_type' => 'learn_serve_opportunity',
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
            'format_display' => $this->websiteChoicePayload($opportunity->format),
            'learning_type_display' => $this->websiteChoicePayload($opportunity->learningType),
            'is_supports_disabled' => (bool) $opportunity->is_supports_disabled,
            'is_urgent' => (bool) $opportunity->is_urgent,
            'is_relief' => (bool) $opportunity->is_relief,
            'all_registered_user' => $this->websiteRegisteredUserIds($registrations),
            // One computed state so every screen renders the same button.
            'action_state' => $opportunity->actionState($isRegistered, $registrations->count()),
            'is_registered' => $isRegistered,
            'is_full' => $opportunity->isAtCapacity($registrations->count()),
            'has_started' => $opportunity->hasStarted(),
            'has_ended' => $opportunity->hasEnded(),
        ];
    }
}
