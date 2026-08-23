<?php

namespace App\Console\Commands;

use App\Models\Config;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityAttendance;
use App\Services\Mail\DynamicEmailService;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

/**
 * Warns a publisher before the check-in window on their opportunity closes,
 * so attendance does not silently go unrecorded.
 */
class SendCheckInWindowReminderCommand extends Command
{
    protected $signature = 'fursa:send-check-in-window-reminder';

    protected $description = 'Remind opportunity publishers before their check-in window closes';

    public function handle(): int
    {
        $config = Config::query()->first();
        $leadHours = (int) ($config->preparation_reminder_hours_before ?? 12) ?: 12;

        $sent = 0;

        VolunteerOpportunity::query()
            ->notDeleted()
            ->whereNotNull('end_date')
            ->whereNull('preparation_reminder_sent_at')
            ->whereDate('end_date', '<=', now()->toDateString())
            ->with('creator')
            ->chunkById(100, function ($opportunities) use ($leadHours, &$sent) {
                foreach ($opportunities as $opportunity) {
                    $closesAt = $opportunity->preparationValidUntil();

                    if (! $closesAt || $closesAt->isPast()) {
                        continue;
                    }

                    // Only inside the lead window, so the reminder lands close to the deadline.
                    if ($closesAt->gt(now()->addHours($leadHours))) {
                        continue;
                    }

                    if ($this->notify($opportunity, $closesAt, $leadHours)) {
                        $sent++;
                    }
                }
            });

        $this->info("Check-in window reminders sent: {$sent}");

        return self::SUCCESS;
    }

    protected function notify(VolunteerOpportunity $opportunity, \Carbon\Carbon $closesAt, int $leadHours): bool
    {
        $recipientIds = $this->recipientIds($opportunity);
        if ($recipientIds === []) {
            return false;
        }

        $pending = $this->pendingCheckIns($opportunity);
        $titleEn = $opportunity->title_en ?? 'Opportunity';
        $titleAr = $opportunity->title_ar ?? $titleEn;
        $deadline = $closesAt->toDateTimeString();

        NotificationService::createForUsers(
            "Check-in window closing: {$titleEn}",
            "نافذة التحضير تُغلق قريباً: {$titleAr}",
            "The check-in window for '{$titleEn}' closes on {$deadline}. {$pending} registered volunteer(s) still have no attendance recorded.",
            "نافذة التحضير للفرصة '{$titleAr}' تُغلق في {$deadline}. لا يزال {$pending} من المتطوعين المسجلين بدون تسجيل حضور.",
            $recipientIds
        );

        foreach (User::query()->whereIn('id', $recipientIds)->get() as $user) {
            DynamicEmailService::send('check_in_window_reminder', $user, [
                'opportunity_title_en' => $titleEn,
                'opportunity_title_ar' => $titleAr,
                'closes_at' => $deadline,
                'hours_remaining' => (string) $leadHours,
                'pending_check_ins' => (string) $pending,
            ]);
        }

        // Stamped so a daily run cannot re-send the same reminder.
        $opportunity->forceFill(['preparation_reminder_sent_at' => now()])->save();

        return true;
    }

    /**
     * @return list<int>
     */
    protected function recipientIds(VolunteerOpportunity $opportunity): array
    {
        $ids = [];

        if ($opportunity->created_by) {
            $ids[] = (int) $opportunity->created_by;
        }

        // Anyone the publisher delegated scanning to also needs the warning.
        foreach ($opportunity->scanPermissions()->notDeleted()->where('is_allowed', true)->pluck('user_id') as $userId) {
            $ids[] = (int) $userId;
        }

        return array_values(array_unique($ids));
    }

    protected function pendingCheckIns(VolunteerOpportunity $opportunity): int
    {
        $registrationIds = $opportunity->registrations()->notDeleted()->pluck('id');

        if ($registrationIds->isEmpty()) {
            return 0;
        }

        $attended = VolunteerOpportunityAttendance::query()
            ->notDeleted()
            ->whereIn('registration_id', $registrationIds)
            ->where('is_attended', true)
            ->distinct('registration_id')
            ->count('registration_id');

        return max(0, $registrationIds->count() - $attended);
    }
}
