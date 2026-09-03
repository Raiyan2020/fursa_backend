<?php

namespace App\Http\Resources\Website;

use App\Http\Resources\Website\Concerns\BuildsWebsiteFields;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Website payload for event list cards and detail screens. */
class WebsiteEventResource extends JsonResource
{
    use BuildsWebsiteFields;

    public function __construct($resource, protected bool $detail = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var Event $event */
        $event = $this->resource;

        $event->loadMissing([
            'images',
            'sponsorImages',
            'interests',
            'organization.user',
            'eventType.choiceType',
            'participationType.choiceType',
            'attendanceType.choiceType',
            'genderChoice.choiceType',
        ]);

        $images = $event->images?->filter(fn ($image) => ! $image->is_deleted) ?? collect();
        $registeredCount = EventRegistration::query()
            ->notDeleted()
            ->where('event_id', $event->id)
            ->count();

        $user = $request->user();
        $creatorUser = $event->organization?->user;
        $isCreator = $user && $creatorUser && $user->id === $creatorUser->id;
        $isRegistered = $user && ! $isCreator
            ? EventRegistration::query()
                ->notDeleted()
                ->where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->exists()
            : false;

        $payload = [
            'id' => $event->id,
            'opportunity_type' => 'event',
            'title_en' => $event->title_en,
            'title_ar' => $event->title_ar,
            'event_status' => $event->resolvedOpportunityStatus(),
            'due_date' => $this->formatDateTime($event->due_date),
            'start_date' => $this->formatDate($event->start_date),
            'end_date' => $this->formatDate($event->end_date),
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'registration_required' => (bool) $event->registration_required,
            'registered_volunteers_count' => ar_num($registeredCount),
            'participants_needed' => ar_num($event->participants_needed),
            'view_count' => ar_num($event->view_count),
            'location_en' => $event->location_en,
            'location_ar' => $event->location_ar,
            // Map picker fields. `lat`/`lng` are numbers, not strings.
            'map_desc' => $event->map_desc ?: ($event->location_ar ?: $event->location_en),
            'lat' => $event->latitude === null ? null : (float) $event->latitude,
            'lng' => $event->longitude === null ? null : (float) $event->longitude,
            'location_url' => $event->location_url ?: $event->registration_link,
            'is_registration_closed' => (bool) $event->is_registration_closed,
            'is_registration_open' => $event->isRegistrationOpen(),
            'event_images' => $this->websiteImageListWithIds($images),
            'participation_type_display' => $this->websiteChoicePayload($event->participationType),
            'event_type_display' => $this->websiteChoicePayload($event->eventType),
            'interest_display' => $this->websiteInterestDisplay($event->interests ?? collect()),
            'created_by' => $this->detail
                ? $this->websiteEventCreator($creatorUser)
                : ($creatorUser ? ['id' => $creatorUser->id] : null),
            'is_creator' => $isCreator,
            'is_registered' => $isRegistered,
            // One computed state so every screen renders the same button.
            'action_state' => $event->actionState($isRegistered, $registeredCount),
            'is_full' => $event->isAtCapacity($registeredCount),
            'has_started' => $event->hasStarted(),
            'has_ended' => $event->hasEnded(),
        ];

        if ($this->detail) {
            $payload = array_merge($payload, [
                'interests' => $event->interests?->map(fn ($i) => $this->interestPayload($i))->values(),
                'description_en' => $event->description_en,
                'description_ar' => $event->description_ar,
                'primary_language' => $event->primary_language?->value ?? $event->primary_language,
                'paid_registration' => (bool) $event->paid_registration,
                'registration_fee' => ar_num($event->registration_fee),
                'registration_link' => $event->registration_link,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'from_age' => ar_num($event->from_age),
                'to_age' => ar_num($event->to_age),
                'attendance_type_display' => $this->websiteChoicePayload($event->attendanceType),
                'gender_display' => $this->websiteChoicePayload($event->genderChoice),
                'event_sponsor_images' => collect($event->sponsorImages ?? [])->map(fn ($image) => [
                    'id' => $image->id,
                    'image' => $image->image ? getimg($image->image) : null,
                    'position' => $image->position,
                ])->values()->all(),
            ]);

            if ($event->registration_required && $event->participants_needed > 0) {
                $payload['remaining_slots'] = ar_num(max(0, $event->participants_needed - $registeredCount));
            }
        }

        return $payload;
    }

    protected function websiteEventCreator(?\App\Models\User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'full_name' => $this->fullName($user),
            'profile_pic' => $this->profilePicUrl($user),
            'is_public' => (bool) ($user->volunteerProfile?->is_public ?? false),
            'facebook_link' => $user->facebook_link,
            'twitter_link' => $user->twitter_link,
            'whatsapp_link' => $user->whatsapp_link,
            'instagram_link' => $user->instagram_link,
            'linkedin_link' => $user->linkedin_link,
        ];
    }
}
