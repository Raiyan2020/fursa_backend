<?php

namespace App\Console\Commands;

use App\Models\VolunteerOpportunity;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * BE-45: `generated_link` was never assigned before this fix, so every
 * existing row has it null and the private-opportunity Share button has
 * nothing to copy. Mints the same unguessable token new rows get.
 */
class BackfillGeneratedLinkCommand extends Command
{
    protected $signature = 'fursa:backfill-generated-link';

    protected $description = 'Assign a generated_link token to volunteer opportunities missing one (BE-45 backfill)';

    public function handle(): int
    {
        $updated = 0;

        VolunteerOpportunity::query()
            ->where(fn ($q) => $q->whereNull('generated_link')->orWhere('generated_link', ''))
            ->chunkById(200, function ($rows) use (&$updated) {
                foreach ($rows as $row) {
                    $row->forceFill(['generated_link' => (string) Str::uuid()])->saveQuietly();
                    $updated++;
                }
            });

        $this->info("generated_link backfilled on {$updated} opportunity(ies).");

        return self::SUCCESS;
    }
}
