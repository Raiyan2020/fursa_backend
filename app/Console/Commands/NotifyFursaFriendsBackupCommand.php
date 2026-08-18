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
        $friendUserIds = $friends->pluck('user_id')->filter()->all();

        $volunteerIds = \App\Models\User::query()
            ->where('user_type', \App\Enums\UserType::VOLUNTEER)
            ->where('is_deleted', false)
            ->where('is_banned', false)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $recipientIds = array_values(array_unique(array_merge($friendUserIds, $volunteerIds)));
        if ($recipientIds === []) {
            $this->info('No individual volunteers available to notify.');

            return self::SUCCESS;
        }

        $recipients = \App\Models\User::query()->whereIn('id', $recipientIds)->get();

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

            $friendIds = $recipientIds;

            NotificationService::createForUsers(
                "Volunteer Backup Needed: {$opp->title_en}",
                "مطلوب دعم تطوعي: {$opp->title_ar}",
                "Volunteer opportunity '{$opp->title_en}' scheduled for {$opp->start_date} (3 days from now) needs {$needed} more volunteers.",
                "الفرصة التطوعية '{$opp->title_ar}' المقررة في {$opp->start_date} (بعد 3 أيام) تحتاج إلى {$needed} متطوعين إضافيين.",
                $friendIds
            );

            foreach ($recipients as $user) {
                DynamicEmailService::send('fursa_friend_backup_notification', $user, [
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
