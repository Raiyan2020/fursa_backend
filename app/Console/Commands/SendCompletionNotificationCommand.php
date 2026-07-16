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

/** Port of Django apps.opportunity.tasks.send_completion_notification */
class SendCompletionNotificationCommand extends Command
{
    protected $signature = 'fursa:send-completion-notification';

    protected $description = 'Send thank-you notifications 1 day after completion (Python: send-completion-notification)';

    public function handle(): int
    {
        $yesterday = now()->subDay()->toDateString();
        $count = 0;

        VolunteerOpportunity::query()
            ->notDeleted()
            ->whereDate('end_date', $yesterday)
            ->where('opportunity_status', OpportunityStatus::COMPLETED)
            ->with(['registrations' => fn ($q) => $q->notDeleted()->with('user')])
            ->get()
            ->each(function (VolunteerOpportunity $opp) use (&$count) {
                foreach ($opp->registrations as $reg) {
                    if (! $reg->user) {
                        continue;
                    }
                    NotificationService::createForUsers(
                        "Thank You: Volunteer Opportunity Completed - {$opp->title_en}",
                        "شكرًا: اكتملت الفرصة التطوعية - {$opp->title_ar}",
                        "Thank you for participating in '{$opp->title_en}' which completed on {$opp->end_date}. We appreciate your contribution!",
                        "شكرًا لمشاركتك في '{$opp->title_ar}' التي اكتملت في {$opp->end_date}. نحن نقدر مساهمتك!",
                        [$reg->user_id]
                    );
                    DynamicEmailService::send('volunteer_completion_notification', $reg->user, [
                        'user_name' => trim(($reg->user->first_name ?? '').' '.($reg->user->last_name ?? '')),
                        'opportunity_title' => $opp->title_en,
                        'end_date' => optional($opp->end_date)->toDateString(),
                    ]);
                    $count++;
                }
            });

        LearnServeOpportunity::query()
            ->notDeleted()
            ->whereDate('end_date', $yesterday)
            ->where('opportunity_status', OpportunityStatus::COMPLETED)
            ->with(['registrations' => fn ($q) => $q->notDeleted()->with('user')])
            ->get()
            ->each(function (LearnServeOpportunity $opp) use (&$count) {
                foreach ($opp->registrations as $reg) {
                    if (! $reg->user) {
                        continue;
                    }
                    NotificationService::createForUsers(
                        "Thank You: Learn & Share Opportunity Completed - {$opp->title_en}",
                        "شكرًا: اكتملت فرصة \"تعلم و شارك\" - {$opp->title_ar}",
                        "Thank you for participating in '{$opp->title_en}' which completed on {$opp->end_date}. We appreciate your contribution!",
                        "شكرًا لمشاركتك في '{$opp->title_ar}' التي اكتملت في {$opp->end_date}. نحن نقدر مساهمتك!",
                        [$reg->user_id]
                    );
                    DynamicEmailService::send('learnserve_completion_notification', $reg->user, [
                        'user_name' => trim(($reg->user->first_name ?? '').' '.($reg->user->last_name ?? '')),
                        'opportunity_title' => $opp->title_en,
                        'end_date' => optional($opp->end_date)->toDateString(),
                    ]);
                    $count++;
                }
            });

        Event::query()
            ->notDeleted()
            ->whereDate('end_date', $yesterday)
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->with(['registrations' => fn ($q) => $q->notDeleted()->with('user')])
            ->get()
            ->each(function (Event $event) use (&$count) {
                foreach ($event->registrations as $reg) {
                    if (! $reg->user) {
                        continue;
                    }
                    NotificationService::createForUsers(
                        "Thank You: Event Completed - {$event->title_en}",
                        "شكرًا: اكتملت الفعالية - {$event->title_ar}",
                        "Thank you for participating in '{$event->title_en}' which completed on {$event->end_date}. We appreciate your attendance!",
                        "شكرًا لمشاركتك في '{$event->title_ar}' الذي اكتمل في {$event->end_date}. نحن نقدر حضورك!",
                        [$reg->user_id]
                    );
                    DynamicEmailService::send('event_completion_notification', $reg->user, [
                        'user_name' => trim(($reg->user->first_name ?? '').' '.($reg->user->last_name ?? '')),
                        'event_title' => $event->title_en,
                        'end_date' => optional($event->end_date)->toDateString(),
                    ]);
                    $count++;
                }
            });

        $this->info("Completion notifications sent: {$count}");

        return self::SUCCESS;
    }
}
