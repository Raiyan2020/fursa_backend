<?php

namespace App\Services\Certificate;

use App\Models\VolunteerOpportunityRegistration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * Renders a volunteer-opportunity certificate. Mirrors CertificateRenderer
 * (the Learn&Serve one) but reads total hours from the registration's
 * attendance rows, since VolunteerOpportunity has no per-registration hours
 * column of its own.
 */
class VolunteerCertificateRenderer
{
    /** Where rendered certificates live on the public disk. */
    public const DIRECTORY = 'certificates';

    public static function html(VolunteerOpportunityRegistration $registration): string
    {
        return View::make('certificates.volunteer_opportunity', self::data($registration))->render();
    }

    /**
     * Render and persist, returning the stored path (relative to the public disk).
     */
    public static function store(VolunteerOpportunityRegistration $registration): string
    {
        $path = self::DIRECTORY.'/volunteer_registration_'.$registration->id.'.html';

        Storage::disk('public')->put($path, self::html($registration));

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    public static function data(VolunteerOpportunityRegistration $registration): array
    {
        $registration->loadMissing(['user', 'opportunity.creator.organizationProfile', 'attendances']);

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

        $totalHours = $registration->attendances
            ->filter(fn ($attendance) => $attendance->is_attended && ! $attendance->is_deleted)
            ->sum('total_hours');

        return [
            'name' => $name !== '' ? $name : ($user->username ?? '—'),
            'is_arabic_name' => $isArabicName,
            'title_ar' => $opportunity?->title_ar,
            'title_en' => $opportunity?->title_en,
            'total_hours' => round((float) $totalHours, 2),
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
