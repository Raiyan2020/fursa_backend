<?php

namespace App\Services\Certificate;

use App\Models\LearnServeOpportunityRegistration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * Renders a development-opportunity certificate.
 *
 * The previous implementation wrote a plain `.txt` placeholder, which is why
 * Arabic names came out broken — there was no text shaping at all. Rendering
 * HTML instead hands shaping and bidi to the browser, which gets Arabic right
 * with no extra dependency, and prints to PDF cleanly.
 *
 * To switch to a real server-side PDF later, install mpdf/mpdf (best Arabic
 * support of the PHP writers) and feed it the exact same HTML from html():
 *
 *     $mpdf = new \Mpdf\Mpdf(['directionality' => 'rtl']);
 *     $mpdf->WriteHTML(CertificateRenderer::html($registration));
 */
class CertificateRenderer
{
    /** Where rendered certificates live on the public disk. */
    public const DIRECTORY = 'certificates';

    public static function html(LearnServeOpportunityRegistration $registration): string
    {
        return View::make('certificates.learn_serve', self::data($registration))->render();
    }

    /**
     * Render and persist, returning the stored path (relative to the public disk).
     */
    public static function store(LearnServeOpportunityRegistration $registration): string
    {
        $path = self::DIRECTORY.'/registration_'.$registration->id.'.html';

        Storage::disk('public')->put($path, self::html($registration));

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    public static function data(LearnServeOpportunityRegistration $registration): array
    {
        $registration->loadMissing(['user', 'opportunity.certificateType', 'opportunity.learningType', 'opportunity.creator']);

        $user = $registration->user;
        $opportunity = $registration->opportunity;

        $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        // Arabic scripts need the RTL layout and an Arabic-capable font stack;
        // detecting from the name keeps a mixed audience rendering correctly.
        $isArabicName = (bool) preg_match('/\p{Arabic}/u', $name);

        $organizer = $opportunity?->creator;
        $organizerName = trim(
            ($organizer?->organizationProfile?->company_name ?? '')
            ?: (($organizer?->first_name ?? '').' '.($organizer?->last_name ?? ''))
        );

        return [
            'name' => $name !== '' ? $name : ($user->username ?? '—'),
            'is_arabic_name' => $isArabicName,
            'title_ar' => $opportunity?->title_ar,
            'title_en' => $opportunity?->title_en,
            'certificate_type' => $opportunity?->certificateType?->value_ar
                ?: $opportunity?->certificateType?->value_en,
            'learning_type' => $opportunity?->learningType?->value_ar
                ?: $opportunity?->learningType?->value_en,
            'organizer_name' => $organizerName !== '' ? $organizerName : null,
            'start_date' => optional($opportunity?->start_date)->format('Y-m-d'),
            'end_date' => optional($opportunity?->end_date)->format('Y-m-d'),
            'civil_id' => $user?->civil_id,
            'registration_id' => $registration->id,
            'issued_at' => now()->format('Y-m-d'),
            'background_url' => Storage::disk('public')->exists('certificate_background.png')
                ? getimg('certificate_background.png')
                : null,
        ];
    }
}
