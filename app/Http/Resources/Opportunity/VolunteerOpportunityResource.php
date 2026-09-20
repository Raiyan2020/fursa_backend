<?php

namespace App\Http\Resources\Opportunity;

use App\Http\Resources\Concerns\ResolvesOpportunitySerializerFields;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Matches Django VolunteerOpportunitySerializer read output. */
class VolunteerOpportunityResource extends JsonResource
{
    use ResolvesOpportunitySerializerFields;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'creator.volunteerProfile.gender.choiceType',
            'creator.emergencyContactRelationship.choiceType',
            'gender.choiceType',
            'interests',
            'masterInterests',
            'images',
            'sponsorImages.organization.user',
            'roles',
            'registrations.user',
        ]);

        $images = $this->images?->filter(fn ($img) => ! $img->is_deleted) ?? collect();
        $registrations = $this->registrations?->filter(fn ($r) => ! $r->is_deleted) ?? collect();

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
            'opportunity_nationality' => $this->opportunity_nationality,
            'participants_needed' => $this->participants_needed,
            'from_age' => $this->from_age,
            'to_age' => $this->to_age,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'link' => $this->link,
            'is_calendar' => (bool) $this->is_calendar,
            'primary_language' => $this->primary_language?->value ?? $this->primary_language,
            'volunteer_hours_per_day' => $this->volunteer_hours_per_day,
            'gender_display' => $this->masterChoicePayload($this->gender),
            'is_public' => (bool) $this->is_public,
            'license_image' => $this->licenseImageUrl($this->license_image),
            'is_relief' => (bool) $this->is_relief,
            'is_interview_needed' => (bool) $this->is_interview_needed,
            'is_urgent' => (bool) $this->is_urgent,
            'is_supports_disabled' => (bool) $this->is_supports_disabled,
            'interests' => $this->effectiveInterests($this->resource)->map(fn ($i) => $this->tagPayload($i))->values(),
            'opportunity_images' => $this->opportunityImagesPayload($images),
            'opportunity_sponsor_images' => $this->opportunitySponsorImagesPayload($this->sponsorImages ?? collect()),
            'created_by' => $this->createdByPayload($this->creator, $request),
            'user_type' => $this->creator?->user_type?->value ?? $this->creator?->user_type,
            // Private opportunities are shared by their real frontend detail
            // URL. generated_link remains an opaque invite token for legacy
            // rows, but a bare UUID is not navigable and must not be exposed
            // as though it were a URL.
            'registration_link' => rtrim((string) config('fursa.frontend_host'), '/').'/volunteer-event-detail/'.$this->id,
            'after_completed_images_count' => $this->afterCompletedImagesCount($images),
            'opportunity_type' => 'volunteer_opportunity',
            'registered_volunteers_count' => $this->registeredVolunteersCount($registrations),
            'is_registered' => $this->isVolunteerOpportunityRegistered($this->resource, $request),
            'is_saved_to_calendar' => $this->isSavedToVolunteerCalendar($this->resource, $request),
            'calendar_id' => $this->volunteerCalendarId($this->resource, $request),
            // Map picker fields. `lat`/`lng` are numbers, not strings.
            'map_desc' => $this->map_desc ?: ($this->location_ar ?: $this->location_en),
            'lat' => $this->latitude === null ? null : (float) $this->latitude,
            'lng' => $this->longitude === null ? null : (float) $this->longitude,
            'interest_display' => $this->interestDisplayPayload($this->effectiveInterests($this->resource), 'volunteer_opportunity_interest'),
            'is_kuwaitis' => (bool) $this->is_kuwaitis,
            'total_roles' => $this->roles?->filter(fn ($r) => ! $r->is_deleted)->count() ?? 0,
            'all_registered_user' => $this->allRegisteredUsersPayload($registrations),
            'has_scan_permission' => $this->hasScanPermission($this->resource, $request),
            'manual_tracking' => true,
            'qr_attendance_enabled' => true,
            'manual_attendance_enabled' => true,
            // BE-60: `link` is the WhatsApp contact link, not a location — a
            // field named location_url must not be able to return a phone number.
            'location_url' => $this->location_url,
            'is_registration_closed' => (bool) $this->is_registration_closed,
            'is_registration_open' => $this->isRegistrationOpen(),
            'preparation_valid_until' => optional($this->preparationValidUntil())?->toDateString(),
            'preparation_valid_until_at' => optional($this->preparationValidUntil())?->toIso8601String(),
            'is_preparation_window_closed' => $this->isPreparationWindowClosed(),
            // Per-day schedule. Empty means the single start_time/end_time
            // applies across the whole start_date..end_date range.
            'has_custom_schedule' => $this->hasCustomSchedule(),
            'time_slots' => $this->timeSlots
                ? $this->timeSlots
                    ->filter(fn ($slot) => ! $slot->is_deleted)
                    ->sortBy('date')
                    ->map(fn ($slot) => [
                        'id' => $slot->id,
                        'date' => optional($slot->date)->toDateString(),
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'hours' => $slot->durationInHours(),
                    ])->values()
                : [],
            'preparation_reopened_until' => optional($this->preparation_reopened_until)?->toIso8601String(),
            'is_emergency' => (bool) $this->is_emergency,
            'is_relief' => (bool) $this->is_relief,
            'volunteer_category' => $this->volunteer_category?->value,
            'volunteer_category_display' => $this->volunteer_category
                ? [
                    'en' => $this->volunteer_category->labelEn(),
                    'ar' => $this->volunteer_category->labelAr(),
                ]
                : null,
            // Beneficiaries are only tracked for charity opportunities.
            'beneficiaries_count' => $this->volunteer_category?->countsBeneficiaries()
                ? (int) ($this->beneficiaries_count ?? 0)
                : null,
            'supports_beneficiaries_count' => (bool) $this->volunteer_category?->countsBeneficiaries(),
            // One computed state so every screen renders the same button.
            'action_state' => $this->resource->actionState(
                $this->isVolunteerOpportunityRegistered($this->resource, $request),
                $this->registeredVolunteersCount($registrations)
            ),
            'is_full' => $this->resource->isAtCapacity($this->registeredVolunteersCount($registrations)),
            'has_started' => $this->resource->hasStarted(),
            'has_ended' => $this->resource->hasEnded(),
            // organizer / sponsor / registered / attended for the current viewer.
            'relationship_tags' => $this->relationshipTags($this->resource, $request),
            // BE-61 Part A: which of the two printed codes the registered
            // viewer's button should offer next. Null when not registered.
            'self_attendance' => $this->selfAttendanceState($this->resource, $request),
            // BE-69 — lets a volunteer holding the «إذن تحضير» permission see
            // the same manage-attendance screen the organizer does.
            'can_manage_attendance' => $this->canManageAttendanceState($this->resource, $request),
        ];
    }
}
