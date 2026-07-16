<?php

namespace App\Console\Commands;

use App\Enums\OpportunityStatus;
use App\Enums\UserType;
use App\Models\Config;
use App\Models\User;
use App\Models\VolunteerOpportunityAttendance;
use App\Models\VolunteerOpportunityRegistration;
use App\Services\Mail\DynamicEmailService;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

/** Port of Django apps.authentication.tasks.check_and_ban_non_attending_users */
class CheckAndBanNonAttendingCommand extends Command
{
    protected $signature = 'fursa:check-and-ban-non-attending';

    protected $description = 'Ban volunteers who miss too many completed opportunities (Python: check-and-ban-non-attending-users)';

    public function handle(): int
    {
        $config = Config::query()->first();
        $minRequired = (int) ($config?->number_of_opportunities ?? 5);
        $banned = 0;

        $users = User::query()
            ->where('is_banned', false)
            ->where('user_type', UserType::VOLUNTEER)
            ->get();

        foreach ($users as $user) {
            $attendedRegistrationIds = VolunteerOpportunityAttendance::query()
                ->notDeleted()
                ->whereHas('registration', fn ($q) => $q->where('user_id', $user->id)->notDeleted())
                ->pluck('registration_id');

            $nonAttendedCount = VolunteerOpportunityRegistration::query()
                ->notDeleted()
                ->where('user_id', $user->id)
                ->whereHas('opportunity', function ($q) {
                    $q->notDeleted()->where('opportunity_status', OpportunityStatus::COMPLETED);
                })
                ->whereNotIn('id', $attendedRegistrationIds)
                ->count();

            if ($nonAttendedCount < $minRequired) {
                continue;
            }

            $user->is_banned = true;
            $user->manually_banned = false;
            $user->banned_time = now('Asia/Dubai');
            $user->save();
            $banned++;

            NotificationService::createForUsers(
                'Account Temporarily Banned',
                'تم تعليق الحساب مؤقتًا',
                "Your account has been temporarily banned due to missing {$nonAttendedCount} volunteer opportunities. The ban will automatically lift after 1 month.",
                "تم تعليق حسابك مؤقتًا بسبب تفويت {$nonAttendedCount} فرص تطوعية. سيتم رفع الحظر تلقائيًا بعد شهر واحد.",
                [$user->id]
            );

            DynamicEmailService::send('user_ban_notification_low_attendance', $user, [
                'non_attended_count' => $nonAttendedCount,
            ]);

            $admins = User::query()
                ->where('user_type', UserType::ADMIN)
                ->where('is_active', true)
                ->get();

            $adminIds = $admins->pluck('id')->all();
            NotificationService::createForUsers(
                "User {$user->email} Temporarily Banned",
                "تم تعليق المستخدم {$user->email} مؤقتًا",
                "The user {$user->email} has been temporarily banned for missing {$nonAttendedCount} volunteer opportunities.",
                "تم تعليق المستخدم {$user->email} مؤقتًا بسبب تفويت {$nonAttendedCount} فرص تطوعية.",
                $adminIds
            );

            foreach ($admins as $admin) {
                DynamicEmailService::send('admin_user_ban_notification_low_attendance', $admin, [
                    'banned_user_full_name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->email,
                    'non_attended_count' => $nonAttendedCount,
                ]);
            }
        }

        $this->info("Banned users: {$banned}");

        return self::SUCCESS;
    }
}
