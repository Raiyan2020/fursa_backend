<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchedulerCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_fursa_scheduler_command_completes_without_exception(): void
    {
        Mail::fake();
        Notification::fake();
        Queue::fake();
        Storage::fake('public');
        $this->seed();

        $commands = [
            'fursa:advance-statuses',
            'fursa:backfill-missing-certificates',
            'fursa:check-and-ban-non-attending',
            'fursa:delete-expired-tokens',
            'fursa:generate-missing-qr-codes',
            'fursa:notify-fursa-friends-backup',
            'fursa:send-completion-notification',
            'fursa:send-day-of-notification',
            'fursa:send-three-day-reminder',
            'fursa:sync-all-statistics',
            'fursa:unban-users',
        ];

        foreach ($commands as $command) {
            $this->artisan($command)->assertExitCode(0);
        }
    }
}
