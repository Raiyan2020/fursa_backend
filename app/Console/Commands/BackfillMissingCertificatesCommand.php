<?php

namespace App\Console\Commands;

use App\Models\LearnServeOpportunityRegistration;
use App\Services\Certificate\CertificateRenderer;
use App\Services\Opportunity\SyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Port of Django apps.opportunity.tasks.backfill_missing_certificates.
 *
 * Certificates are rendered as HTML by CertificateRenderer — the old `.txt`
 * placeholder is what broke Arabic names, since plain text does no shaping.
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
                $path = CertificateRenderer::store($registration);

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
