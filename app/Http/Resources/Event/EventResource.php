<?php

namespace App\Http\Resources\Event;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'approval_status' => $this->approval_status?->value,
            'deletion_status' => $this->deletion_status?->value,
            'event_status' => $this->resource->resolvedOpportunityStatus(),
            'from_age' => $this->from_age,
            'to_age' => $this->to_age,
            'gender_id' => $this->gender_id,
            'attendance_type_id' => $this->attendance_type_id,
            'event_type_id' => $this->event_type_id,
            'due_date' => $this->due_date?->toIso8601String(),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'registration_required' => (bool) $this->registration_required,
            'participants_needed' => $this->participants_needed,
            'paid_registration' => (bool) $this->paid_registration,
            'registration_fee' => $this->registration_fee,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location_en' => $this->location_en,
            'location_ar' => $this->location_ar,
            // Map picker fields. `lat`/`lng` are numbers, not strings.
            'map_desc' => $this->map_desc ?: ($this->location_ar ?: $this->location_en),
            'lat' => $this->latitude === null ? null : (float) $this->latitude,
            'lng' => $this->longitude === null ? null : (float) $this->longitude,
            'location_url' => $this->location_url,
            'is_registration_closed' => (bool) $this->is_registration_closed,
            'is_registration_open' => $this->isRegistrationOpen(),
            'participation_type_id' => $this->participation_type_id,
            'registration_link' => $this->registration_link,
            'created_by' => $this->created_by,
            'license_image' => $this->license_image ? getimg($this->license_image) : null,
            'view_count' => $this->view_count,
            'primary_language' => $this->primary_language?->value,
            'images' => $this->whenLoaded('images', fn () => $this->images->reject(fn ($img) => $img->is_deleted)->values()->map(fn ($img) => [
                'id' => $img->id,
                'image' => getimg($img->image),
            ])),
            'sponsor_images' => $this->whenLoaded('sponsorImages', fn () => $this->sponsorImages->reject(fn ($img) => $img->is_deleted)->sortBy('position')->values()->map(fn ($img) => [
                'id' => $img->id,
                'image' => $img->image ? getimg($img->image) : null,
                'position' => $img->position,
                'organization' => $img->organization ? [
                    'id' => $img->organization_id,
                    'full_name' => $img->organization->company_name ?: $img->organization->nickname,
                    'profile_pic' => $img->organization->user?->profile_pic ? getimg($img->organization->user->profile_pic) : null,
                ] : null,
            ])),
            'interests' => $this->effectiveInterests($this->resource)->map(fn ($i) => $this->tagPayload($i))->values(),
            'interest_display' => $this->interestDisplayPayload($this->effectiveInterests($this->resource), 'event_interest'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // One computed state so every screen renders the same button.
            'action_state' => $this->resource->actionState($this->isRegisteredForEvent($request)),
            'is_full' => $this->resource->isAtCapacity(),
            'has_started' => $this->resource->hasStarted(),
            'has_ended' => $this->resource->hasEnded(),
            // Matches the organizer/sponsor/registered/attended vocabulary the
            // volunteer/learn-serve detail resources already expose.
            'relationship_tags' => $this->relationshipTags($request),
        ];
    }

    /**
     * Registration lookup kept local so the resource has no extra dependency.
     */
    protected function isRegisteredForEvent($request): bool
    {
        return $this->eventRegistrationFor($request) !== null;
    }

    protected function eventRegistrationFor($request): ?EventRegistration
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return EventRegistration::query()
            ->where('event_id', $this->resource->id)
            ->where('user_id', $user->id)
            ->where('is_deleted', false)
            ->first();
    }

    protected function relationshipTags($request): array
    {
        $user = $request->user();
        if (! $user) {
            return [];
        }

        $tags = [];
        $orgId = $user->organizationProfile?->id;
        if ($orgId && (int) $this->resource->created_by === (int) $orgId) {
            $tags[] = 'organizer';
        }

        if ($orgId) {
            $images = $this->resource->relationLoaded('sponsorImages')
                ? $this->resource->sponsorImages
                : $this->resource->sponsorImages()->get();
            if ($images->filter(fn ($img) => ! ($img->is_deleted ?? false))->contains(fn ($img) => (int) ($img->organization_id ?? 0) === (int) $orgId)) {
                $tags[] = 'sponsor';
            }
        }

        $registration = $this->eventRegistrationFor($request);
        if ($registration) {
            $tags[] = 'registered';
            if ($registration->is_attended) {
                $tags[] = 'attended';
            }
        }

        return array_values(array_unique($tags));
    }
}
