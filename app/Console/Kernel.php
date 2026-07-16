<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * Schedules mirror Django CELERY_BEAT_SCHEDULE in config/settings.py.
     */
    protected function schedule(Schedule $schedule): void
    {
        // midnight — update opportunity/event statuses
        $schedule->command('fursa:advance-statuses')->dailyAt('00:00');

        // midnight — Fursa Friend backup needs
        $schedule->command('fursa:notify-fursa-friends-backup')->dailyAt('00:00');

        // 1:00 AM — sync all statistics
        $schedule->command('fursa:sync-all-statistics --mode=all')->dailyAt('01:00');

        // 1:30 AM — recalculate badges (same SyncService sweep)
        $schedule->command('fursa:sync-all-statistics --mode=recalculate')->dailyAt('01:30');

        // 2:00 AM — assign badges
        $schedule->command('fursa:sync-all-statistics --mode=badges')->dailyAt('02:00');

        // 2:30 AM — unban users after configured duration
        $schedule->command('fursa:unban-users')->dailyAt('02:30');

        // 3:00 AM — delete expired tokens
        $schedule->command('fursa:delete-expired-tokens')->dailyAt('03:00');

        // 3:30 AM — backfill missing certificates
        $schedule->command('fursa:backfill-missing-certificates')->dailyAt('03:30');

        // 4:00 AM — generate missing QR codes
        $schedule->command('fursa:generate-missing-qr-codes')->dailyAt('04:00');

        // 5:00 AM — 3-day reminders
        $schedule->command('fursa:send-three-day-reminder')->dailyAt('05:00');

        // 6:00 AM — day-of notifications
        $schedule->command('fursa:send-day-of-notification')->dailyAt('06:00');

        // 7:00 AM — completion thank-you notifications
        $schedule->command('fursa:send-completion-notification')->dailyAt('07:00');

        // 8:00 AM — ban non-attending volunteers
        $schedule->command('fursa:check-and-ban-non-attending')->dailyAt('08:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
