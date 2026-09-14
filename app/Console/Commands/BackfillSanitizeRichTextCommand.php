<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Faq;
use App\Models\LearnServeOpportunity;
use App\Models\Page;
use App\Models\VolunteerOpportunity;
use App\Support\HtmlSanitizer;
use Illuminate\Console\Command;

/**
 * One-off backfill for BE-37: every row written before the sanitizer was
 * wired into the write path was stored verbatim, including any hostile
 * markup. This re-cleans the existing rich-text columns in place.
 */
class BackfillSanitizeRichTextCommand extends Command
{
    protected $signature = 'fursa:backfill-sanitize-rich-text';

    protected $description = 'Sanitize stored rich-text HTML on existing rows (BE-37 backfill)';

    /** @var array<class-string, list<string>> */
    private const TARGETS = [
        VolunteerOpportunity::class => ['description_en', 'description_ar'],
        Event::class => ['description_en', 'description_ar'],
        LearnServeOpportunity::class => ['description_en', 'description_ar'],
        Faq::class => ['answer_en', 'answer_ar'],
        Page::class => ['content_en', 'content_ar'],
    ];

    public function handle(): int
    {
        $total = 0;

        foreach (self::TARGETS as $model => $fields) {
            $updated = 0;

            $model::query()->chunkById(200, function ($rows) use ($fields, &$updated) {
                foreach ($rows as $row) {
                    $changes = [];

                    foreach ($fields as $field) {
                        $original = $row->{$field};
                        $clean = HtmlSanitizer::clean($original);

                        if ($clean !== $original) {
                            $changes[$field] = $clean;
                        }
                    }

                    if ($changes !== []) {
                        $row->forceFill($changes)->saveQuietly();
                        $updated++;
                    }
                }
            });

            $this->info($model.': '.$updated.' row(s) sanitized');
            $total += $updated;
        }

        $this->info("Total rows sanitized: {$total}");

        return self::SUCCESS;
    }
}
