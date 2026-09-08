<?php

namespace App\Services\Certificate;

use App\Enums\OpportunityStatus;
use App\Models\VolunteerOpportunityRegistration;
use App\Services\Mail\DynamicEmailService;
use App\Services\Notification\NotificationService;
use App\Services\Opportunity\SyncService;
use Illuminate\Support\Facades\Log;

/**
 * Certificate issuance for VolunteerOpportunity registrations, mirroring the
 * Learn&Serve pipeline (CertificateRenderer + fursa:backfill-missing-certificates)
 * but for a different trigger:
 *
 *  - automatic: as soon as the opportunity completes (hooked from
 *    fursa:advance-statuses), every already-attended registration is issued
 *    a certificate and emailed immediately.
 *  - manual: an organizer-triggered "send certificates" action for
 *    registrations attended (or corrected) after that first automatic pass
 *    already ran - covers late/corrected attendance on a completed opportunity.
 *
 * Both paths call issueEligible(), so there is exactly one eligibility rule
 * and one issuance code path.
 */
class VolunteerCertificateService
{
    /**
     * Issue certificates for every eligible, not-yet-certified registration -
     * optionally scoped to one opportunity. Eligible means: the opportunity
     * has completed, and the registration has at least one attended
     * attendance row (the hours printed on the certificate are the sum of
     * those rows at issuance time).
     */
    public static function issueEligible(?int $opportunityId = null): int
    {
        $query = VolunteerOpportunityRegistration::query()
            ->notDeleted()
            ->where('is_certified', false)
            ->whereHas('opportunity', function ($q) use ($opportunityId) {
                $q->where('opportunity_status', OpportunityStatus::COMPLETED);
                if ($opportunityId) {
                    $q->where('id', $opportunityId);
                }
            })
            ->whereHas('attendances', fn ($q) => $q->where('is_attended', true)->where('is_deleted', false))
            ->with(['user', 'opportunity.creator.organizationProfile', 'attendances']);

        $issued = 0;
        foreach ($query->get() as $registration) {
            if (self::issue($registration)) {
                $issued++;
            }
        }

        return $issued;
    }

    public static function issue(VolunteerOpportunityRegistration $registration): bool
    {
        if ($registration->is_certified) {
            return false;
        }

        try {
            $path = VolunteerCertificateRenderer::store($registration);
        } catch (\Throwable $e) {
            Log::error('Volunteer certificate render failed for registration '.$registration->id.': '.$e->getMessage());

            return false;
        }

        $registration->certificate_image = $path;
        $registration->is_certified = true;
        $registration->save();

        if ($registration->user_id) {
            SyncService::syncUser((int) $registration->user_id);
        }

        self::notify($registration);

        return true;
    }

    protected static function notify(VolunteerOpportunityRegistration $registration): void
    {
        $user = $registration->user;
        if (! $user) {
            return;
        }

        $opportunity = $registration->opportunity;
        $titleEn = $opportunity?->title_en ?? 'Opportunity';
        $titleAr = $opportunity?->title_ar ?? $titleEn;

        try {
            NotificationService::createForUsers(
                "Certificate issued: {$titleEn}",
                "تم إصدار شهادتك: {$titleAr}",
                "Your certificate for '{$titleEn}' is ready in your profile.",
                "شهادتك في '{$titleAr}' جاهزة الآن في ملفك الشخصي.",
                [$user->id]
            );
        } catch (\Throwable $e) {
            Log::warning('Volunteer certificate notification failed', [
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            DynamicEmailService::send('volunteer_certificate_issued', $user, [
                'first_name' => $user->first_name ?? '',
                'opportunity_title_en' => $titleEn,
                'opportunity_title_ar' => $titleAr,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Volunteer certificate email failed', [
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
