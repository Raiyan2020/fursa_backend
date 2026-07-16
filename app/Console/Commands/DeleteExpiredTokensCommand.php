<?php

namespace App\Console\Commands;

use App\Models\ExpiringToken;
use Illuminate\Console\Command;

/** Port of Django apps.base.tasks.delete_expired_tokens */
class DeleteExpiredTokensCommand extends Command
{
    protected $signature = 'fursa:delete-expired-tokens';

    protected $description = 'Delete expired auth tokens (Python: delete_expired_tokens)';

    public function handle(): int
    {
        $deleted = ExpiringToken::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info("Deleted expired tokens: {$deleted}");

        return self::SUCCESS;
    }
}
