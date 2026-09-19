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

    /**
     * @param  int|null  $profileOwnerId  Set only when this card is rendered
     *                                    inside someone's profile activity tab
     *                                    — enables `profile_activity_tag`.
     */
    public function __construct($resource, protected ?int $profileOwnerId = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var LearnServeOpportunity $opportunity */
        $opportunity = $this->resource;

        $opportunity->loadMissing(['creator', 'interests', 'masterInterests', 'images', 'registrations', 'format.choiceType', 'learningType.choiceType']);
        $images = $opportunity->images?->filter(fn ($image) => ! $image->is_deleted) ?? collect();
        $registrations = $opportunity->registrations?->filter(fn ($registration) => ! $registration->is_deleted) ?? collect();

        $viewer = $request->user();
        $isRegistered = $viewer !== null
            && $opportunity->created_by !== $viewer->id
            && $registrations->contains(fn ($registration) => (int) $registration->user_id === (int) $viewer->id);

        $ownerRegistration = $this->profileOwnerId
            ? $registrations->first(fn ($r) => (int) $r->user_id === $this->profileOwnerId)
            : null;

        $viewerRegistration = $viewer
            ? $registrations->first(fn ($r) => (int) $r->user_id === (int) $viewer->id)
            : null;
        $relationshipTags = [];
        if ($viewer && (int) $opportunity->created_by === (int) $viewer->id) {
            $relationshipTags[] = 'organizer';
        }
        $viewerOrgId = $viewer?->organizationProfile?->id;
        if ($viewerOrgId) {
            $sponsorImages = $opportunity->relationLoaded('sponsorImages') ? $opportunity->sponsorImages : $opportunity->sponsorImages()->get();
            if (collect($sponsorImages)->filter(fn ($img) => ! ($img->is_deleted ?? false))->contains(fn ($img) => (int) ($img->organization_id ?? 0) === (int) $viewerOrgId)) {
                $relationshipTags[] = 'sponsor';
            }
        }
        if ($viewerRegistration) {
            $relationshipTags[] = 'registered';
            if ($viewerRegistration->is_attended) {
                $relationshipTags[] = 'attended';
            }
        }

        return [
            'id' => $opportunity->id,
            'opportunity_type' => 'learn_serve_opportunity',
            'title_en' => $opportunity->title_en,
            'title_ar' => $opportunity->title_ar,
            'opportunity_status' => $opportunity->resolvedOpportunityStatus(),
            'due_date' => $this->formatDateTime($opportunity->due_date),
            'start_date' => $this->formatDate($opportunity->start_date),
            'end_date' => $this->formatDate($opportunity->end_date),
            'start_time' => $opportunity->start_time,
            'end_time' => $opportunity->end_time,
            'from_age' => ar_num($opportunity->from_age),
            'to_age' => ar_num($opportunity->to_age),
            'location_en' => $opportunity->location_en,
            'location_ar' => $opportunity->location_ar,
            // Map picker fields. `lat`/`lng` are numbers, not strings.
            'map_desc' => $opportunity->map_desc ?: ($opportunity->location_ar ?: $opportunity->location_en),
            'lat' => $opportunity->latitude === null ? null : (float) $opportunity->latitude,
            'lng' => $opportunity->longitude === null ? null : (float) $opportunity->longitude,
            // BE-60: no fallback to `link` — a field named location_url must
            // not be able to return a phone number, and here `link` is the
            // meeting URL anyway, not a WhatsApp contact (see BE-65).
            'location_url' => $opportunity->location_url,
            // BE-65 — a separate, ungated contact number, unlike `link` (the
            // online meeting URL, gated to registered participants).
            'whatsapp_link' => $opportunity->whatsapp_link,
            'is_registration_closed' => (bool) $opportunity->is_registration_closed,
            'is_registration_open' => $opportunity->isRegistrationOpen(),
            'is_paid' => (bool) $opportunity->is_paid,
            'price' => $opportunity->price !== null ? (float) $opportunity->price : null,
            'participants_needed' => ar_num($opportunity->participants_needed),
            'registered_volunteers_count' => ar_num($registrations->count()),
            'opportunity_images' => $this->websiteImageList(opportunity_card_images($images)),
            'created_by' => $this->websiteCreatorId($opportunity->creator),
            'interest_display' => $this->websiteInterestDisplay($this->effectiveInterests($opportunity)),
            'format_display' => $this->websiteChoicePayload($opportunity->format),
            'learning_type_id' => $opportunity->learning_type_id,
            'format_id' => $opportunity->format_id,
            'certificate_type_id' => $opportunity->certificate_type_id,
            'requires_check_in' => $opportunity->requiresCheckIn(),
            'certificate_type_display' => $this->websiteChoicePayload($opportunity->certificateType),
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
            'relationship_tags' => array_values(array_unique($relationshipTags)),
            'profile_activity_tag' => $this->profileActivityTag(
                $this->profileOwnerId,
                $request,
                isCreator: $this->profileOwnerId !== null && (int) $opportunity->created_by === $this->profileOwnerId,
                isRegistered: $ownerRegistration !== null,
                isAttended: (bool) $ownerRegistration?->is_attended,
                isDevelopment: true
            ),
        ];
    }
}
