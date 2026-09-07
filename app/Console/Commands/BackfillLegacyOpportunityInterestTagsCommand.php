<?php

namespace App\Console\Commands;

use App\Enums\InterestType;
use App\Models\Interest;
use App\Models\MasterChoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time bridge for legacy interest tags imported from the old (Django/
 * Postgres) database. Opportunities/events were tagged there via a
 * MasterChoice-based pivot (`master_choice_volunteer_opportunity` etc); the
 * current app tags them via the `Interest` model exclusively
 * (`interest_volunteer_opportunity` etc), so those legacy assignments were
 * never read by any Resource and rendered as empty `interests`/`interest_display`.
 *
 * Translates each legacy MasterChoice tag to its Interest-model equivalent by
 * name (creating the Interest row if none matches yet) and inserts it into the
 * current pivot, skipping anything already tagged there. Safe to run more than
 * once. Run this once against the live database — new records already tag
 * correctly through the Interest model, so this is a backfill, not a cron.
 */
class BackfillLegacyOpportunityInterestTagsCommand extends Command
{
    protected $signature = 'fursa:backfill-legacy-opportunity-interest-tags';

    protected $description = 'Bridge legacy MasterChoice-based opportunity/event interest tags into the Interest model';

    public function handle(): int
    {
        $specs = [
            [
                'legacy_table' => 'master_choice_volunteer_opportunity',
                'fk' => 'volunteer_opportunity_id',
                'current_table' => 'interest_volunteer_opportunity',
                'interest_type' => InterestType::VOLUNTEER,
                'label' => 'volunteer opportunities',
            ],
            [
                'legacy_table' => 'master_choice_learn_serve_opportunity',
                'fk' => 'learn_serve_opportunity_id',
                'current_table' => 'interest_learn_serve_opportunity',
                'interest_type' => InterestType::LEARNSHARE,
                'label' => 'learn & serve opportunities',
            ],
            [
                'legacy_table' => 'master_choice_event',
                'fk' => 'event_id',
                'current_table' => 'interest_event',
                'interest_type' => InterestType::EVENT,
                'label' => 'events',
            ],
        ];

        foreach ($specs as $spec) {
            $this->bridge($spec);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{legacy_table: string, fk: string, current_table: string, interest_type: InterestType, label: string}  $spec
     */
    protected function bridge(array $spec): void
    {
        if (! Schema::hasTable($spec['legacy_table'])) {
            $this->warn("Skipping {$spec['label']}: {$spec['legacy_table']} does not exist on this database.");

            return;
        }

        $legacyRows = DB::table($spec['legacy_table'])->get(['id', $spec['fk'], 'master_choice_id']);
        if ($legacyRows->isEmpty()) {
            $this->info("{$spec['label']}: no legacy tags found, nothing to bridge.");

            return;
        }

        $masterChoices = MasterChoice::query()
            ->whereIn('id', $legacyRows->pluck('master_choice_id')->unique())
            ->get()
            ->keyBy('id');

        $inserted = 0;
        $created = 0;
        $skippedNoName = 0;

        foreach ($legacyRows as $row) {
            $choice = $masterChoices->get($row->master_choice_id);
            $name = trim((string) ($choice->value_en ?? ''));

            if ($name === '') {
                $skippedNoName++;

                continue;
            }

            $interest = Interest::query()
                ->whereRaw('LOWER(TRIM(name_en)) = ?', [mb_strtolower($name)])
                ->first();

            if (! $interest) {
                $interest = Interest::query()->create([
                    'name_en' => $name,
                    'name_ar' => $choice->value_ar ?: $name,
                    'interest_type' => $spec['interest_type'],
                ]);
                $created++;
            }

            $entityId = $row->{$spec['fk']};
            $alreadyLinked = DB::table($spec['current_table'])
                ->where($spec['fk'], $entityId)
                ->where('interest_id', $interest->id)
                ->exists();

            if (! $alreadyLinked) {
                DB::table($spec['current_table'])->insert([
                    $spec['fk'] => $entityId,
                    'interest_id' => $interest->id,
                ]);
                $inserted++;
            }
        }

        $this->info("{$spec['label']}: {$inserted} tag(s) bridged, {$created} new Interest row(s) created, {$skippedNoName} legacy row(s) skipped (no name on the MasterChoice).");
    }
}
