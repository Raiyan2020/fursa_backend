<?php

namespace App\Console\Commands;

use App\Enums\OpportunityStatus;
use App\Models\FursaFriend;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use App\Services\Mail\DynamicEmailService;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

/** Port of Django apps.fursa_friend.tasks.notify_friends_for_volunteer_backup */
class NotifyFursaFriendsBackupCommand extends Command
{
    protected $signature = 'fursa:notify-fursa-friends-backup';

    protected $description = 'Notify Fursa Friends when upcoming opportunities need volunteers (Python: check-volunteer-backup-needs)';

    public function handle(): int
    {
        $targetDate = now()->addDays(3)->toDateString();

        $opportunities = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('opportunity_status', OpportunityStatus::UPCOMING)
            ->whereDate('due_date', $targetDate)
            ->get();

        if ($opportunities->isEmpty()) {
            $this->info("No upcoming opportunities due on {$targetDate}.");

            return self::SUCCESS;
        }

        $friends = FursaFriend::query()->notDeleted()->with('user')->get();
        if ($friends->isEmpty()) {
            $this->info('No Forsa Friends available to notify.');

            return self::SUCCESS;
        }

        $notified = 0;

        foreach ($opportunities as $opp) {
            $approvedCount = VolunteerOpportunityRegistration::query()
                ->notDeleted()
                ->where('opportunity_id', $opp->id)
                ->count();

            if ($opp->participants_needed > 0 && $approvedCount >= $opp->participants_needed) {
                continue;
            }

            $needed = max(0, (int) $opp->participants_needed - $approvedCount);
            if ($needed <= 0) {
                continue;
            }

            $friendIds = $friends->pluck('user_id')->filter()->all();

            NotificationService::createForUsers(
                "Volunteer Backup Needed: {$opp->title_en}",
                "مطلوب دعم تطوعي: {$opp->title_ar}",
                "Volunteer opportunity '{$opp->title_en}' scheduled for {$opp->start_date} (3 days from now) needs {$needed} more volunteers. As a Forsa Friend, your support is requested regardless of skills or interests.",
                "الفرصة التطوعية '{$opp->title_ar}' المقررة في {$opp->start_date} (بعد 3 أيام) تحتاج إلى {$needed} متطوعين إضافيين. كصديق فرصة، نطلب دعمك بغض النظر عن المهارات أو الاهتمامات.",
                $friendIds
            );

            foreach ($friends as $friend) {
                if (! $friend->user) {
                    continue;
                }
                DynamicEmailService::send('fursa_friend_backup_notification', $friend->user, [
                    'volunteers_needed' => $needed,
                    'days_until_start' => 3,
                    'title' => "Volunteer Backup Needed: {$opp->title_en}",
                ]);
            }

            $notified++;
        }

        $this->info("Fursa Friend backup notices sent for {$notified} opportunities.");

        return self::SUCCESS;
    }
}
