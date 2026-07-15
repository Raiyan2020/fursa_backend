<?php

namespace App\Http\Resources\Concerns;

use App\Models\MasterChoice;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

trait ResolvesApiPayloads
{
    protected function masterChoicePayload(?MasterChoice $choice): ?array
    {
        if (! $choice) {
            return null;
        }

        $choice->loadMissing('choiceType');

        return [
            'id' => $choice->id,
            'choice_type' => $choice->choiceType?->name,
            'value_en' => $choice->value_en,
            'value_ar' => $choice->value_ar,
        ];
    }

    protected function masterChoiceCollection($choices): ?array
    {
        if ($choices === null || (is_countable($choices) && count($choices) === 0)) {
            return null;
        }

        return collect($choices)->map(fn ($c) => $this->masterChoicePayload($c))->values()->all();
    }

    protected function profilePicUrl(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->profile_pic) {
            return Storage::disk('public')->url($user->profile_pic);
        }

        if ($user->is_social_login && $user->social_profile_pic_url) {
            return $user->social_profile_pic_url;
        }

        return null;
    }

    protected function socialMediaList(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $links = [
            ['platform' => 'instagram', 'link' => $user->instagram_link],
            ['platform' => 'whatsapp', 'link' => $user->whatsapp_link],
            ['platform' => 'linkedin', 'link' => $user->linkedin_link],
            ['platform' => 'facebook', 'link' => $user->facebook_link],
            ['platform' => 'twitter', 'link' => $user->twitter_link],
        ];

        return array_values(array_filter($links, static fn (array $entry) => ! empty($entry['link'])));
    }

    protected function interestPayload($interest): array
    {
        return [
            'id' => $interest->id,
            'name_en' => $interest->name_en,
            'name_ar' => $interest->name_ar,
            'interest_type' => $interest->interest_type?->value ?? $interest->interest_type,
        ];
    }

    protected function badgeInfoPayload($badge): ?array
    {
        if (! $badge) {
            return null;
        }

        return [
            'id' => $badge->id,
            'name' => $badge->name,
        ];
    }

    protected function documentUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    protected function fullName(?User $user): string
    {
        if (! $user) {
            return '';
        }

        return trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
    }
}
