<?php

namespace App\Http\Resources\Concerns;

use App\Http\Resources\Auth\CustomUserResource;
use App\Models\Config;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\MyCalendar;
use App\Models\ScanPermission;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Shared read payload helpers matching Django opportunity serializers.
 */
trait ResolvesOpportunitySerializerFields
{
    use ResolvesApiPayloads;

    protected function opportunityImagesPayload($images): array
    {
        return collect($images)->map(fn ($img) => [
            'id' => $img->id,
            'image' => $img->image ? getimg($img->image) : null,
            'is_after_completed' => (bool) $img->is_after_completed,
        ])->values()->all();
    }

    protected function opportunitySponsorImagesPayload($sponsorImages): array
    {
        return collect($sponsorImages)->filter(fn ($obj) => ! $obj->is_deleted)->map(function ($obj) {
            $org = $obj->organization;
            $image = null;

            if ($org?->user) {
                if ($org->user->profile_pic) {
                    $image = getimg($org->user->profile_pic);
                } elseif ($org->user->social_profile_pic_url) {
                    $image = $org->user->social_profile_pic_url;
                }
            }

            return [
                'id' => $obj->id,
                'image' => $image,
                'organization' => $org ? [
                    'id' => $org->id,
                    'full_name' => $org->company_name ?: $org->nickname,
                ] : null,
                'position' => $obj->position,
            ];
        })->values()->all();
    }

    protected function afterCompletedImagesCount($images): int
    {
        return collect($images)->where('is_after_completed', true)->count();
    }

    protected function registeredVolunteersCount($registrations): int
    {
        return collect($registrations)->filter(fn ($r) => ! $r->is_deleted)->count();
    }

    protected function allRegisteredUsersPayload($registrations): array
    {
        return collect($registrations)
            ->filter(fn ($r) => ! $r->is_deleted)
            ->map(fn ($r) => [
                'id' => $r->user_id,
                'email' => $r->user?->email,
            ])
            ->values()
            ->all();
    }

    protected function isVolunteerOpportunityRegistered(VolunteerOpportunity $opportunity, Request $request): bool
    {
        $user = $request->user();
        if (! $user || $opportunity->created_by === $user->id) {
            return false;
        }

        return VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    protected function isLearnServeOpportunityRegistered(LearnServeOpportunity $opportunity, Request $request): bool
    {
        $user = $request->user();
        if (! $user || $opportunity->created_by === $user->id) {
            return false;
        }

        return LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    protected function isLearnServeAttended(LearnServeOpportunity $opportunity, Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity->id)
            ->where('user_id', $user->id)
            ->where('is_attended', true)
            ->exists();
    }

    protected function isSavedToVolunteerCalendar(VolunteerOpportunity $opportunity, Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return MyCalendar::query()
            ->where('user_id', $user->id)
            ->where('volunteer_opportunity_id', $opportunity->id)
            ->where('is_saved', true)
            ->exists();
    }

    protected function isSavedToLearnServeCalendar(LearnServeOpportunity $opportunity, Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return MyCalendar::query()
            ->where('user_id', $user->id)
            ->where('learn_serve_opportunity_id', $opportunity->id)
            ->where('is_saved', true)
            ->exists();
    }

    protected function manualTracking(?int $participantsNeeded): bool
    {
        $threshold = Config::query()->value('manual_attendance_threshold');
        if ($threshold !== null && $participantsNeeded !== null) {
            return $participantsNeeded >= (int) $threshold;
        }

        return false;
    }

    /**
     * Relationship tags the client asked to show on profile tabs:
     * organizer / sponsor / registered / attended.
     *
     * Computed for the authenticated viewer, so an anonymous request gets [].
     *
     * @return array<int, string>
     */
    protected function relationshipTags(Model $opportunity, Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return [];
        }

        $tags = [];

        if ((int) ($opportunity->created_by ?? 0) === (int) $user->id) {
            $tags[] = 'organizer';
        }

        if ($this->isSponsorOf($opportunity, $user)) {
            $tags[] = 'sponsor';
        }

        if ($opportunity instanceof VolunteerOpportunity) {
            if ($this->isVolunteerOpportunityRegistered($opportunity, $request)) {
                $tags[] = 'registered';
            }
            if ($this->hasVolunteerAttendance($opportunity, $user->id)) {
                $tags[] = 'attended';
            }
        }

        if ($opportunity instanceof LearnServeOpportunity) {
            if ($this->isLearnServeOpportunityRegistered($opportunity, $request)) {
                $tags[] = 'registered';
            }
            if ($this->isLearnServeAttended($opportunity, $request)) {
                $tags[] = 'attended';
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * The viewer's organization appears in the opportunity's sponsor images.
     */
    protected function isSponsorOf(Model $opportunity, $user): bool
    {
        $orgId = $user->organizationProfile?->id;
        if (! $orgId || ! method_exists($opportunity, 'sponsorImages')) {
            return false;
        }

        $images = $opportunity->relationLoaded('sponsorImages')
            ? $opportunity->sponsorImages
            : $opportunity->sponsorImages()->get();

        return $images
            ->filter(fn ($img) => ! ($img->is_deleted ?? false))
            ->contains(fn ($img) => (int) ($img->organization_id ?? 0) === (int) $orgId);
    }

    protected function hasVolunteerAttendance(VolunteerOpportunity $opportunity, int $userId): bool
    {
        return VolunteerOpportunityRegistration::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('user_id', $userId)
            ->where('is_deleted', false)
            ->whereHas('attendances', fn ($q) => $q->where('is_attended', true)->where('is_deleted', false))
            ->exists();
    }

    protected function hasScanPermission(VolunteerOpportunity $opportunity, Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return ScanPermission::query()
            ->notDeleted()
            ->where('opportunity_id', $opportunity->id)
            ->where('user_id', $user->id)
            ->where('is_allowed', true)
            ->exists();
    }

    protected function createdByPayload($creator, Request $request): ?array
    {
        if (! $creator) {
            return null;
        }

        return (new CustomUserResource($creator))->toArray($request);
    }

    protected function licenseImageUrl(?string $path): ?string
    {
        return $path ? getimg($path) : null;
    }

    protected function formatDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return optional($value)->format('Y-m-d\TH:i:s.u\Z') ?? (string) $value;
    }

    protected function formatDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return optional($value)->format('Y-m-d') ?? (string) $value;
    }
}
