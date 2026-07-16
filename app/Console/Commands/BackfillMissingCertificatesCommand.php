<?php

namespace App\Console\Commands;

use App\Models\LearnServeOpportunityRegistration;
use App\Services\Opportunity\SyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Port of Django apps.opportunity.tasks.backfill_missing_certificates.
 * Full Selenium PDF generation is not ported; eligible rows are flagged and a
 * lightweight placeholder certificate is stored when missing.
 */
class BackfillMissingCertificatesCommand extends Command
{
    protected $signature = 'fursa:backfill-missing-certificates';

    protected $description = 'Backfill missing Learn&Serve certificates (Python: backfill-missing-certificates)';

    public function handle(): int
    {
        $registrations = LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->where('is_attended', true)
            ->where('is_certified', false)
            ->with(['opportunity.certificateType', 'opportunity.learningType', 'user'])
            ->get();

        $processed = 0;

        foreach ($registrations as $registration) {
            $opportunity = $registration->opportunity;
            if (! $opportunity) {
                continue;
            }

            $certificateType = strtolower((string) ($opportunity->certificateType?->value_en ?? ''));
            $learningType = strtolower((string) ($opportunity->learningType?->value_en ?? ''));

            $eligible = (
                $certificateType === 'forsa certificate'
                && in_array($learningType, ['internship', 'course'], true)
            ) || $certificateType === "organizer's certificate";

            if (! $eligible) {
                continue;
            }

            try {
                $path = 'certificates/registration_'.$registration->id.'.txt';
                $content = implode("\n", [
                    'Fursa Certificate',
                    'Name: '.trim(($registration->user?->first_name ?? '').' '.($registration->user?->last_name ?? '')),
                    'Course: '.($opportunity->title_en ?? ''),
                    'Start: '.optional($opportunity->start_date)->toDateString(),
                    'End: '.optional($opportunity->end_date)->toDateString(),
                    'Generated: '.now()->toDateTimeString(),
                ]);

                Storage::disk('public')->put($path, $content);

                $registration->certificate_image = $path;
                $registration->is_certified = true;
                $registration->save();

                if ($registration->user_id) {
                    SyncService::syncUser((int) $registration->user_id);
                }

                $processed++;
            } catch (\Throwable $e) {
                Log::error('Certificate backfill failed for registration '.$registration->id.': '.$e->getMessage());
            }
        }

        $this->info("Certificates backfilled: {$processed}");

        return self::SUCCESS;
    }
}
