<?php

namespace App\Console\Commands;

use App\Models\Config;
use App\Models\User;
use App\Services\Mail\DynamicEmailService;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

/** Port of Django apps.authentication.tasks.unban_users_after_one_month */
class UnbanUsersCommand extends Command
{
    protected $signature = 'fursa:unban-users';

    protected $description = 'Automatically unban users after configured duration (Python: unban_users_after_one_month)';

    public function handle(): int
    {
        $config = Config::query()->first();
        $duration = (int) ($config?->time_duration ?? 1);
        $unit = $config?->time_unit ?? 'months';

        $threshold = match ($unit) {
            'days' => now('Asia/Dubai')->subDays($duration),
            'weeks' => now('Asia/Dubai')->subWeeks($duration),
            'years' => now('Asia/Dubai')->subYears($duration),
            default => now('Asia/Dubai')->subMonths($duration),
        };

        $users = User::query()
            ->where('is_banned', true)
            ->where('manually_banned', false)
            ->where('banned_time', '<=', $threshold)
            ->get();

        foreach ($users as $user) {
            $user->is_banned = false;
            $user->manually_banned = true;
            $user->save();

            NotificationService::createForUsers(
                'Account Unbanned',
                'تم رفع الحظر عن الحساب',
                'Your account has been unbanned. You can now access the platform again.',
                'تم رفع الحظر عن حسابك. يمكنك الآن الوصول إلى المنصة مرة أخرى.',
                [$user->id]
            );

            DynamicEmailService::send('user_unban_email', $user, []);
        }

        $this->info('Unbanned users: '.$users->count());

        return self::SUCCESS;
    }
}
