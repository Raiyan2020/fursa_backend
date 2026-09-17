<?php

namespace App\Console\Commands;

use App\Models\ChoiceType;
use App\Models\MasterChoice;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * BE-62 — renames "Consultation"'s Arabic label to «مساحة» and backfills
 * `master_choices.slug`, without running the full ChoiceTypeSeeder.
 *
 * The seeder upserts every choice type on every environment it runs on;
 * running it against production data is riskier than this ticket calls for.
 * The migration (2026_09_17_000003_...) already does the same work on
 * `php artisan migrate` — this command exists only as a standalone way to
 * apply or verify the rename separately, and is safe to run more than once.
 */
class RenameConsultationToSpaceCommand extends Command
{
    protected $signature = 'fursa:rename-consultation-to-space {--dry-run : Report what would change without writing}';

    protected $description = 'Rename Consultation\'s Arabic label to «مساحة» and backfill master_choices.slug';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $typeIds = ChoiceType::query()
            ->whereIn('name', ['learning_type', 'filter-type'])
            ->pluck('id', 'name');

        if ($typeIds->isEmpty()) {
            $this->error('learning_type / filter-type choice types not found — seed choice types first.');

            return self::FAILURE;
        }

        $toRename = MasterChoice::query()
            ->whereIn('choice_type_id', $typeIds)
            ->where('value_en', 'Consultation')
            ->where('value_ar', '!=', 'مساحة')
            ->get();

        $this->info(($dryRun ? '[dry-run] ' : '').'Consultation rows to rename: '.$toRename->count());

        foreach ($toRename as $choice) {
            $this->line("  choice_type_id={$choice->choice_type_id} id={$choice->id}: \"{$choice->value_ar}\" -> \"مساحة\"");

            if (! $dryRun) {
                $choice->value_ar = 'مساحة';
                $choice->save();
            }
        }

        $missingSlug = MasterChoice::query()->whereNull('slug')->orderBy('id')->get();
        $this->info(($dryRun ? '[dry-run] ' : '').'Rows missing a slug: '.$missingSlug->count());

        if (! $dryRun) {
            foreach ($missingSlug as $choice) {
                $choice->slug = $this->uniqueSlug($choice);
                $choice->save();
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function uniqueSlug(MasterChoice $choice): string
    {
        $base = Str::slug($choice->value_en) ?: 'choice';
        $slug = $base;
        $i = 2;
        while (
            MasterChoice::query()
                ->where('choice_type_id', $choice->choice_type_id)
                ->where('slug', $slug)
                ->where('id', '!=', $choice->id)
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
