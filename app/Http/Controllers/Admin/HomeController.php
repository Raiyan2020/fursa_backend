<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\FursaFriend;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\OrganizationProfile;
use App\Models\Sponsor;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use App\Models\VolunteerProfile;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class HomeController extends Controller
{
    public function index()
    {
        $usersCount = User::query()->notDeleted()->count();
        $volunteersCount = VolunteerProfile::query()->notDeleted()->count();
        $entitiesCount = OrganizationProfile::query()->notDeleted()->count();
        $volunteerOppsCount = VolunteerOpportunity::query()->notDeleted()->count();
        $learnServeCount = LearnServeOpportunity::query()->notDeleted()->count();
        $eventsCount = Event::query()->notDeleted()->count();
        $sponsorsCount = Sponsor::query()->notDeleted()->count();
        $friendsCount = FursaFriend::query()->notDeleted()->count();

        $pendingEntities = OrganizationProfile::query()->notDeleted()
            ->where('organization_status', ApprovalStatus::PENDING)->count();
        $pendingVolunteerOpps = VolunteerOpportunity::query()->notDeleted()
            ->where('approval_status', ApprovalStatus::PENDING)->count();
        $pendingLearnServe = LearnServeOpportunity::query()->notDeleted()
            ->where('approval_status', ApprovalStatus::PENDING)->count();
        $pendingEvents = Event::query()->notDeleted()
            ->where('approval_status', ApprovalStatus::PENDING)->count();
        $pendingSponsors = Sponsor::query()->notDeleted()
            ->where('approval_status', ApprovalStatus::PENDING)->count();

        $pendingTotal = $pendingEntities + $pendingVolunteerOpps + $pendingLearnServe + $pendingEvents + $pendingSponsors;

        $welcome = $this->welcomeStats($usersCount, $pendingTotal);

        $menus = [
            ['name' => __('all users'), 'url' => route('admin.users.index'), 'icon' => 'icon-users', 'count' => $usersCount, 'group' => 'users'],
            ['name' => __('volunteers'), 'url' => route('admin.volunteers.index'), 'icon' => 'icon-user', 'count' => $volunteersCount, 'group' => 'users'],
            ['name' => __('entities'), 'url' => route('admin.entities.index'), 'icon' => 'icon-briefcase', 'count' => $entitiesCount, 'group' => 'users'],
            ['name' => __('forsa friends'), 'url' => route('admin.fursa-friends.index'), 'icon' => 'icon-heart', 'count' => $friendsCount, 'group' => 'users'],
            ['name' => __('volunteer opportunities'), 'url' => route('admin.volunteer-opportunities.index'), 'icon' => 'icon-target', 'count' => $volunteerOppsCount, 'group' => 'content'],
            ['name' => __('learn & share opportunities'), 'url' => route('admin.learn-serve-opportunities.index'), 'icon' => 'icon-book-open', 'count' => $learnServeCount, 'group' => 'content'],
            ['name' => __('events'), 'url' => route('admin.events.index'), 'icon' => 'icon-calendar', 'count' => $eventsCount, 'group' => 'content'],
            ['name' => __('sponsors'), 'url' => route('admin.sponsors.index'), 'icon' => 'icon-award', 'count' => $sponsorsCount, 'group' => 'content'],
        ];

        $charts = $this->buildChartsData([
            'pending_entities' => $pendingEntities,
            'pending_volunteer_opps' => $pendingVolunteerOpps,
            'pending_learn_serve' => $pendingLearnServe,
            'pending_events' => $pendingEvents,
            'pending_sponsors' => $pendingSponsors,
            'volunteers' => $volunteersCount,
            'entities' => $entitiesCount,
            'volunteer_opps' => $volunteerOppsCount,
            'learn_serve' => $learnServeCount,
            'events' => $eventsCount,
            'sponsors' => $sponsorsCount,
            'friends' => $friendsCount,
        ]);

        return view('dashboard.index', compact('welcome', 'menus', 'charts'));
    }

    protected function welcomeStats(int $usersCount, int $pendingCount): array
    {
        $admin = auth('admin')->user();
        $now = Carbon::now()->locale(app()->getLocale());
        $hour = (int) $now->format('H');

        if ($hour >= 5 && $hour < 12) {
            $greeting = __('Good morning');
        } elseif ($hour >= 12 && $hour < 17) {
            $greeting = __('Good afternoon');
        } else {
            $greeting = __('Good evening');
        }

        return [
            'greeting' => $greeting,
            'name' => $admin->name,
            'day' => $now->format('d'),
            'month_year' => $now->translatedFormat('F Y'),
            'users_count' => $usersCount,
            'pending_count' => $pendingCount,
            'new_users_today' => User::query()->notDeleted()->whereDate('created_at', $now->toDateString())->count(),
        ];
    }

    protected function buildChartsData(array $counts): array
    {
        $months = 12;
        $usersMonthly = $this->monthlySeries(User::query()->notDeleted(), 'created_at', $months);
        $volunteerOppsMonthly = $this->monthlySeries(VolunteerOpportunity::query()->notDeleted(), 'created_at', $months);
        $learnServeMonthly = $this->monthlySeries(LearnServeOpportunity::query()->notDeleted(), 'created_at', $months);
        $eventsMonthly = $this->monthlySeries(Event::query()->notDeleted(), 'created_at', $months);

        $userTypes = $this->userTypeDistribution();

        $entityStatuses = $this->approvalDistribution(OrganizationProfile::query()->notDeleted(), 'organization_status');
        $volunteerOppStatuses = $this->approvalDistribution(VolunteerOpportunity::query()->notDeleted(), 'approval_status');
        $learnServeStatuses = $this->approvalDistribution(LearnServeOpportunity::query()->notDeleted(), 'approval_status');
        $eventStatuses = $this->approvalDistribution(Event::query()->notDeleted(), 'approval_status');
        $sponsorStatuses = $this->approvalDistribution(Sponsor::query()->notDeleted(), 'approval_status');

        $registrations = [
            'volunteer' => VolunteerOpportunityRegistration::query()->notDeleted()->count(),
            'learn_serve' => LearnServeOpportunityRegistration::query()->notDeleted()->count(),
            'events' => EventRegistration::query()->notDeleted()->count(),
        ];

        $statusLabels = [
            ApprovalStatus::PENDING->value => ApprovalStatus::PENDING->label(),
            ApprovalStatus::APPROVED->value => ApprovalStatus::APPROVED->label(),
            ApprovalStatus::REJECTED->value => ApprovalStatus::REJECTED->label(),
        ];

        return [
            'locale' => app()->getLocale(),
            'is_rtl' => app()->getLocale() === 'ar',
            'colors' => [
                'primary' => '#7c3aed',
                'primary_soft' => '#a78bfa',
                'success' => '#22c55e',
                'warning' => '#f59e0b',
                'danger' => '#ef4444',
                'info' => '#3b82f6',
                'muted' => '#94a3b8',
                'secondary' => '#8b5cf6',
                'teal' => '#14b8a6',
            ],
            'growth' => [
                'labels' => $usersMonthly['labels'],
                'series' => [
                    [
                        'name' => __('Users'),
                        'data' => $usersMonthly['data'],
                    ],
                    [
                        'name' => __('volunteer opportunities'),
                        'data' => $volunteerOppsMonthly['data'],
                    ],
                    [
                        'name' => __('learn & share opportunities'),
                        'data' => $learnServeMonthly['data'],
                    ],
                    [
                        'name' => __('events'),
                        'data' => $eventsMonthly['data'],
                    ],
                ],
                'totals' => [
                    'users' => array_sum($usersMonthly['data']),
                    'volunteer_opps' => array_sum($volunteerOppsMonthly['data']),
                    'learn_serve' => array_sum($learnServeMonthly['data']),
                    'events' => array_sum($eventsMonthly['data']),
                ],
            ],
            'user_types' => [
                'labels' => $userTypes['labels'],
                'series' => $userTypes['series'],
                'total' => array_sum($userTypes['series']),
            ],
            'approvals' => [
                'categories' => [
                    __('entities'),
                    __('volunteer opportunities'),
                    __('learn & share opportunities'),
                    __('events'),
                    __('sponsors'),
                ],
                'status_labels' => array_values($statusLabels),
                'series' => [
                    [
                        'name' => $statusLabels[ApprovalStatus::PENDING->value],
                        'data' => [
                            $entityStatuses[ApprovalStatus::PENDING->value],
                            $volunteerOppStatuses[ApprovalStatus::PENDING->value],
                            $learnServeStatuses[ApprovalStatus::PENDING->value],
                            $eventStatuses[ApprovalStatus::PENDING->value],
                            $sponsorStatuses[ApprovalStatus::PENDING->value],
                        ],
                    ],
                    [
                        'name' => $statusLabels[ApprovalStatus::APPROVED->value],
                        'data' => [
                            $entityStatuses[ApprovalStatus::APPROVED->value],
                            $volunteerOppStatuses[ApprovalStatus::APPROVED->value],
                            $learnServeStatuses[ApprovalStatus::APPROVED->value],
                            $eventStatuses[ApprovalStatus::APPROVED->value],
                            $sponsorStatuses[ApprovalStatus::APPROVED->value],
                        ],
                    ],
                    [
                        'name' => $statusLabels[ApprovalStatus::REJECTED->value],
                        'data' => [
                            $entityStatuses[ApprovalStatus::REJECTED->value],
                            $volunteerOppStatuses[ApprovalStatus::REJECTED->value],
                            $learnServeStatuses[ApprovalStatus::REJECTED->value],
                            $eventStatuses[ApprovalStatus::REJECTED->value],
                            $sponsorStatuses[ApprovalStatus::REJECTED->value],
                        ],
                    ],
                ],
            ],
            'pending' => [
                'labels' => [
                    __('entities'),
                    __('volunteer opportunities'),
                    __('learn & share opportunities'),
                    __('events'),
                    __('sponsors'),
                ],
                'series' => [
                    $counts['pending_entities'],
                    $counts['pending_volunteer_opps'],
                    $counts['pending_learn_serve'],
                    $counts['pending_events'],
                    $counts['pending_sponsors'],
                ],
                'total' => $counts['pending_entities']
                    + $counts['pending_volunteer_opps']
                    + $counts['pending_learn_serve']
                    + $counts['pending_events']
                    + $counts['pending_sponsors'],
            ],
            'overview' => [
                'labels' => [
                    __('volunteers'),
                    __('entities'),
                    __('volunteer opportunities'),
                    __('learn & share opportunities'),
                    __('events'),
                    __('sponsors'),
                    __('forsa friends'),
                ],
                'series' => [
                    $counts['volunteers'],
                    $counts['entities'],
                    $counts['volunteer_opps'],
                    $counts['learn_serve'],
                    $counts['events'],
                    $counts['sponsors'],
                    $counts['friends'],
                ],
            ],
            'registrations' => [
                'labels' => [
                    __('Volunteer registrations'),
                    __('Learn & serve registrations'),
                    __('Event registrations'),
                ],
                'series' => [
                    $registrations['volunteer'],
                    $registrations['learn_serve'],
                    $registrations['events'],
                ],
                'total' => array_sum($registrations),
            ],
        ];
    }

    /**
     * Zero-filled monthly counts for the last N months (current month included).
     *
     * @return array{labels: list<string>, data: list<int>}
     */
    protected function monthlySeries(Builder $query, string $dateColumn = 'created_at', int $months = 12): array
    {
        $end = Carbon::now()->endOfMonth();
        $start = Carbon::now()->subMonths($months - 1)->startOfMonth();
        $table = $query->getModel()->getTable();
        $qualified = str_contains($dateColumn, '.') ? $dateColumn : "{$table}.{$dateColumn}";
        $connectionName = $query->getModel()->getConnectionName() ?? config('database.default');
        $isSqlite = config("database.connections.{$connectionName}.driver") === 'sqlite';

        $rows = (clone $query)
            ->whereBetween($qualified, [$start->toDateTimeString(), $end->toDateTimeString()])
            ->selectRaw(
                $isSqlite
                    ? "strftime('%Y-%m', {$qualified}) as ym, COUNT(*) as aggregate"
                    : "DATE_FORMAT({$qualified}, '%Y-%m') as ym, COUNT(*) as aggregate"
            )
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('aggregate', 'ym');

        $labels = [];
        $data = [];
        $cursor = $start->copy()->locale(app()->getLocale());

        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $labels[] = $cursor->translatedFormat('M Y');
            $data[] = (int) ($rows[$key] ?? 0);
            $cursor->addMonth();
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    /**
     * @return array{labels: list<string>, series: list<int>}
     */
    protected function userTypeDistribution(): array
    {
        $raw = User::query()
            ->notDeleted()
            ->selectRaw('user_type as type_key, COUNT(*) as aggregate')
            ->groupBy('type_key')
            ->pluck('aggregate', 'type_key');

        $labels = [];
        $series = [];

        foreach (UserType::cases() as $type) {
            $labels[] = $type->label();
            $series[] = (int) ($raw[$type->value] ?? 0);
        }

        $unknown = (int) $raw->sum() - array_sum($series);
        if ($unknown > 0) {
            $labels[] = __('Unknown');
            $series[] = $unknown;
        }

        return compact('labels', 'series');
    }

    /**
     * Always returns pending/approved/rejected with zeros filled.
     *
     * @return array<string, int>
     */
    protected function approvalDistribution(Builder $query, string $column): array
    {
        $table = $query->getModel()->getTable();
        $qualified = str_contains($column, '.') ? $column : "{$table}.{$column}";

        $raw = (clone $query)
            ->selectRaw("{$qualified} as status_key, COUNT(*) as aggregate")
            ->groupBy('status_key')
            ->pluck('aggregate', 'status_key');

        $result = [];
        foreach (ApprovalStatus::cases() as $status) {
            $result[$status->value] = (int) ($raw[$status->value] ?? 0);
        }

        return $result;
    }
}
