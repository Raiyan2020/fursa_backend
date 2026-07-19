<?php

namespace App\Http\Controllers\Api\Base;

use App\Enums\ApprovalStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\Event\EventResource;
use App\Http\Resources\Opportunity\LearnServeOpportunityResource;
use App\Http\Resources\Opportunity\VolunteerOpportunityResource;
use App\Http\Resources\Organization\OrganizationProfileResource;
use App\Http\Resources\Sponsor\SponsorResource;
use App\Http\Resources\Volunteer\VolunteerProfileWithUserResource;
use App\Models\BannerImage;
use App\Models\Event;
use App\Models\Faq;
use App\Models\LearnServeOpportunity;
use App\Models\MasterChoice;
use App\Models\OrganizationProfile;
use App\Models\OrganizationStatistic;
use App\Models\Sponsor;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerProfile;
use App\Models\VolunteerStatistic;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Public landing-page aggregate — GET home/
 * No auth. Flat arrays (no pagination) for homepage carousels.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query('limit', 10)));

        return ApiResponse::success(
            [
                'banners' => $this->banners(),
                'statistics' => $this->platformStatistics(),
                'sponsors' => $this->sponsors(),
                'events' => $this->events($limit),
                'opportunities' => $this->opportunities($limit),
                'learn_share' => $this->learnShare(limit: $limit),
                'community' => $this->community($limit),
                'achievements' => $this->achievements(),
                'faqs' => $this->faqs($limit),
            ],
            'Home content retrieved successfully.',
            'تم استرجاع محتوى الصفحة الرئيسية بنجاح.'
        );
    }

    protected function banners()
    {
        return BannerImage::query()->notDeleted()->latest()->get()->map(fn (BannerImage $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'image' => $b->image ? Storage::disk('public')->url($b->image) : null,
            'banner_url' => $b->banner_url,
            'created_at' => optional($b->created_at)?->toIso8601String(),
        ])->values();
    }

    protected function platformStatistics(): array
    {
        $volunteerTeamType = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->where('value_en', 'Volunteer Team')
            ->first();

        $volunteerTeamCount = 0;
        $organizationQuery = User::query()
            ->where('user_type', UserType::ORGANIZATION)
            ->where('is_deleted', false);

        if ($volunteerTeamType) {
            $volunteerTeamCount = OrganizationProfile::query()
                ->where('organizer_type_id', $volunteerTeamType->id)
                ->whereHas('user', fn ($q) => $q->where('is_deleted', false)->where('user_type', UserType::ORGANIZATION))
                ->count();
            $organizationQuery->whereDoesntHave('organizationProfile', function ($q) use ($volunteerTeamType) {
                $q->where('organizer_type_id', $volunteerTeamType->id);
            });
        }

        return [
            'volunteer_count' => User::query()->where('user_type', UserType::VOLUNTEER)->where('is_deleted', false)->count(),
            'volunteer_team_count' => $volunteerTeamCount,
            'organization_count' => $organizationQuery->count(),
        ];
    }

    protected function sponsors()
    {
        $sponsors = Sponsor::query()
            ->notDeleted()
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->with(['documents', 'sponsorType', 'orgType', 'typeOfSupport'])
            ->latest()
            ->get();

        return SponsorResource::collection($sponsors)->resolve();
    }

    protected function events(int $limit)
    {
        $events = Event::query()
            ->notDeleted()
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->with(['images', 'sponsorImages', 'interests'])
            ->latest('start_date')
            ->limit($limit)
            ->get();

        return EventResource::collection($events)->resolve();
    }

    protected function opportunities(int $limit)
    {
        $items = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('is_public', true)
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->with(['creator', 'gender.choiceType', 'interests', 'images'])
            ->withCount(['registrations' => fn ($q) => $q->notDeleted()])
            ->orderBy('start_date')
            ->limit($limit)
            ->get();

        return VolunteerOpportunityResource::collection($items)->resolve();
    }

    protected function learnShare(int $limit)
    {
        $items = LearnServeOpportunity::query()
            ->notDeleted()
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->with(['creator', 'interests', 'images', 'timeSlots'])
            ->latest()
            ->limit($limit)
            ->get();

        return LearnServeOpportunityResource::collection($items)->resolve();
    }

    protected function community(int $limit): array
    {
        $volunteerTeamType = MasterChoice::query()
            ->notDeleted()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->where('value_en', 'Volunteer Team')
            ->first();

        $volunteerItems = VolunteerProfile::query()
            ->notDeleted()
            ->whereHas('user', fn ($q) => $q->where('is_deleted', false)->where('is_banned', false))
            ->with([
                'user.interests',
                'user.masterInterests.choiceType',
                'user.badge',
                'user.volunteerProfile.gender.choiceType',
                'user.emergencyContactRelationship.choiceType',
                'gender.choiceType',
                'currentBadge',
            ])
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        $orgQuery = OrganizationProfile::query()
            ->notDeleted()
            ->whereHas('user', fn ($q) => $q->where('is_deleted', false)->where('is_banned', false))
            ->with([
                'organizerType.choiceType',
                'sector.choiceType',
                'documents',
                'user.interests',
                'user.masterInterests.choiceType',
                'user.badge',
            ]);

        if ($volunteerTeamType) {
            $orgQuery->where('organizer_type_id', '!=', $volunteerTeamType->id);
        }

        $orgItems = $orgQuery->latest('updated_at')->limit($limit)->get();

        $teamQuery = OrganizationProfile::query()
            ->notDeleted()
            ->whereHas('user', fn ($q) => $q->where('is_deleted', false)->where('is_banned', false))
            ->with([
                'organizerType.choiceType',
                'sector.choiceType',
                'documents',
                'user.interests',
                'user.masterInterests.choiceType',
                'user.badge',
            ]);

        if ($volunteerTeamType) {
            $teamQuery->where('organizer_type_id', $volunteerTeamType->id);
        } else {
            $teamQuery->whereRaw('1 = 0');
        }

        $teamItems = $teamQuery->latest('updated_at')->limit($limit)->get();

        $serializeVolunteer = fn ($profile) => array_merge(
            (new VolunteerProfileWithUserResource($profile))->resolve(),
            ['user_details' => (new UserResource($profile->user))->resolve()]
        );

        $serializeOrg = fn ($profile) => array_merge(
            (new OrganizationProfileResource($profile))->resolve(),
            ['user_details' => (new UserResource($profile->user))->resolve()]
        );

        return [
            'volunteer' => $volunteerItems->map($serializeVolunteer)->values(),
            'organization' => $orgItems->map($serializeOrg)->values(),
            'volunteer_team' => $teamItems->map($serializeOrg)->values(),
        ];
    }

    protected function achievements(): array
    {
        $currentYear = (int) now()->format('Y');

        $individuals = VolunteerStatistic::query()
            ->whereNotNull('month')
            ->where('year', $currentYear)
            ->selectRaw('user_id, SUM(volunteer_hours) as total_hours, SUM(opportunities_organized) as total_organizing')
            ->groupBy('user_id')
            ->orderByDesc('total_hours')
            ->limit(10)
            ->get();

        $profiles = VolunteerProfile::query()
            ->whereIn('user_id', $individuals->pluck('user_id'))
            ->with(['user.badge', 'currentBadge'])
            ->get()
            ->keyBy('user_id');

        $individualsData = $individuals->map(function ($row) use ($profiles) {
            $profile = $profiles->get($row->user_id);
            $user = $profile?->user;
            $volHours = (int) ($row->total_hours ?? 0);
            $orgHours = (int) ($row->total_organizing ?? 0);
            $total = $volHours + $orgHours;
            if ($total <= 0) {
                return null;
            }

            return [
                'user_id' => $row->user_id,
                'name' => trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')),
                'nickname' => $profile?->nickname,
                'volunteer_hours' => $volHours,
                'organizing_hours' => $orgHours,
                'total_hours' => $total,
                'badge_info' => $this->badgeInfo($user),
            ];
        })->filter()->values();

        $teamType = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->where('value_en', 'Volunteer Team')
            ->first();

        $teamData = collect();
        if ($teamType) {
            $teams = OrganizationProfile::query()
                ->notDeleted()
                ->where('organizer_type_id', $teamType->id)
                ->where('organization_status', ApprovalStatus::APPROVED)
                ->with('user')
                ->get();

            $teamStats = OrganizationStatistic::query()
                ->whereNotNull('month')
                ->where('year', $currentYear)
                ->whereIn('user_id', $teams->pluck('user_id'))
                ->selectRaw('user_id, SUM(vol_opportunity_organized) as total_vol, SUM(learn_opportunity_organized) as total_learn')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

            $teamData = $teams->map(function ($team) use ($teamStats) {
                $stats = $teamStats->get($team->user_id);
                if (! $stats) {
                    return null;
                }
                $total = (int) (($stats->total_vol ?? 0) + ($stats->total_learn ?? 0));
                if ($total <= 0) {
                    return null;
                }

                return [
                    'organization_id' => $team->user_id,
                    'organization_name' => $team->company_name ?: trim(($team->user->first_name ?? '').' '.($team->user->last_name ?? '')),
                    'executed_opportunities' => $total,
                    'badge_info' => $this->badgeInfo($team->user),
                ];
            })->filter()->sortByDesc('executed_opportunities')->values()->take(10);
        }

        return [
            'individuals' => $individualsData,
            'teams' => $teamData,
            'cycle_info' => [
                'cycle_type' => 'monthly',
                'cycle_scope' => 'current',
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->endOfYear()->toDateString(),
            ],
        ];
    }

    protected function faqs(int $limit)
    {
        return Faq::query()
            ->notDeleted()
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (Faq $faq) => [
                'id' => $faq->id,
                'question_en' => $faq->question_en,
                'question_ar' => $faq->question_ar,
                'answer_en' => $faq->answer_en,
                'answer_ar' => $faq->answer_ar,
            ])
            ->values();
    }

    protected function badgeInfo(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $yearStats = VolunteerStatistic::query()
            ->where('user_id', $user->id)
            ->where('year', now()->year)
            ->whereNull('month')
            ->with('badge')
            ->first();

        if ($yearStats?->badge) {
            return ['id' => $yearStats->badge->id, 'name' => $yearStats->badge->name];
        }

        if ($user->badge) {
            return ['id' => $user->badge->id, 'name' => $user->badge->name];
        }

        return null;
    }
}
