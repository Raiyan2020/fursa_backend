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
            'opportunity_status' => $this->opportunity_status?->value ?? $this->opportunity_status,
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
            'is_saved_to_calendar' => $this->isSavedToLearnServeCalendar($this->resource, $request),
            'interest_display' => null,
            'is_kuwaitis' => (bool) $this->is_kuwaitis,
            'timeslots_display' => LearnServeTimeSlotResource::collection($timeSlots)->resolve(),
            'license_image' => $this->licenseImageUrl($this->license_image),
            'all_registered_user' => $this->allRegisteredUsersPayload($registrations),
        ];
    }
}
