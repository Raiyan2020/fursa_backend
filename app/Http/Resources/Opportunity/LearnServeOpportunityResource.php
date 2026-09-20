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
            'masterInterests',
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
            // BE-65 — a separate, ungated contact number, unlike `link` (the
            // online meeting URL, gated to registered participants).
            'whatsapp_link' => $this->whatsapp_link,
            'is_calendar' => (bool) $this->is_calendar,
            'primary_language' => $this->primary_language?->value ?? $this->primary_language,
            'created_by' => $this->createdByPayload($this->creator, $request),
            'user_type' => $this->creator?->user_type?->value ?? $this->creator?->user_type,
            'learning_type_id' => $this->learning_type_id,
            'format_id' => $this->format_id,
            'certificate_type_id' => $this->certificate_type_id,
            'learning_type_display' => $this->masterChoicePayload($this->learningType),
            'format_display' => $this->masterChoicePayload($this->format),
            'certificate_type_display' => $this->masterChoicePayload($this->certificateType),
            'interests' => $this->effectiveInterests($this->resource)->map(fn ($i) => $this->tagPayload($i))->values(),
            'gender_display' => $this->masterChoicePayload($this->gender),
            'opportunity_images' => $this->opportunityImagesPayload($images),
            // PDF review: a sponsor funds a free opportunity; a paid one is
            // already self-funded by participants, so the sponsor name is
            // withheld here rather than left attached to something it didn't pay for.
            'opportunity_sponsor_images' => $this->price === null
                ? $this->opportunitySponsorImagesPayload($this->sponsorImages ?? collect())
                : [],
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
            // BE-60: no fallback to `link` — a field named location_url must
            // not be able to return a phone number, and here `link` is the
            // meeting URL anyway, not a WhatsApp contact (see BE-65).
            'location_url' => $this->location_url,
            'is_registration_closed' => (bool) $this->is_registration_closed,
            'is_registration_open' => $this->isRegistrationOpen(),
            'is_paid' => (bool) $this->is_paid,
            'price' => $this->price !== null ? (float) $this->price : null,
            'payout_after_fee' => $this->resource->payoutAfterFee(),
            // BE-71 — travels with payout_after_fee so a publisher's copy of
            // the fee can never disagree with the number actually used.
            'platform_fee_percentage' => $this->resource->platformFeePercentage(),
            // BE-61 Part B: type-derived now that Internship is excluded from
            // the single expiring-code check-in (workshops/consultations get
            // it despite currently running with no check-in step at all).
            'qr_attendance_enabled' => $this->resource->qrAttendanceEligible(),
            'manual_attendance_enabled' => $this->requiresCheckIn(),
            'requires_check_in' => $this->requiresCheckIn(),
            'preparation_valid_until' => optional($this->preparationValidUntil())?->toDateString(),
            'is_saved_to_calendar' => $this->isSavedToLearnServeCalendar($this->resource, $request),
            'calendar_id' => $this->learnServeCalendarId($this->resource, $request),
            'interest_display' => $this->interestDisplayPayload($this->effectiveInterests($this->resource), 'learnserve_opportunity_interest'),
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
