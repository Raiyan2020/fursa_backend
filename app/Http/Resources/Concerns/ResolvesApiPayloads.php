<?php

namespace App\Http\Resources\Concerns;

use App\Enums\ApprovalStatus;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\MasterChoice;
use App\Models\User;

trait ResolvesApiPayloads
{
    /**
     * "خبير / Expert": a volunteer who runs their own workshops, courses or
     * consultations (created at least one approved development opportunity).
     */
    protected function isExpert(?User $user): bool
    {
        return $this->expertOpportunitiesCount($user) > 0;
    }

    protected function expertOpportunitiesCount(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return (int) LearnServeOpportunity::query()
            ->where('created_by', $user->id)
            ->where('is_deleted', false)
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->count();
    }

    /**
     * Development activity = development opportunities the volunteer
     * attended, plus any they ran themselves. Powers the "development
     * opportunities" counter that replaces the certificates counter on the
     * profile card (certificates themselves still live in their own tab).
     */
    protected function developmentActivityCount(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        $attended = (int) LearnServeOpportunityRegistration::query()
            ->where('user_id', $user->id)
            ->where('is_deleted', false)
            ->where('is_attended', true)
            ->count();

        return $attended + $this->expertOpportunitiesCount($user);
    }
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
            return getimg($user->profile_pic);
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

    /**
     * Same interests as `interestPayload`, reshaped to match the
     * MasterChoice-style display other `interest_display` fields use
     * app-wide (id / choice_type / value_en / value_ar).
     */
    protected function interestDisplayPayload($interests, string $choiceType): ?array
    {
        if ($interests === null || (is_countable($interests) && count($interests) === 0)) {
            return null;
        }

        return collect($interests)->map(fn ($interest) => [
            'id' => $interest->id,
            'choice_type' => $choiceType,
            'value_en' => $interest->name_en,
            'value_ar' => $interest->name_ar,
        ])->values()->all();
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

        return getimg($path);
    }

    protected function fullName(?User $user): string
    {
        if (! $user) {
            return '';
        }

        return trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
    }
}
