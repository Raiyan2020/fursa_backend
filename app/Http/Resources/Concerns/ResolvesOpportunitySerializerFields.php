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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'image' => $img->image ? Storage::disk('public')->url($img->image) : null,
            'is_after_completed' => (bool) $img->is_after_completed,
        ])->values()->all();
    }

    protected function opportunitySponsorImagesPayload($sponsorImages): array
    {
        return collect($sponsorImages)->map(function ($obj) {
            $org = $obj->organization;
            $image = null;

            if ($org?->user) {
                if ($org->user->profile_pic) {
                    $image = Storage::disk('public')->url($org->user->profile_pic);
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
        return $path ? Storage::disk('public')->url($path) : null;
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
