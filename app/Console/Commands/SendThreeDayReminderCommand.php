<?php

namespace App\Console\Commands;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use App\Services\Mail\DynamicEmailService;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

/** Port of Django apps.opportunity.tasks.send_three_day_reminder */
class SendThreeDayReminderCommand extends Command
{
    protected $signature = 'fursa:send-three-day-reminder';

    protected $description = 'Send 3-day upcoming reminders (Python: send-three-day-reminder)';

    public function handle(): int
    {
        $target = now()->addDays(3)->toDateString();
        $count = 0;

        VolunteerOpportunity::query()
            ->notDeleted()
            ->whereDate('start_date', $target)
            ->where('opportunity_status', OpportunityStatus::UPCOMING)
            ->with(['registrations' => fn ($q) => $q->notDeleted()->with('user')])
            ->get()
            ->each(function (VolunteerOpportunity $opp) use (&$count) {
                foreach ($opp->registrations as $reg) {
                    if (! $reg->user) {
                        continue;
                    }
                    NotificationService::createForUsers(
                        "Upcoming Volunteer Opportunity: {$opp->title_en}",
                        "فرصة تطوعية قادمة: {$opp->title_ar}",
                        "Reminder: Your opportunity '{$opp->title_en}' starts in 3 days.",
                        "تذكير: فرصتك التطوعية '{$opp->title_ar}' تبدأ بعد 3 أيام.",
                        [$reg->user_id]
                    );
                    DynamicEmailService::send('volunteer_three_day_reminder', $reg->user, [
                        'user_name' => trim(($reg->user->first_name ?? '').' '.($reg->user->last_name ?? '')),
                        'opportunity_title' => $opp->title_en,
                        'start_date' => optional($opp->start_date)->toDateString(),
                    ]);
                    $count++;
                }
            });

        LearnServeOpportunity::query()
            ->notDeleted()
            ->whereDate('start_date', $target)
            ->where('opportunity_status', OpportunityStatus::UPCOMING)
            ->with(['registrations' => fn ($q) => $q->notDeleted()->with('user')])
            ->get()
            ->each(function (LearnServeOpportunity $opp) use (&$count) {
                foreach ($opp->registrations as $reg) {
                    if (! $reg->user) {
                        continue;
                    }
                    NotificationService::createForUsers(
                        "Upcoming Learn & Share: {$opp->title_en}",
                        "فرصة \"تعلم و شارك\" قادمة: {$opp->title_ar}",
                        "Reminder: Your Learn & Share opportunity '{$opp->title_en}' starts in 3 days.",
                        "تذكير: فرصة \"تعلم و شارك\" الخاصة بك '{$opp->title_ar}' تبدأ بعد 3 أيام.",
                        [$reg->user_id]
                    );
                    DynamicEmailService::send('learnserve_three_day_reminder', $reg->user, [
                        'user_name' => trim(($reg->user->first_name ?? '').' '.($reg->user->last_name ?? '')),
                        'opportunity_title' => $opp->title_en,
                        'start_date' => optional($opp->start_date)->toDateString(),
                    ]);
                    $count++;
                }
            });

        Event::query()
            ->notDeleted()
            ->whereDate('start_date', $target)
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->with(['registrations' => fn ($q) => $q->notDeleted()->with('user')])
            ->get()
            ->each(function (Event $event) use (&$count) {
                foreach ($event->registrations as $reg) {
                    if (! $reg->user) {
                        continue;
                    }
                    NotificationService::createForUsers(
                        "Upcoming Event: {$event->title_en}",
                        "فعالية قادمة: {$event->title_ar}",
                        "Reminder: Your event '{$event->title_en}' starts in 3 days.",
                        "تذكير: الفعالية الخاصة بك '{$event->title_ar}' يبدأ بعد 3 أيام.",
                        [$reg->user_id]
                    );
                    DynamicEmailService::send('event_three_day_reminder', $reg->user, [
                        'user_name' => trim(($reg->user->first_name ?? '').' '.($reg->user->last_name ?? '')),
                        'event_title' => $event->title_en,
                        'start_date' => optional($event->start_date)->toDateString(),
                    ]);
                    $count++;
                }
            });

        $this->info("Three-day reminders sent: {$count}");

        return self::SUCCESS;
    }
}
