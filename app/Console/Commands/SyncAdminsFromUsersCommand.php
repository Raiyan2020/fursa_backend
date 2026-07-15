<?php

namespace App\Console\Commands;

use Database\Seeders\MigrateAdminsFromUsersSeeder;
use Illuminate\Console\Command;

class SyncAdminsFromUsersCommand extends Command
{
    protected $signature = 'admins:sync-from-users';

    protected $description = 'Copy Django admin users from `users` into Laravel `admins` (keeps original password hashes)';

    public function handle(): int
    {
        $this->info('Syncing dashboard admins from users table...');
        $this->callSilent('db:seed', ['--class' => MigrateAdminsFromUsersSeeder::class]);
        $this->info('Done. Client can log in at /dashboard/login with the same email & password as before.');

        return self::SUCCESS;
    }
}
