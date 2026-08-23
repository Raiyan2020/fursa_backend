<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Enums\VolunteerCategory;
use App\Http\Controllers\Controller;
use App\Models\Config;
use App\Models\Event;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\OrganizationProfile;
use App\Models\Sponsor;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use App\Models\VolunteerProfile;
use App\Models\VolunteerStatistic;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Spreadsheet export of the platform's headline numbers, so the client can pull
 * figures out of the dashboard instead of asking for them.
 */
class StatisticsExportController extends Controller
{
    public function export(): StreamedResponse
    {
        $sections = [
            __('users') => $this->userRows(),
            __('opportunities') => $this->opportunityRows(),
            __('volunteer hours') => $this->hoursRows(),
            __('yearly volunteer hours') => $this->yearlyRows(),
        ];

        $filename = 'statistics_'.now()->format('Y-m-d_His').'.xls';

        return response()->streamDownload(function () use ($sections) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';

            foreach ($sections as $title => $rows) {
                echo '<h3>'.e($title).'</h3>';
                echo '<table border="1">';
                echo '<tr><th>'.e(__('metric')).'</th><th>'.e(__('value')).'</th></tr>';

                foreach ($rows as $label => $value) {
                    echo '<tr><td>'.e($label).'</td><td>'.e($value).'</td></tr>';
                }

                echo '</table><br>';
            }

            echo '</body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, int|string>
     */
    protected function userRows(): array
    {
        return [
            __('all users') => User::query()->notDeleted()->count(),
            __('volunteers') => VolunteerProfile::query()->notDeleted()->count(),
            __('entities') => OrganizationProfile::query()->notDeleted()->count(),
            __('sponsors') => Sponsor::query()->notDeleted()->count(),
            __('active') => User::query()->notDeleted()->where('is_active', true)->count(),
            __('banned') => User::query()->notDeleted()->where('is_banned', true)->count(),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    protected function opportunityRows(): array
    {
        $volunteer = VolunteerOpportunity::query()->notDeleted();
        $learnServe = LearnServeOpportunity::query()->notDeleted();

        return [
            __('volunteer opportunities') => (clone $volunteer)->count(),
            __('learn & share opportunities') => (clone $learnServe)->count(),
            __('events') => Event::query()->notDeleted()->count(),
            __('approved') => (clone $volunteer)->where('approval_status', ApprovalStatus::APPROVED)->count(),
            __('pending') => (clone $volunteer)->where('approval_status', ApprovalStatus::PENDING)->count(),
            __('completed') => (clone $volunteer)->where('opportunity_status', OpportunityStatus::COMPLETED)->count(),
            __('registrations') => VolunteerOpportunityRegistration::query()->notDeleted()->count(),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    protected function hoursRows(): array
    {
        $totalHours = (float) VolunteerStatistic::query()->whereNotNull('month')->sum('volunteer_hours');
        $rate = (float) (Config::query()->value('economic_impact_rate_kwd') ?: 6);

        // Same definition the public statistics endpoint uses.
        $charityBeneficiaries = (int) VolunteerOpportunity::query()
            ->notDeleted()
            ->where('volunteer_category', VolunteerCategory::CHARITY->value)
            ->sum('beneficiaries_count');

        $courseLearners = LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->where('is_attended', true)
            ->whereHas('opportunity', fn ($q) => $q->notDeleted())
            ->count();

        return [
            __('total volunteer hours') => round($totalHours, 2),
            __('economic impact rate') => $rate,
            __('economic impact') => round($totalHours * $rate, 2),
            __('beneficiaries') => $charityBeneficiaries + $courseLearners,
            __('beneficiaries from volunteer opportunities') => $charityBeneficiaries,
            __('learners from courses') => $courseLearners,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    protected function yearlyRows(): array
    {
        return VolunteerStatistic::query()
            ->whereNotNull('month')
            ->selectRaw('year, SUM(volunteer_hours) as total_hours')
            ->groupBy('year')
            ->orderBy('year')
            ->pluck('total_hours', 'year')
            ->mapWithKeys(fn ($hours, $year) => [(string) $year => round((float) $hours, 2)])
            ->all();
    }
}
