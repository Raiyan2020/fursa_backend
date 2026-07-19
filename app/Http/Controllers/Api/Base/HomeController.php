<?php

namespace App\Http\Controllers\Api\Base;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\BannerImage;
use App\Models\Event;
use App\Models\HomeSection;
use App\Models\LearnServeOpportunity;
use App\Models\MasterChoice;
use App\Models\OrganizationProfile;
use App\Models\Page;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Models\Sponsor;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerStatistic;
use App\Models\WhyFursaItem;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Public landing-page aggregate — GET home/
 * Only homepage card/section keys (no auth, no pagination).
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query('limit', 10)));

        return ApiResponse::success(
            [
                'hero' => $this->hero(),
                'statistics' => $this->statistics(),
                'sponsors' => $this->sponsors(),
                'why_fursa' => $this->whyFursa(),
                'opportunities' => $this->opportunities($limit),
                'community' => $this->community($limit),
                'learn_share' => $this->learnShare($limit),
                'share_idea' => $this->section('share_idea'),
                'events' => $this->events($limit),
                'achievements' => $this->achievements(),
                'footer' => $this->footer(),
            ],
            'Home content retrieved successfully.',
            'تم استرجاع محتوى الصفحة الرئيسية بنجاح.'
        );
    }

    protected function hero(): array
    {
        $section = $this->section('hero');

        return [
            'title_en' => $section['title_en'] ?? null,
            'title_ar' => $section['title_ar'] ?? null,
            'banners' => BannerImage::query()->notDeleted()->latest()->get()->map(fn (BannerImage $b) => [
                'id' => $b->id,
                'image' => $this->mediaUrl($b->image),
                'banner_url' => $b->banner_url,
            ])->values(),
        ];
    }

    protected function statistics(): array
    {
        $volunteerTeamType = $this->volunteerTeamType();

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
        return Sponsor::query()
            ->notDeleted()
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->latest()
            ->get()
            ->map(fn (Sponsor $s) => [
                'id' => $s->id,
                'name' => $s->org_name ?: $s->person_name,
                'logo' => $s->sponsor_logo ? getimg($s->sponsor_logo) : null,
            ])
            ->values();
    }

    protected function whyFursa()
    {
        return WhyFursaItem::query()
            ->notDeleted()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (WhyFursaItem $item) => [
                'id' => $item->id,
                'title_en' => $item->title_en,
                'title_ar' => $item->title_ar,
                'icon' => $this->mediaUrl($item->icon),
            ])
            ->values();
    }

    protected function opportunities(int $limit)
    {
        return VolunteerOpportunity::query()
            ->notDeleted()
            ->where('is_public', true)
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->with(['images', 'interests'])
            ->withCount(['registrations' => fn ($q) => $q->notDeleted()])
            ->orderByDesc('is_urgent')
            ->orderBy('start_date')
            ->limit($limit)
            ->get()
            ->map(function (VolunteerOpportunity $item) {
                $registered = (int) ($item->registrations_count ?? 0);
                $needed = (int) ($item->participants_needed ?? 0);
                $image = $item->images?->first(fn ($img) => ! $img->is_deleted);

                return [
                    'id' => $item->id,
                    'title_en' => $item->title_en,
                    'title_ar' => $item->title_ar,
                    'image' => $image?->image ? getimg($image->image) : null,
                    'is_urgent' => (bool) $item->is_urgent,
                    'start_date' => optional($item->start_date)?->format('Y-m-d'),
                    'end_date' => optional($item->end_date)?->format('Y-m-d'),
                    'start_time' => $item->start_time,
                    'end_time' => $item->end_time,
                    'from_age' => $item->from_age,
                    'to_age' => $item->to_age,
                    'location_en' => $item->location_en,
                    'location_ar' => $item->location_ar,
                    'registered_count' => $registered,
                    'participants_needed' => $needed,
                    'tags' => $item->interests?->map(fn ($i) => [
                        'id' => $i->id,
                        'name_en' => $i->name_en,
                        'name_ar' => $i->name_ar,
                    ])->values() ?? [],
                    'status' => $this->cardStatus($item->opportunity_status, $registered, $needed),
                ];
            })
            ->values();
    }

    protected function community(int $limit)
    {
        return Post::query()
            ->notDeleted()
            ->where('is_displayed', true)
            ->with(['user.organizationProfile', 'user.volunteerProfile'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (Post $post) {
                $user = $post->user;

                return [
                    'id' => $post->id,
                    'user_id' => $user?->id,
                    'name' => $this->displayName($user),
                    'image' => $this->profileImage($user),
                    'text_en' => $post->idea_text_en ?: $post->title_en,
                    'text_ar' => $post->idea_text_ar ?: $post->title_ar,
                ];
            })
            ->values();
    }

    protected function learnShare(int $limit)
    {
        return LearnServeOpportunity::query()
            ->notDeleted()
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->with(['images', 'interests', 'format', 'learningType'])
            ->withCount(['registrations' => fn ($q) => $q->notDeleted()])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (LearnServeOpportunity $item) {
                $registered = (int) ($item->registrations_count ?? 0);
                $needed = (int) ($item->participants_needed ?? 0);
                $image = $item->images?->first(fn ($img) => ! $img->is_deleted);

                return [
                    'id' => $item->id,
                    'title_en' => $item->title_en,
                    'title_ar' => $item->title_ar,
                    'image' => $image?->image ? getimg($image->image) : null,
                    'start_date' => optional($item->start_date)?->format('Y-m-d'),
                    'end_date' => optional($item->end_date)?->format('Y-m-d'),
                    'start_time' => $item->start_time,
                    'end_time' => $item->end_time,
                    'type_en' => $item->learningType?->value_en,
                    'type_ar' => $item->learningType?->value_ar,
                    'format_en' => $item->format?->value_en,
                    'format_ar' => $item->format?->value_ar,
                    'registered_count' => $registered,
                    'participants_needed' => $needed,
                    'tags' => $item->interests?->map(fn ($i) => [
                        'id' => $i->id,
                        'name_en' => $i->name_en,
                        'name_ar' => $i->name_ar,
                    ])->values() ?? [],
                    'status' => $this->cardStatus($item->opportunity_status, $registered, $needed),
                ];
            })
            ->values();
    }

    protected function events(int $limit)
    {
        return Event::query()
            ->notDeleted()
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->with(['images', 'interests', 'eventType'])
            ->latest('start_date')
            ->limit($limit)
            ->get()
            ->map(function (Event $event) {
                $image = $event->images?->first(fn ($img) => ! ($img->is_deleted ?? false));

                return [
                    'id' => $event->id,
                    'title_en' => $event->title_en,
                    'title_ar' => $event->title_ar,
                    'image' => $image?->image ? getimg($image->image) : null,
                    'is_free' => ! (bool) $event->paid_registration,
                    'start_date' => optional($event->start_date)?->format('Y-m-d'),
                    'end_date' => optional($event->end_date)?->format('Y-m-d'),
                    'start_time' => $event->start_time,
                    'end_time' => $event->end_time,
                    'view_count' => (int) $event->view_count,
                    'event_type_en' => $event->eventType?->value_en,
                    'event_type_ar' => $event->eventType?->value_ar,
                    'location_en' => $event->location_en,
                    'location_ar' => $event->location_ar,
                    'tags' => $event->interests?->map(fn ($i) => [
                        'id' => $i->id,
                        'name_en' => $i->name_en,
                        'name_ar' => $i->name_ar,
                    ])->values() ?? [],
                ];
            })
            ->values();
    }

    protected function achievements(): array
    {
        $currentYear = (int) now()->format('Y');

        $rows = VolunteerStatistic::query()
            ->whereNotNull('month')
            ->where('year', $currentYear)
            ->selectRaw('user_id, SUM(volunteer_hours) as total_hours, SUM(opportunities_organized) as total_organizing')
            ->groupBy('user_id')
            ->orderByDesc('total_hours')
            ->limit(10)
            ->get();

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id'))
            ->with(['volunteerProfile', 'badge'])
            ->get()
            ->keyBy('id');

        $individuals = $rows->map(function ($row) use ($users) {
            $user = $users->get($row->user_id);
            $total = (int) ($row->total_hours ?? 0) + (int) ($row->total_organizing ?? 0);
            if ($total <= 0 || ! $user) {
                return null;
            }

            return [
                'user_id' => $user->id,
                'name' => $this->displayName($user),
                'image' => $this->profileImage($user),
                'total_hours' => $total,
                'badge' => $user->badge ? [
                    'id' => $user->badge->id,
                    'name' => $user->badge->name,
                ] : null,
            ];
        })->filter()->values();

        return [
            'individuals' => $individuals,
        ];
    }

    protected function footer(): array
    {
        $settings = SiteSetting::current();

        return [
            'pages' => Page::query()->notDeleted()->orderBy('id')->get()->map(fn (Page $p) => [
                'slug' => $p->slug,
                'title_en' => $p->title_en,
                'title_ar' => $p->title_ar,
            ])->values(),
            'contact_email' => $settings->contact_email,
            'social' => [
                'tiktok' => $settings->tiktok_url,
                'twitter' => $settings->twitter_url,
                'youtube' => $settings->youtube_url,
                'instagram' => $settings->instagram_url,
            ],
            'copyright_en' => $settings->copyright_en,
            'copyright_ar' => $settings->copyright_ar,
        ];
    }

    protected function section(string $slug): ?array
    {
        $section = HomeSection::query()->notDeleted()->where('slug', $slug)->first();
        if (! $section) {
            return null;
        }

        return [
            'slug' => $section->slug,
            'title_en' => $section->title_en,
            'title_ar' => $section->title_ar,
            'description_en' => $section->description_en,
            'description_ar' => $section->description_ar,
            'image' => $this->mediaUrl($section->image),
        ];
    }

    protected function cardStatus(mixed $status, int $registered, int $needed): string
    {
        $value = $status instanceof OpportunityStatus ? $status->value : (string) $status;

        if (in_array($value, [OpportunityStatus::COMPLETED->value, OpportunityStatus::CANCELLED->value], true)) {
            return 'closed';
        }

        if ($needed > 0 && $registered >= $needed) {
            return 'full';
        }

        return 'open';
    }

    protected function volunteerTeamType(): ?MasterChoice
    {
        return MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->where('value_en', 'Volunteer Team')
            ->first();
    }

    protected function displayName(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $orgName = $user->organizationProfile?->company_name
            ?: $user->organizationProfile?->nickname;
        if ($orgName) {
            return $orgName;
        }

        $full = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        return $full !== '' ? $full : ($user->username ?: $user->volunteerProfile?->nickname);
    }

    protected function profileImage(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->profile_pic) {
            return getimg($user->profile_pic);
        }

        return $user->social_profile_pic_url;
    }

    protected function mediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return function_exists('getimg') ? getimg($path) : Storage::disk('public')->url($path);
    }
}
