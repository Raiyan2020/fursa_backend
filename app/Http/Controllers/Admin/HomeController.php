<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FursaFriend;
use App\Models\LearnServeOpportunity;
use App\Models\OrganizationProfile;
use App\Models\Sponsor;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerProfile;
use Carbon\Carbon;

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
        $pendingEvents = Event::query()->notDeleted()
            ->where('approval_status', ApprovalStatus::PENDING)->count();
        $pendingSponsors = Sponsor::query()->notDeleted()
            ->where('approval_status', ApprovalStatus::PENDING)->count();

        $welcome = $this->welcomeStats($usersCount, $pendingEntities + $pendingVolunteerOpps + $pendingEvents + $pendingSponsors);

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

        return view('dashboard.index', compact('welcome', 'menus'));
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
}
