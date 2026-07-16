<?php

namespace App\Console\Commands;

use App\Models\OrganizationProfile;
use App\Models\VolunteerProfile;
use App\Services\Opportunity\SyncService;
use Illuminate\Console\Command;

/**
 * Port of Django:
 * - update_all_statistics
 * - recalculate_badges
 * - assign_badges_to_users
 */
class SyncAllStatisticsCommand extends Command
{
    protected $signature = 'fursa:sync-all-statistics {--mode=all : all|badges|recalculate}';

    protected $description = 'Sync volunteer/org statistics and badges (Python: update-all-statistics / recalculate-badges / assign_badges_to_users)';

    public function handle(): int
    {
        $mode = $this->option('mode');
        $this->info("Starting statistics sync (mode={$mode})...");

        $volunteers = VolunteerProfile::query()->notDeleted()->pluck('user_id');
        $orgs = OrganizationProfile::query()->notDeleted()->pluck('user_id');

        $ok = 0;
        foreach ($volunteers as $userId) {
            if (SyncService::syncVolunteer((int) $userId)) {
                $ok++;
            }
        }
        foreach ($orgs as $userId) {
            if (SyncService::syncOrganization((int) $userId)) {
                $ok++;
            }
        }

        $this->info("Synced {$ok} users.");

        return self::SUCCESS;
    }
}
