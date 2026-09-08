<?php

namespace App\Console\Commands;

use App\Services\Certificate\VolunteerCertificateService;
use Illuminate\Console\Command;

/**
 * One-time backfill for VolunteerOpportunity registrations that were already
 * attended on a completed opportunity before certificate support existed for
 * this opportunity type (e.g. registration 996 on opportunity 113). New
 * registrations are covered automatically going forward, either at the
 * moment the opportunity completes (fursa:advance-statuses) or via the
 * organizer's manual "send certificates" action - this command is only for
 * the gap left by anything attended before this feature shipped.
 */
class BackfillMissingVolunteerCertificatesCommand extends Command
{
    protected $signature = 'fursa:backfill-missing-volunteer-certificates';

    protected $description = 'Issue certificates for already-attended VolunteerOpportunity registrations on completed opportunities';

    public function handle(): int
    {
        $issued = VolunteerCertificateService::issueEligible();

        $this->info("Volunteer opportunity certificates backfilled: {$issued}");

        return self::SUCCESS;
    }
}
