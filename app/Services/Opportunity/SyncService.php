<?php

namespace App\Services\Opportunity;

use App\Enums\OpportunityStatus;
use App\Models\Badge;
use App\Models\LearnServeOpportunity;
use App\Models\OrganizationProfile;
use App\Models\OrganizationStatistic;
use App\Models\OpportunitySponsorImage;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityAttendance;
use App\Models\VolunteerProfile;
use App\Models\VolunteerStatistic;
use Illuminate\Support\Facades\Log;

/** Port of Django apps.base.sync_service.SyncService */
class SyncService
{
    public static function syncUser(int $userId): bool
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return false;
        }

        if ($user->isVolunteer()) {
            return self::syncVolunteer($userId);
        }

        if ($user->isOrganization()) {
            return self::syncOrganization($userId);
        }

        return false;
    }

    public static function getBadgeForHours(?float $totalHours): ?Badge
    {
        if ($totalHours === null || $totalHours < 0) {
            return null;
        }

        return Badge::query()
            ->notDeleted()
            ->where('min_hours', '<=', $totalHours)
            ->where(function ($q) use ($totalHours) {
                $q->whereNull('max_hours')->orWhere('max_hours', '>=', $totalHours);
            })
            ->orderBy('min_hours')
            ->first();
    }

    public static function syncVolunteer(int $userId): bool
    {
        try {
            $volunteer = VolunteerProfile::query()->where('user_id', $userId)->first();
            if (! $volunteer) {
                return false;
            }

            $user = $volunteer->user;
            $currentYear = (int) now()->year;
            $monthlyHours = [];

            $attendances = VolunteerOpportunityAttendance::query()
                ->where('is_attended', true)
                ->where('is_deleted', false)
                ->whereHas('registration', function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->where('is_deleted', false)
                        ->whereHas('opportunity', fn ($oq) => $oq->where('is_deleted', false));
                })
                ->with('registration.opportunity')
                ->get();

            foreach ($attendances as $att) {
                if (! $att->attended_date) {
                    continue;
                }
                $key = $att->attended_date->year.'-'.$att->attended_date->month;
                $monthlyHours[$key] = ($monthlyHours[$key] ?? 0) + (float) ($att->total_hours ?? 0);
            }

            $allMonthKeys = [];
            foreach ($monthlyHours as $key => $hours) {
                [$y, $m] = array_map('intval', explode('-', $key));
                $allMonthKeys[] = [$y, $m];

                $participated = VolunteerOpportunity::query()
                    ->where('is_deleted', false)
                    ->whereHas('registrations', function ($q) use ($user, $y, $m) {
                        $q->where('user_id', $user->id)
                            ->where('is_deleted', false)
                            ->whereHas('attendances', function ($aq) use ($y, $m) {
                                $aq->where('is_attended', true)
                                    ->where('is_deleted', false)
                                    ->whereYear('attended_date', $y)
                                    ->whereMonth('attended_date', $m);
                            });
                    })
                    ->distinct()
                    ->count('volunteer_opportunities.id');

                VolunteerStatistic::query()->updateOrCreate(
                    ['user_id' => $user->id, 'year' => $y, 'month' => $m],
                    [
                        'volunteer_hours' => $hours,
                        'opportunities_participated' => $participated,
                    ]
                );
            }

            $existingMonths = VolunteerStatistic::query()
                ->where('user_id', $user->id)
                ->whereNotNull('month')
                ->get(['year', 'month']);

            foreach ($existingMonths as $row) {
                $found = collect($allMonthKeys)->contains(fn ($k) => $k[0] === $row->year && $k[1] === $row->month);
                if (! $found) {
                    VolunteerStatistic::query()
                        ->where('user_id', $user->id)
                        ->where('year', $row->year)
                        ->where('month', $row->month)
                        ->update(['volunteer_hours' => 0, 'opportunities_participated' => 0]);
                }
            }

            $totalHoursAllTime = (float) VolunteerStatistic::query()
                ->where('user_id', $user->id)
                ->whereNotNull('month')
                ->sum('volunteer_hours');

            $currentYearHours = 0.0;
            foreach ($monthlyHours as $key => $hrs) {
                [$y] = array_map('intval', explode('-', $key));
                if ($y === $currentYear) {
                    $currentYearHours += $hrs;
                }
            }

            $strictTotalOpportunities = VolunteerOpportunity::query()
                ->where('is_deleted', false)
                ->whereHas('registrations', function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->where('is_deleted', false)
                        ->whereHas('attendances', fn ($aq) => $aq->where('is_attended', true)->where('is_deleted', false));
                })
                ->distinct()
                ->count('volunteer_opportunities.id');

            $badge = self::getBadgeForHours($currentYearHours);

            $volunteer->update([
                'current_year_hours' => $currentYearHours,
                'total_volunteer_hours' => $totalHoursAllTime,
                'total_opportunities' => $strictTotalOpportunities,
                'current_badge_id' => $badge?->id,
            ]);

            $involvedYears = collect($allMonthKeys)->pluck(0)
                ->merge(VolunteerStatistic::query()->where('user_id', $user->id)->pluck('year'))
                ->unique();

            foreach ($involvedYears as $y) {
                $agg = VolunteerStatistic::query()
                    ->where('user_id', $user->id)
                    ->where('year', $y)
                    ->whereNotNull('month')
                    ->selectRaw('SUM(volunteer_hours) as total_hours, SUM(opportunities_participated) as total_opps')
                    ->first();

                $yearBadge = self::getBadgeForHours((float) ($agg->total_hours ?? 0));

                VolunteerStatistic::query()->updateOrCreate(
                    ['user_id' => $user->id, 'year' => $y, 'month' => null],
                    [
                        'volunteer_hours' => (float) ($agg->total_hours ?? 0),
                        'opportunities_participated' => (int) ($agg->total_opps ?? 0),
                        'badge_id' => $yearBadge?->id,
                    ]
                );
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('SyncService.syncVolunteer failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public static function syncOrganization(int $userId): bool
    {
        try {
            $orgProfile = OrganizationProfile::query()->where('user_id', $userId)->first();
            if (! $orgProfile) {
                return false;
            }

            $user = $orgProfile->user;
            $currentYear = (int) now()->year;
            $volCounts = [];
            $learnCounts = [];
            $orgHours = [];
            $sponsoredCounts = [];

            $volOpps = VolunteerOpportunity::query()
                ->where('created_by', $user->id)
                ->whereNotNull('end_date')
                ->where('opportunity_status', OpportunityStatus::COMPLETED)
                ->where('is_deleted', false)
                ->whereHas('registrations.attendances', fn ($q) => $q->where('is_attended', true)->where('is_deleted', false))
                ->get();

            foreach ($volOpps as $opp) {
                $y = $opp->end_date->year;
                $m = $opp->end_date->month;
                $volCounts["{$y}-{$m}"] = ($volCounts["{$y}-{$m}"] ?? 0) + 1;
            }

            $allVolOpps = VolunteerOpportunity::query()
                ->where('created_by', $user->id)
                ->whereNotNull('end_date')
                ->where('is_deleted', false)
                ->get();

            foreach ($allVolOpps as $opp) {
                $y = $opp->end_date->year;
                $m = $opp->end_date->month;
                $hours = (float) VolunteerOpportunityAttendance::query()
                    ->where('is_attended', true)
                    ->where('is_deleted', false)
                    ->whereHas('registration', fn ($q) => $q
                        ->where('opportunity_id', $opp->id)
                        ->where('is_deleted', false))
                    ->sum('total_hours');
                $orgHours["{$y}-{$m}"] = ($orgHours["{$y}-{$m}"] ?? 0) + $hours;
            }

            $learnOpps = LearnServeOpportunity::query()
                ->where('created_by', operator: $user->id)
                ->whereNotNull('end_date')
                ->where('opportunity_status', OpportunityStatus::COMPLETED)
                ->where('is_deleted', false)
                ->whereHas('registrations', fn ($q) => $q->where('is_attended', true)->where('is_deleted', false))
                ->whereHas('learningType', fn ($q) => $q->whereIn('value_en', ['Course', 'Internship']))
                ->get();

            foreach ($learnOpps as $opp) {
                $y = $opp->end_date->year;
                $m = $opp->end_date->month;
                $learnCounts["{$y}-{$m}"] = ($learnCounts["{$y}-{$m}"] ?? 0) + 1;
            }

            $sponsoredImages = OpportunitySponsorImage::query()
                ->where('organization_id', $orgProfile->id)
                ->where('is_deleted', false)
                ->with(['volunteerOpportunity', 'learnServeOpportunity'])
                ->get();

            foreach ($sponsoredImages as $img) {
                $opp = $img->volunteerOpportunity ?? $img->learnServeOpportunity;
                if ($opp && ! $opp->is_deleted && $opp->end_date && $opp->opportunity_status === OpportunityStatus::COMPLETED) {
                    $y = $opp->end_date->year;
                    $m = $opp->end_date->month;
                    $sponsoredCounts["{$y}-{$m}"] = ($sponsoredCounts["{$y}-{$m}"] ?? 0) + 1;
                }
            }

            $allKeys = collect(array_keys($volCounts))
                ->merge(array_keys($learnCounts))
                ->merge(array_keys($orgHours))
                ->merge(array_keys($sponsoredCounts))
                ->unique();

            foreach ($allKeys as $key) {
                [$y, $m] = array_map('intval', explode('-', $key));
                OrganizationStatistic::query()->updateOrCreate(
                    ['user_id' => $user->id, 'year' => $y, 'month' => $m],
                    [
                        'vol_opportunity_organized' => $volCounts[$key] ?? 0,
                        'learn_opportunity_organized' => $learnCounts[$key] ?? 0,
                        'organization_hours' => $orgHours[$key] ?? 0,
                        'sponsored' => $sponsoredCounts[$key] ?? 0,
                    ]
                );
            }

            $involvedYears = $allKeys->map(fn ($k) => (int) explode('-', $k)[0])
                ->merge(OrganizationStatistic::query()->where('user_id', $user->id)->pluck('year'))
                ->unique();

            foreach ($involvedYears as $y) {
                $agg = OrganizationStatistic::query()
                    ->where('user_id', $user->id)
                    ->where('year', $y)
                    ->whereNotNull('month')
                    ->selectRaw('
                        SUM(vol_opportunity_organized) as total_vol,
                        SUM(learn_opportunity_organized) as total_learn,
                        SUM(organization_hours) as total_hours,
                        SUM(sponsored) as total_sponsored
                    ')
                    ->first();

                $volCount = (float) ($agg->total_vol ?? 0);
                $lsCount = (float) ($agg->total_learn ?? 0);
                $sponsored = (float) ($agg->total_sponsored ?? 0);
                $totalImpact = $volCount + $lsCount + $sponsored;
                $yearBadge = self::getBadgeForHours($totalImpact);

                OrganizationStatistic::query()->updateOrCreate(
                    ['user_id' => $user->id, 'year' => $y, 'month' => null],
                    [
                        'vol_opportunity_organized' => $volCount,
                        'learn_opportunity_organized' => $lsCount,
                        'organization_hours' => (float) ($agg->total_hours ?? 0),
                        'sponsored' => $sponsored,
                        'badge_id' => $yearBadge?->id,
                    ]
                );

                if ((int) $y === $currentYear && $yearBadge) {
                    $user->update(['badge_id' => $yearBadge->id]);
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('SyncService.syncOrganization failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
