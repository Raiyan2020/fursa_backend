<?php

namespace App\Console\Commands;

use App\Enums\ApprovalStatus;
use App\Enums\OpportunityStatus;
use App\Models\Event;
use App\Models\LearnServeOpportunity;
use App\Models\VolunteerOpportunity;
use App\Services\Mail\DynamicEmailService;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

/** Port of Django apps.opportunity.tasks.send_day_of_notification */
class SendDayOfNotificationCommand extends Command
{
    protected $signature = 'fursa:send-day-of-notification';

    protected $description = 'Send same-day start notifications (Python: send-day-of-notification)';

    public function handle(): int
    {
        $today = now()->toDateString();
        $count = 0;

        VolunteerOpportunity::query()
            ->notDeleted()
            ->whereDate('start_date', $today)
            ->where('opportunity_status', OpportunityStatus::INPROGRESS)
            ->with(['registrations' => fn ($q) => $q->notDeleted()->with('user')])
            ->get()
            ->each(function (VolunteerOpportunity $opp) use (&$count) {
                foreach ($opp->registrations as $reg) {
                    if (! $reg->user) {
                        continue;
                    }
                    NotificationService::createForUsers(
                        "Today: Volunteer Opportunity - {$opp->title_en}",
                        "اليوم: فرصة تطوعية - {$opp->title_ar}",
                        "Your volunteer opportunity '{$opp->title_en}' starts today at {$opp->start_time}! We look forward to your participation.",
                        "فرصتك التطوعية '{$opp->title_ar}' تبدأ اليوم في {$opp->start_time}! نتطلع إلى مشاركتك.",
                        [$reg->user_id]
                    );
                    DynamicEmailService::send('volunteer_day_of_notification', $reg->user, [
                        'user_name' => trim(($reg->user->first_name ?? '').' '.($reg->user->last_name ?? '')),
                        'opportunity_title' => $opp->title_en,
                        'start_date' => optional($opp->start_date)->toDateString(),
                    ]);
                    $count++;
                }
            });

        LearnServeOpportunity::query()
            ->notDeleted()
            ->whereDate('start_date', $today)
            ->where('opportunity_status', OpportunityStatus::INPROGRESS)
            ->with(['registrations' => fn ($q) => $q->notDeleted()->with('user')])
            ->get()
            ->each(function (LearnServeOpportunity $opp) use (&$count) {
                foreach ($opp->registrations as $reg) {
                    if (! $reg->user) {
                        continue;
                    }
                    NotificationService::createForUsers(
                        "Today: Learn & Share Opportunity - {$opp->title_en}",
                        "اليوم: فرصة \"تعلم و شارك\" - {$opp->title_ar}",
                        "Your Learn & Share opportunity '{$opp->title_en}' starts today at {$opp->start_time}! We look forward to your participation.",
                        "فرصة \"تعلم و شارك\" الخاصة بك '{$opp->title_ar}' تبدأ اليوم في {$opp->start_time}! نتطلع إلى مشاركتك.",
                        [$reg->user_id]
                    );
                    DynamicEmailService::send('learnserve_day_of_notification', $reg->user, [
                        'user_name' => trim(($reg->user->first_name ?? '').' '.($reg->user->last_name ?? '')),
                        'opportunity_title' => $opp->title_en,
                        'start_date' => optional($opp->start_date)->toDateString(),
                    ]);
                    $count++;
                }
            });

        Event::query()
            ->notDeleted()
            ->whereDate('start_date', $today)
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->with(['registrations' => fn ($q) => $q->notDeleted()->with('user')])
            ->get()
            ->each(function (Event $event) use (&$count) {
                foreach ($event->registrations as $reg) {
                    if (! $reg->user) {
                        continue;
                    }
                    NotificationService::createForUsers(
                        "Today: Event - {$event->title_en}",
                        "اليوم: فعالية - {$event->title_ar}",
                        "Your event '{$event->title_en}' starts today at {$event->start_time}! We look forward to your participation.",
                        "الفعالية الخاصة بك '{$event->title_ar}' يبدأ اليوم في {$event->start_time}! نتطلع إلى مشاركتك.",
                        [$reg->user_id]
                    );
                    DynamicEmailService::send('event_day_of_notification', $reg->user, [
                        'user_name' => trim(($reg->user->first_name ?? '').' '.($reg->user->last_name ?? '')),
                        'event_title' => $event->title_en,
                        'start_date' => optional($event->start_date)->toDateString(),
                    ]);
                    $count++;
                }
            });

        $this->info("Day-of notifications sent: {$count}");

        return self::SUCCESS;
    }
}
