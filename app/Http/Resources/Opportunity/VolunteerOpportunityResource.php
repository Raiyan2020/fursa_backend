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
            'opportunity_status' => $this->opportunity_status?->value ?? $this->opportunity_status,
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
            'interests' => $this->interests?->map(fn ($i) => $this->interestPayload($i))->values(),
            'opportunity_images' => $this->opportunityImagesPayload($images),
            'opportunity_sponsor_images' => $this->opportunitySponsorImagesPayload($this->sponsorImages ?? collect()),
            'created_by' => $this->createdByPayload($this->creator, $request),
            'user_type' => $this->creator?->user_type?->value ?? $this->creator?->user_type,
            'registration_link' => $this->generated_link,
            'after_completed_images_count' => $this->afterCompletedImagesCount($images),
            'opportunity_type' => 'volunteer_opportunity',
            'registered_volunteers_count' => $this->registeredVolunteersCount($registrations),
            'is_registered' => $this->isVolunteerOpportunityRegistered($this->resource, $request),
            'is_saved_to_calendar' => $this->isSavedToVolunteerCalendar($this->resource, $request),
            'location_en' => $this->location_en,
            'location_ar' => $this->location_ar,
            'interest_display' => null,
            'is_kuwaitis' => (bool) $this->is_kuwaitis,
            'total_roles' => $this->roles?->filter(fn ($r) => ! $r->is_deleted)->count() ?? 0,
            'all_registered_user' => $this->allRegisteredUsersPayload($registrations),
            'has_scan_permission' => $this->hasScanPermission($this->resource, $request),
            'manual_tracking' => $this->manualTracking($this->participants_needed),
        ];
    }
}
