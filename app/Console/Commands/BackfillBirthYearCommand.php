<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Fills `users.birth_year` from `users.dob` for accounts that have a birth date
 * but no birth year.
 *
 * Registration accepted either field and stored them independently, so these
 * accounts could not sign up for any opportunity ("please provide your birth
 * year") and were skipped by age-targeted notifications — even though the
 * birth date was on file the whole time.
 */
class BackfillBirthYearCommand extends Command
{
    protected $signature = 'fursa:backfill-birth-year {--dry-run : Report what would change without writing}';

    protected $description = 'Derive users.birth_year from users.dob where it is missing';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = User::query()
            ->whereNotNull('dob')
            ->where(function ($q) {
                $q->whereNull('birth_year')->orWhere('birth_year', 0);
            });

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No users need a birth_year backfill.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Users to backfill: {$total}");

        $updated = 0;
        $skipped = 0;

        $query->chunkById(500, function ($users) use (&$updated, &$skipped, $dryRun) {
            foreach ($users as $user) {
                try {
                    $year = (int) Carbon::parse($user->dob)->year;
                } catch (\Throwable) {
                    $skipped++;

                    continue;
                }

                // Guard against obviously bad dates rather than writing nonsense.
                if ($year < 1900 || $year > (int) now()->year) {
                    $skipped++;

                    continue;
                }

                if (! $dryRun) {
                    // saveQuietly: this is a data repair, not a profile change,
                    // so it should not fire model events or touch timestamps.
                    $user->birth_year = $year;
                    $user->saveQuietly();
                }

                $updated++;
            }
        });

        $this->info(($dryRun ? '[dry-run] ' : '')."Backfilled: {$updated}, skipped (unparsable/out of range): {$skipped}");

        if ($skipped > 0) {
            $this->warn('Skipped rows still need the user to supply a birth date.');
        }

        return self::SUCCESS;
    }
}
