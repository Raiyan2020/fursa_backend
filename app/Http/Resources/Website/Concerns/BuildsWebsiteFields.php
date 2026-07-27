<?php

namespace App\Http\Resources\Website\Concerns;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use App\Models\MasterChoice;
use App\Models\User;
use Illuminate\Http\Request;

trait BuildsWebsiteFields
{
    use ResolvesApiPayloads;

    protected function websiteChoicePayload(?MasterChoice $choice): ?array
    {
        if (! $choice) {
            return null;
        }

        return [
            'id' => $choice->id,
            'value_en' => $choice->value_en,
            'value_ar' => $choice->value_ar,
        ];
    }

    protected function websiteInterestDisplay($interests): array
    {
        return collect($interests)->map(fn ($interest) => [
            'value_en' => $interest->name_en,
            'value_ar' => $interest->name_ar,
        ])->values()->all();
    }

    protected function websiteImageList($images): array
    {
        return collect($images)->map(fn ($img) => [
            'image' => $img->image ? getimg($img->image) : null,
        ])->values()->all();
    }

    protected function websiteImageListWithIds($images): array
    {
        return collect($images)->map(fn ($img) => [
            'id' => $img->id,
            'image' => $img->image ? getimg($img->image) : null,
        ])->values()->all();
    }

    protected function websiteCreatorId(?User $creator): ?array
    {
        return $creator ? ['id' => $creator->id] : null;
    }

    protected function websiteRegisteredUserIds($registrations): array
    {
        return collect($registrations)
            ->filter(fn ($registration) => ! $registration->is_deleted)
            ->map(fn ($registration) => ['id' => $registration->user_id])
            ->values()
            ->all();
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

    protected function postNickname(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return $user->volunteerProfile?->nickname
            ?? $user->organizationProfile?->nickname;
    }

    protected function postUserPayload(?User $user, Request $request): ?array
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing(['volunteerProfile.gender.choiceType']);

        return [
            'id' => $user->id,
            'full_name' => $this->fullName($user),
            'profile_pic' => $this->profilePicUrl($user),
            'gender_display' => $this->websiteChoicePayload($user->volunteerProfile?->gender),
            'is_public' => (bool) ($user->volunteerProfile?->is_public ?? false),
            'user_type' => $user->user_type?->value ?? $user->user_type,
        ];
    }
}
