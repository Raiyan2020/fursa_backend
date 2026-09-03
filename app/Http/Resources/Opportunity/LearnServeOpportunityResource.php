<?php

namespace App\Http\Resources\Opportunity;

use App\Http\Resources\Concerns\ResolvesOpportunitySerializerFields;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Matches Django LearnServeOpportunitySerializer read output. */
class LearnServeOpportunityResource extends JsonResource
{
    use ResolvesOpportunitySerializerFields;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'creator.volunteerProfile.gender.choiceType',
            'creator.emergencyContactRelationship.choiceType',
            'learningType.choiceType',
            'gender.choiceType',
            'format.choiceType',
            'certificateType.choiceType',
            'interests',
            'images',
            'sponsorImages.organization.user',
            'timeSlots.opportunity',
            'registrations.user',
        ]);

        $images = $this->images?->filter(fn ($img) => ! $img->is_deleted) ?? collect();
        $registrations = $this->registrations?->filter(fn ($r) => ! $r->is_deleted) ?? collect();
        $timeSlots = $this->timeSlots?->filter(fn ($ts) => ! $ts->is_deleted) ?? collect();

        return [
            'id' => $this->id,
            'approval_status' => $this->approval_status?->value ?? $this->approval_status,
            'opportunity_status' => $this->resource->resolvedOpportunityStatus(),
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'due_date' => $this->formatDateTime($this->due_date),
            'start_date' => $this->formatDate($this->start_date),
            'end_date' => $this->formatDate($this->end_date),
            'participants_needed' => $this->participants_needed,
            'opportunity_nationality' => $this->opportunity_nationality,
            'from_age' => $this->from_age,
            'to_age' => $this->to_age,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'link' => $this->link,
            'is_calendar' => (bool) $this->is_calendar,
            'primary_language' => $this->primary_language?->value ?? $this->primary_language,
            'created_by' => $this->createdByPayload($this->creator, $request),
            'user_type' => $this->creator?->user_type?->value ?? $this->creator?->user_type,
            'learning_type_display' => $this->masterChoicePayload($this->learningType),
            'format_display' => $this->masterChoicePayload($this->format),
            'certificate_type_display' => $this->masterChoicePayload($this->certificateType),
            'interests' => $this->interests?->map(fn ($i) => $this->interestPayload($i))->values(),
            'gender_display' => $this->masterChoicePayload($this->gender),
            'opportunity_images' => $this->opportunityImagesPayload($images),
            'opportunity_sponsor_images' => $this->opportunitySponsorImagesPayload($this->sponsorImages ?? collect()),
            'after_completed_images_count' => $this->afterCompletedImagesCount($images),
            'opportunity_type' => 'learn_serve_opportunity',
            'is_registered' => $this->isLearnServeOpportunityRegistered($this->resource, $request),
            'is_attended' => $this->isLearnServeAttended($this->resource, $request),
            'registered_volunteers_count' => $this->registeredVolunteersCount($registrations),
            'location_en' => $this->location_en,
            'location_ar' => $this->location_ar,
            // Map picker fields. `lat`/`lng` are numbers, not strings.
            'map_desc' => $this->map_desc ?: ($this->location_ar ?: $this->location_en),
            'lat' => $this->latitude === null ? null : (float) $this->latitude,
            'lng' => $this->longitude === null ? null : (float) $this->longitude,
            'location_url' => $this->location_url ?: $this->link,
            'is_registration_closed' => (bool) $this->is_registration_closed,
            'is_registration_open' => $this->isRegistrationOpen(),
            'is_paid' => (bool) $this->is_paid,
            'qr_attendance_enabled' => true,
            'manual_attendance_enabled' => $this->requiresCheckIn(),
            'requires_check_in' => $this->requiresCheckIn(),
            'preparation_valid_until' => optional($this->preparationValidUntil())?->toDateString(),
            'is_saved_to_calendar' => $this->isSavedToLearnServeCalendar($this->resource, $request),
            'interest_display' => $this->interestDisplayPayload($this->interests, 'learnserve_opportunity_interest'),
            'is_kuwaitis' => (bool) $this->is_kuwaitis,
            'timeslots_display' => LearnServeTimeSlotResource::collection($timeSlots)->resolve(),
            'license_image' => $this->licenseImageUrl($this->license_image),
            'all_registered_user' => $this->allRegisteredUsersPayload($registrations),
            // One computed state so every screen renders the same button.
            'action_state' => $this->resource->actionState(
                $this->isLearnServeOpportunityRegistered($this->resource, $request),
                $this->registeredVolunteersCount($registrations)
            ),
            'is_full' => $this->resource->isAtCapacity($this->registeredVolunteersCount($registrations)),
            'has_started' => $this->resource->hasStarted(),
            'has_ended' => $this->resource->hasEnded(),
            // organizer / sponsor / registered / attended for the current viewer.
            'relationship_tags' => $this->relationshipTags($this->resource, $request),
        ];
    }
}
