<?php

namespace App\Console\Commands;

use App\Enums\OpportunityStatus;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use App\Services\Mail\DynamicEmailService;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

/**
 * Emergency / backup alert for under-subscribed opportunities.
 *
 * Reworked per client feedback: the alert is no longer limited to Fursa
 * Friends — it reaches every active user — it goes out two days before the
 * deadline, and each opportunity raises it exactly once.
 */
class NotifyFursaFriendsBackupCommand extends Command
{
    /** Days before the registration deadline that the alert goes out. */
    protected const LEAD_DAYS = 2;

    protected $signature = 'fursa:notify-fursa-friends-backup';

    protected $description = 'Alert all users when an upcoming opportunity still needs volunteers (sent once, 2 days before the deadline)';

    public function handle(): int
    {
        $targetDate = now()->addDays(self::LEAD_DAYS)->toDateString();

        $opportunities = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('opportunity_status', OpportunityStatus::UPCOMING)
            // Sent once per opportunity, so a re-run never repeats an alert.
            ->whereNull('backup_alert_sent_at')
            ->whereDate('due_date', '<=', $targetDate)
            ->whereDate('due_date', '>=', now()->toDateString())
            ->get();

        if ($opportunities->isEmpty()) {
            $this->info("No opportunities awaiting a backup alert on or before {$targetDate}.");

            return self::SUCCESS;
        }

        $recipients = $this->recipients();
        if ($recipients->isEmpty()) {
            $this->info('No active users available to notify.');

            return self::SUCCESS;
        }

        $recipientIds = $recipients->pluck('id')->all();
        $notified = 0;

        foreach ($opportunities as $opportunity) {
            $registered = VolunteerOpportunityRegistration::query()
                ->notDeleted()
                ->where('opportunity_id', $opportunity->id)
                ->count();

            $needed = max(0, (int) $opportunity->participants_needed - $registered);
            if ($opportunity->participants_needed > 0 && $needed <= 0) {
                continue;
            }
            if ($needed <= 0) {
                continue;
            }

            $isEmergency = (bool) $opportunity->is_emergency;
            $startDate = optional($opportunity->start_date)->toDateString();

            NotificationService::createForUsers(
                $isEmergency
                    ? "Emergency: {$opportunity->title_en}"
                    : "Volunteer Backup Needed: {$opportunity->title_en}",
                $isEmergency
                    ? "طوارئ: {$opportunity->title_ar}"
                    : "مطلوب دعم تطوعي: {$opportunity->title_ar}",
                "Volunteer opportunity '{$opportunity->title_en}' scheduled for {$startDate} needs {$needed} more volunteers.",
                "الفرصة التطوعية '{$opportunity->title_ar}' المقررة في {$startDate} تحتاج إلى {$needed} متطوعين إضافيين.",
                $recipientIds
            );

            foreach ($recipients as $user) {
                DynamicEmailService::send('fursa_friend_backup_notification', $user, [
                    'volunteers_needed' => $needed,
                    'days_until_start' => self::LEAD_DAYS,
                    'title' => $isEmergency
                        ? "Emergency: {$opportunity->title_en}"
                        : "Volunteer Backup Needed: {$opportunity->title_en}",
                ]);
            }

            $opportunity->forceFill(['backup_alert_sent_at' => now()])->save();
            $notified++;
        }

        $this->info("Backup alerts sent for {$notified} opportunities to {$recipients->count()} users.");

        return self::SUCCESS;
    }

    /**
     * Every active user, not just Fursa Friends — the client wants emergency
     * calls to reach the whole platform.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    protected function recipients()
    {
        return User::query()
            ->where('is_deleted', false)
            ->where('is_banned', false)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get();
    }
}
