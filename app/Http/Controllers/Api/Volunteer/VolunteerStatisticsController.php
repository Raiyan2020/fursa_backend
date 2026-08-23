<?php

namespace App\Http\Controllers\Api\Volunteer;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\VolunteerCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\Volunteer\VolunteerProfileWithUserResource;
use App\Models\Config;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\MasterChoice;
use App\Models\OrganizationProfile;
use App\Models\OrganizationStatistic;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use App\Models\VolunteerProfile;
use App\Models\VolunteerStatistic;
use App\Support\ApiResponse;
use App\Support\RankingCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VolunteerStatisticsController extends Controller
{
    public function statistics(): JsonResponse
    {
        $currentYear = (int) now()->format('Y');
        $years = range($currentYear, $currentYear + 6);

        $yearlyTotals = VolunteerStatistic::query()
            ->whereNotNull('month')
            ->selectRaw('year, SUM(volunteer_hours) as total_hours')
            ->groupBy('year')
            ->pluck('total_hours', 'year');

        $yearList = array_map(fn (int $year) => [
            'year' => $year,
            'total_hours' => (float) ($yearlyTotals[$year] ?? 0),
        ], $years);

        $grandTotal = (int) VolunteerStatistic::query()
            ->whereNotNull('month')
            ->sum('volunteer_hours');

        $volunteerCompleted = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('opportunity_status', OpportunityStatus::COMPLETED)
            ->whereHas('registrations.attendances', fn ($q) => $q->where('is_attended', true)->where('is_deleted', false))
            ->distinct()
            ->count();

        $learnCompleted = LearnServeOpportunity::query()
            ->notDeleted()
            ->where('opportunity_status', OpportunityStatus::COMPLETED)
            ->whereHas('registrations', fn ($q) => $q->where('is_attended', true)->where('is_deleted', false))
            ->count();

        $reliefTrips = VolunteerOpportunity::query()->notDeleted()->where('is_relief', true)->count();
        $rate = (float) (Config::query()->value('economic_impact_rate_kwd') ?: 6);
        $economicImpact = round($grandTotal * $rate, 2);

        // Beneficiaries = the figure publishers enter on charity volunteer
        // opportunities, plus everyone who actually attended a development
        // course (the client counts learners, not registrations).
        $volunteerBeneficiaries = (int) VolunteerOpportunity::query()
            ->notDeleted()
            ->where('volunteer_category', VolunteerCategory::CHARITY->value)
            ->sum('beneficiaries_count');

        $courseLearners = (int) LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->where('is_attended', true)
            ->whereHas('opportunity', fn ($q) => $q->notDeleted())
            ->count();

        $totalBeneficiaries = $volunteerBeneficiaries + $courseLearners;

        return ApiResponse::success([
            'yearly_hours' => $yearList,
            'grand_total_hours' => $grandTotal,
            'volunteer_opportunities_completed' => $volunteerCompleted,
            'learn_serve_opportunities_completed' => $learnCompleted,
            'development_opportunities_completed' => $learnCompleted,
            'relief_trips' => $reliefTrips,
            'outside_kuwait_trips' => $reliefTrips,
            'economic_impact_kwd' => $economicImpact,
            'economic_impact_rate_kwd' => $rate,
            'beneficiaries_count' => $totalBeneficiaries,
            'beneficiaries_breakdown' => [
                'volunteer_opportunities' => $volunteerBeneficiaries,
                'course_learners' => $courseLearners,
            ],
        ], 'Yearly volunteer hours summary retrieved successfully.', 'تم استرجاع ملخص ساعات التطوع السنوي بنجاح.');
    }

    public function topVolunteers(Request $request): JsonResponse
    {
        $cycle = RankingCycle::current();
        $config = Config::query()->first();

        $individuals = VolunteerStatistic::query()
            ->whereNotNull('month')
            ->where('year', $cycle['start']->year)
            ->whereBetween('month', [$cycle['start']->month, $cycle['end']->month])
            ->selectRaw('user_id, SUM(volunteer_hours) as volunteer_hours, SUM(opportunities_organized) as organizing_hours')
            ->groupBy('user_id')
            ->orderByDesc('volunteer_hours')
            ->limit(10)
            ->get();

        $profiles = VolunteerProfile::query()
            ->whereIn('user_id', $individuals->pluck('user_id'))
            ->with(['user.badge', 'currentBadge', 'gender.choiceType'])
            ->get()
            ->keyBy('user_id');

        $topIndividuals = $individuals->map(function ($row) use ($profiles) {
            $profile = $profiles->get($row->user_id);
            $user = $profile?->user;
            $volunteerHours = (float) ($row->volunteer_hours ?? 0);
            $organizingHours = (int) ($row->organizing_hours ?? 0);
            $total = (int) round($volunteerHours + $organizingHours);
            if ($total <= 0) {
                return null;
            }

            return [
                'user_id' => $row->user_id,
                'name' => trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')),
                'nickname' => $profile?->nickname,
                'volunteer_hours' => $volunteerHours,
                'organizing_hours' => $organizingHours,
                'profile_pic' => $this->userProfilePic($user),
                'total_hours' => $total,
                'gender_display' => $this->genderDisplay($profile?->gender),
                'is_public' => (bool) ($profile?->is_public ?? false),
                'user_type' => $user?->user_type?->value ?? $user?->user_type ?? 'volunteer',
                'badge_info' => $this->badgeInfo($user),
            ];
        })->filter()->values();

        $teamType = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->where('value_en', 'Volunteer Team')
            ->first();

        $orgQuery = OrganizationProfile::query()
            ->notDeleted()
            ->where('organization_status', ApprovalStatus::APPROVED)
            ->with(['user.badge']);

        $teams = $teamType
            ? (clone $orgQuery)->where('organizer_type_id', $teamType->id)->get()
            : collect();
        $companies = $teamType
            ? (clone $orgQuery)->where('organizer_type_id', '!=', $teamType->id)->get()
            : (clone $orgQuery)->get();

        $cycleStats = OrganizationStatistic::query()
            ->whereNotNull('month')
            ->where('year', $cycle['start']->year)
            ->whereBetween('month', [$cycle['start']->month, $cycle['end']->month])
            ->selectRaw('user_id, SUM(vol_opportunity_organized) as total_vol, SUM(learn_opportunity_organized) as total_learn, SUM(sponsored) as total_sponsored')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $topVolunteerTeams = $teams->map(function (OrganizationProfile $team) use ($cycleStats) {
            $stats = $cycleStats->get($team->user_id);
            $executed = (int) (($stats->total_vol ?? 0) + ($stats->total_learn ?? 0));
            if ($executed <= 0) {
                return null;
            }

            return [
                'organization_id' => $team->user_id,
                'organization_name' => $team->company_name ?: trim(($team->user?->first_name ?? '').' '.($team->user?->last_name ?? '')),
                'executed_opportunities' => $executed,
                'profile_pic' => $this->userProfilePic($team->user),
                'user_type' => $team->user?->user_type?->value ?? $team->user?->user_type ?? 'organization',
                'badge_info' => $this->badgeInfo($team->user),
            ];
        })->filter()->sortByDesc('executed_opportunities')->values()->take(10);

        $topCompanies = $companies->map(function (OrganizationProfile $org) use ($cycleStats) {
            $stats = $cycleStats->get($org->user_id);
            $sponsored = (int) ($stats->total_sponsored ?? 0);
            if ($sponsored <= 0) {
                return null;
            }

            return [
                'organization_id' => $org->user_id,
                'organization_name' => $org->company_name ?: trim(($org->user?->first_name ?? '').' '.($org->user?->last_name ?? '')),
                'sponsored_count' => $sponsored,
                'profile_pic' => $this->userProfilePic($org->user),
                'user_type' => $org->user?->user_type?->value ?? $org->user?->user_type ?? 'organization',
                'badge_info' => $this->badgeInfo($org->user),
            ];
        })->filter()->sortByDesc('sponsored_count')->values()->take(10);

        return ApiResponse::success([
            'top_individuals' => $topIndividuals,
            'top_volunteer_teams' => $topVolunteerTeams,
            'top_companies_and_government' => $topCompanies,
            'cycle_type' => $cycle['type'],
            'cycle_scope' => $config?->cycle_scope ?: 'current',
            'start_date' => $cycle['start']->toDateString(),
            'end_date' => $cycle['end']->toDateString(),
        ], 'Top statistics retrieved successfully.', 'تم استرجاع أفضل الإحصائيات بنجاح.');
    }

    public function availableVolunteers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunity_id' => ['required', 'integer'],
            'type' => ['required', 'in:volunteer,learnserve'],
            'search' => ['nullable', 'string'],
        ]);

        $query = VolunteerProfile::query()
            ->notDeleted()
            ->where('is_verified', true)
            ->whereHas('user', fn ($q) => $q->where('is_banned', false)->where('is_deleted', false))
            ->with(['user', 'gender.choiceType', 'currentBadge']);

        if ($data['type'] === 'volunteer') {
            $opportunity = VolunteerOpportunity::query()->notDeleted()->find($data['opportunity_id']);
            if (! $opportunity) {
                return ApiResponse::error('Opportunity not found.', 'الفرصة غير موجودة.', 404);
            }
            if ($opportunity->created_by !== $request->user()->id) {
                return ApiResponse::error(
                    'You are not authorized to view volunteers for this opportunity.',
                    'غير مصرح لك بعرض المتطوعين لهذه الفرصة.',
                    403
                );
            }

            $registeredIds = VolunteerOpportunityRegistration::query()
                ->notDeleted()
                ->where('opportunity_id', $opportunity->id)
                ->pluck('user_id');

            $conflictingIds = VolunteerOpportunityRegistration::query()
                ->notDeleted()
                ->where('opportunity_id', '!=', $opportunity->id)
                ->whereHas('opportunity', function ($q) use ($opportunity) {
                    $q->where('start_date', '<', $opportunity->end_date)
                        ->where('end_date', '>', $opportunity->start_date);
                })
                ->pluck('user_id');

            $query->whereNotIn('user_id', $registeredIds)
                ->whereNotIn('user_id', $conflictingIds)
                ->where('user_id', '!=', $request->user()->id);
        } else {
            $opportunity = LearnServeOpportunity::query()->notDeleted()->find($data['opportunity_id']);
            if (! $opportunity) {
                return ApiResponse::error('Opportunity not found.', 'الفرصة غير موجودة.', 404);
            }
            if ($opportunity->created_by !== $request->user()->id) {
                return ApiResponse::error(
                    'You are not authorized to view volunteers for this opportunity.',
                    'غير مصرح لك بعرض المتطوعين لهذه الفرصة.',
                    403
                );
            }

            $registeredIds = LearnServeOpportunityRegistration::query()
                ->notDeleted()
                ->where('opportunity_id', $opportunity->id)
                ->pluck('user_id');

            $query->whereNotIn('user_id', $registeredIds);
        }

        if (! empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nickname', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('civil_id', 'like', "%{$search}%");
                    });
            });
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        return ApiResponse::paginated(
            $paginator,
            VolunteerProfileWithUserResource::collection($paginator->getCollection()),
            'Available volunteers retrieved successfully.',
            'تم استرجاع المتطوعين المتاحين بنجاح.'
        );
    }

    public function userCertificates(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer']]);

        $profile = VolunteerProfile::query()->notDeleted()->where('user_id', $data['user_id'])->first();
        if (! $profile) {
            return ApiResponse::error('User not found or profile is deleted.', 'لم يتم العثور على المستخدم أو تم حذف الملف الشخصي.', 404);
        }

        $certificates = LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->where('user_id', $profile->user_id)
            ->where(function ($q) {
                $q->where('is_certified', true)
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('certificate_image')
                            ->where('certificate_image', '!=', '');
                    });
            })
            ->with('opportunity')
            ->get()
            ->map(fn ($row) => [
                'registration_id' => $row->id,
                'certificate_image' => getimg($row->certificate_image),
                'opportunity__title_en' => $row->opportunity?->title_en,
                'opportunity__title_ar' => $row->opportunity?->title_ar,
            ]);

        return ApiResponse::success($certificates, 'Certificates retrieved successfully.', 'تم استرجاع الشهادات بنجاح.');
    }

    public function volunteerDetail(Request $request): JsonResponse
    {
        $profile = $request->user()->volunteerProfile;
        if (! $profile) {
            return ApiResponse::error(
                'Only volunteers can access this endpoint.',
                'يمكن للمتطوعين فقط الوصول إلى هذه النقطة.',
                403
            );
        }

        if ($request->query('download') === 'true') {
            return ApiResponse::success([
                'pdf_url' => null,
                'message' => 'PDF generation is not yet implemented.',
            ], 'PDF generation stub.', 'إنشاء PDF غير متوفر بعد.');
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));

        $coursesQuery = LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->where('user_id', $request->user()->id)
            ->where(function ($q) {
                $q->where('is_certified', true)
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('certificate_image')
                            ->where('certificate_image', '!=', '');
                    });
            })
            ->with('opportunity')
            ->latest();

        $total = (clone $coursesQuery)->count();
        $courses = $coursesQuery
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->map(fn ($registration) => [
                'title_en' => $registration->opportunity?->title_en,
                'title_ar' => $registration->opportunity?->title_ar,
                'year' => optional($registration->registration_date)?->format('Y'),
            ])
            ->values();

        return ApiResponse::success([
            'total_volunteer_hours' => $profile->total_volunteer_hours,
            'total_opportunities' => $profile->total_opportunities,
            'total_certificates' => $profile->total_certificates,
            'civil_id' => $request->user()->civil_id,
            'full_name' => trim(($request->user()->first_name ?? '').' '.($request->user()->last_name ?? '')),
            'opportunities' => [
                'data' => $courses,
                'meta' => [
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
                    ],
                ],
            ],
        ], 'Volunteer details retrieved successfully.', 'تم استرجاع تفاصيل المتطوع بنجاح.');
    }

    public function downloadQrCode(Request $request): StreamedResponse|JsonResponse
    {
        $data = $request->validate(['volunteer_id' => ['required', 'integer']]);

        $profile = VolunteerProfile::query()->find($data['volunteer_id']);
        if (! $profile || ! $profile->qr_code) {
            return ApiResponse::error('Volunteer profile or QR code not found.', 'ملف المتطوع أو رمز QR غير موجود.', 404);
        }

        if (! Storage::disk('public')->exists($profile->qr_code)) {
            return ApiResponse::error('QR code file not found.', 'ملف رمز QR غير موجود.', 404);
        }

        $filename = basename($profile->qr_code);

        return Storage::disk('public')->download($profile->qr_code, $filename);
    }

    public function syncStatistics(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = (int) now()->format('Y');
        $month = (int) now()->format('n');

        $hours = VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->where('user_id', $user->id)
            ->whereHas('attendances', fn ($q) => $q->where('is_attended', true)->where('is_deleted', false))
            ->count() * 2;

        VolunteerStatistic::query()->updateOrCreate(
            ['user_id' => $user->id, 'year' => $year, 'month' => $month],
            [
                'volunteer_hours' => $hours,
                'opportunities_participated' => VolunteerOpportunityRegistration::query()
                    ->notDeleted()->where('user_id', $user->id)->count(),
            ]
        );

        if ($profile = $user->volunteerProfile) {
            $profile->update([
                'total_volunteer_hours' => VolunteerStatistic::query()
                    ->where('user_id', $user->id)->whereNotNull('month')->sum('volunteer_hours'),
                'current_year_hours' => VolunteerStatistic::query()
                    ->where('user_id', $user->id)->where('year', $year)->whereNotNull('month')->sum('volunteer_hours'),
            ]);
        }

        return ApiResponse::success(null, 'Statistics sync triggered successfully.', 'تم تفعيل مزامنة الإحصائيات بنجاح.');
    }

    protected function badgeInfo($user): ?array
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

    protected function userProfilePic($user): ?string
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

    protected function genderDisplay($gender): ?array
    {
        if (! $gender) {
            return null;
        }

        $gender->loadMissing('choiceType');

        return [
            'id' => $gender->id,
            'choice_type' => $gender->choiceType?->name,
            'value_en' => $gender->value_en,
            'value_ar' => $gender->value_ar,
        ];
    }
}
