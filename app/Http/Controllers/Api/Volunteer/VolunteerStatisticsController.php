<?php

namespace App\Http\Controllers\Api\Volunteer;

use App\Enums\OpportunityStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Volunteer\VolunteerProfileWithUserResource;
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

        return ApiResponse::success([
            'results' => $yearList,
            'yearly_hours' => $yearList,
            'grand_total_hours' => $grandTotal,
            'volunteer_opportunities_completed' => $volunteerCompleted,
            'learn_serve_opportunities_completed' => $learnCompleted,
            'relief_trips' => $reliefTrips,
        ], 'Yearly volunteer hours summary retrieved successfully.', 'تم استرجاع ملخص ساعات التطوع السنوي بنجاح.');
    }

    public function topVolunteers(Request $request): JsonResponse
    {
        $currentYear = (int) now()->format('Y');

        $individuals = VolunteerStatistic::query()
            ->whereNotNull('month')
            ->where('year', $currentYear)
            ->selectRaw('user_id, SUM(volunteer_hours) as total_hours, SUM(opportunities_organized) as total_organizing, MIN(created_at) as earliest_created')
            ->groupBy('user_id')
            ->orderByDesc('total_hours')
            ->limit(10)
            ->get();

        $userIds = $individuals->pluck('user_id');
        $profiles = VolunteerProfile::query()
            ->whereIn('user_id', $userIds)
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
                ->where('organization_status', 'approved')
                ->with('user')
                ->get();

            $teamStats = OrganizationStatistic::query()
                ->whereNotNull('month')
                ->where('year', $currentYear)
                ->whereIn('user_id', $teams->pluck('user_id'))
                ->selectRaw('user_id, SUM(vol_opportunity_organized) as total_vol, SUM(learn_opportunity_organized) as total_learn, MIN(created_at) as earliest')
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

        return ApiResponse::success([
            'individuals' => $individualsData,
            'teams' => $teamData,
            'cycle_info' => [
                'cycle_type' => 'monthly',
                'cycle_scope' => 'current',
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->endOfYear()->toDateString(),
            ],
        ], 'Top volunteers retrieved successfully.', 'تم استرجاع أفضل المتطوعين بنجاح.');
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
            ->where('is_certified', true)
            ->whereNotNull('certificate_image')
            ->where('certificate_image', '!=', '')
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

        $stats = VolunteerStatistic::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('month')
            ->where('year', now()->year)
            ->first();

        $registrations = VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->where('user_id', $request->user()->id)
            ->with('opportunity')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($reg) => [
                'id' => $reg->id,
                'title_en' => $reg->opportunity?->title_en,
                'title_ar' => $reg->opportunity?->title_ar,
                'registration_date' => $reg->registration_date?->toIso8601String(),
                'status' => $reg->status?->value,
            ]);

        return ApiResponse::success([
            'profile' => [
                'id' => $profile->id,
                'nickname' => $profile->nickname,
                'total_volunteer_hours' => $profile->total_volunteer_hours,
                'total_opportunities' => $profile->total_opportunities,
                'total_certificates' => $profile->total_certificates,
                'opportunities_organized' => $profile->opportunities_organized,
                'current_rank' => $profile->current_rank,
                'current_year_hours' => $profile->current_year_hours,
            ],
            'year_statistics' => $stats,
            'recent_registrations' => $registrations,
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
}
