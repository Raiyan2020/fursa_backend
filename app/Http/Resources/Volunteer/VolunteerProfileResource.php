<?php

namespace App\Http\Resources\Volunteer;

use App\Enums\ApprovalStatus;
use App\Http\Resources\Concerns\ResolvesApiPayloads;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches Django VolunteerProfileSerializer.
 * Own profile: `user` is the FK primary key (int).
 * all-volunteers: use VolunteerProfileWithUserResource instead.
 */
class VolunteerProfileResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'gender.choiceType',
            'user.interests',
            'user.masterInterests.choiceType',
            'user.badge',
            'currentBadge',
        ]);

        $user = $this->user;

        $badge = null;
        if ($user?->badge) {
            $badge = $this->badgeInfoPayload($user->badge);
        } elseif ($this->currentBadge) {
            $badge = $this->badgeInfoPayload($this->currentBadge);
        }

        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'occupation' => $this->occupation,
            'experience' => $this->experience,
            'health_concerns' => $this->health_concerns,
            'is_public' => (bool) $this->is_public,
            'is_verified' => (bool) $this->is_verified,
            'gender_display' => $this->masterChoicePayload($this->gender),
            'birth_year' => $user?->birth_year,
            'instagram_link' => $user?->instagram_link,
            'whatsapp_link' => $user?->whatsapp_link,
            'linkedin_link' => $user?->linkedin_link,
            'facebook_link' => $user?->facebook_link,
            'twitter_link' => $user?->twitter_link,
            'socialMedia' => $this->socialMediaList($user),
            'user' => $this->userPayload(),
            'interests' => ($user?->interests ?? collect())->map(fn ($i) => $this->interestPayload($i))->values()->all(),
            'nationality' => $user?->nationality?->value ?? $user?->nationality,
            'total_volunteer_hours' => (float) ($this->total_volunteer_hours ?? 0),
            'total_opportunities' => $this->total_opportunities ?? 0,
            'total_certificates' => $this->total_certificates ?? 0,
            'opportunities_organized' => $this->opportunities_organized ?? 0,
            'current_rank' => $this->current_rank,
            'current_year_hours' => (float) ($this->current_year_hours ?? 0),
            'badge_info' => $badge,
            'statistics' => [
                'current_year' => [
                    'hours' => (float) ($this->current_year_hours ?? 0),
                    'rank' => $this->current_rank,
                ],
                'all_time' => [
                    'total_hours' => round((float) ($this->total_volunteer_hours ?? 0), 2),
                    'total_opportunities' => round((float) ($this->total_opportunities ?? 0), 2),
                    'total_certificates' => $this->total_certificates ?? 0,
                    'opportunities_organized' => round((float) ($this->opportunities_organized ?? 0), 2),
                ],
            ],
            'interest_display' => $this->masterChoiceCollection($user?->masterInterests),
            'dob' => optional($user?->dob)?->format('Y-m-d'),
            'email' => $user?->email,
            'civil_id' => $user?->civil_id,
            'phone_number' => $user?->phone_number,
            'country_code' => $user?->country_code,
            'emergency_contact_name' => $user?->emergency_contact_name,
            'emergency_contact_phone' => $user?->emergency_contact_phone,
            'emergency_contact_country_code' => $user?->emergency_contact_country_code,
            'emergency_contact_civil_id' => $user?->emergency_contact_civil_id,
            'emergency_contact_relationship' => $user?->emergency_contact_relationship_id,
            'emergency_contact_relationship_display' => $this->masterChoicePayload($user?->emergencyContactRelationship),
            // "خبير / Expert": a volunteer who runs their own workshops, courses
            // or consultations. Shown as a tag on the Development tab.
            'is_expert' => $this->isExpert($user),
            'expert_opportunities_count' => $this->expertOpportunitiesCount($user),
            // Counters the client wants hidden until they hold a real value.
            'counter_visibility' => [
                'volunteer_hours' => true,
                'volunteer_opportunities' => true,
                'development' => $this->developmentActivityCount($user) > 0,
                'certificates' => (int) ($this->total_certificates ?? 0) > 0,
                'sponsorship' => false,
            ],
        ];
    }

    /**
     * Expert = the volunteer created at least one approved development
     * opportunity (workshop / course / consultation) of their own.
     */
    protected function isExpert($user): bool
    {
        return $this->expertOpportunitiesCount($user) > 0;
    }

    protected function expertOpportunitiesCount($user): int
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
     * Development activity = development opportunities the volunteer attended,
     * plus any they ran themselves. Drives whether the tab/counter shows.
     */
    protected function developmentActivityCount($user): int
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

    protected function userPayload(): mixed
    {
        return $this->user_id;
    }
}
