<?php

namespace App\Enums;

/**
 * BE-77 — the four-way audience an opportunity/event can be published for.
 *
 * Distinct from `Nationality` (a person's own nationality: kuwaitis/other):
 * this is what a publisher targets, not what a user is. The three specific
 * values partition the population; `all` is their union and the default.
 */
enum OpportunityNationality: string
{
    case ALL = 'all';
    case KUWAITIS = 'kuwaitis';
    case NON_KUWAITI_ARABIC = 'non_kuwaiti_arabic';
    case NON_ARABIC = 'non_arabic';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::ALL => 'all nationalities',
            self::KUWAITIS => 'Kuwaitis',
            self::NON_KUWAITI_ARABIC => 'non-Kuwaiti Arabic speakers',
            self::NON_ARABIC => 'non-Arabic speakers',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::ALL => 'كل الجنسيات',
            self::KUWAITIS => 'كويتيين',
            self::NON_KUWAITI_ARABIC => 'غير كويتي ناطق بالعربية',
            self::NON_ARABIC => 'غير ناطق بالعربية',
        };
    }

    /**
     * Keeps `opportunity_nationality` and the legacy `is_kuwaitis` boolean in
     * step for at least one release: whichever one a write actually sent
     * wins, and the other is derived from it so old and new readers both see
     * a consistent value.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFields(array $data): array
    {
        if (array_key_exists('opportunity_nationality', $data) && $data['opportunity_nationality'] !== null && $data['opportunity_nationality'] !== '') {
            $data['is_kuwaitis'] = $data['opportunity_nationality'] === self::KUWAITIS->value;
        } elseif (array_key_exists('is_kuwaitis', $data) && $data['is_kuwaitis'] !== null) {
            $data['opportunity_nationality'] = $data['is_kuwaitis'] ? self::KUWAITIS->value : self::ALL->value;
        }

        return $data;
    }

    /**
     * Whether a user qualifies for this audience.
     *
     * A Kuwaiti is definitionally an Arabic speaker, so nationality alone
     * answers the language question for that subset of users — the explicit
     * `speaks_arabic` signal only carries information for non-Kuwaitis.
     *
     * `null` on either signal (unanswered nationality, or `speaks_arabic`
     * never asked) passes rather than blocks — the client's own rule for
     * `speaks_arabic` (BE-77 part B), applied the same way to an unanswered
     * nationality so nobody is refused over a question we never asked them.
     */
    public function matches(?Nationality $userNationality, ?bool $speaksArabic): bool
    {
        $isKuwaiti = $userNationality === Nationality::KUWAITIS;
        $resolvedSpeaksArabic = $isKuwaiti ? true : $speaksArabic;

        return match ($this) {
            self::ALL => true,
            self::KUWAITIS => $userNationality === null || $isKuwaiti,
            self::NON_KUWAITI_ARABIC => ! $isKuwaiti
                && ($resolvedSpeaksArabic === null || $resolvedSpeaksArabic === true),
            self::NON_ARABIC => $resolvedSpeaksArabic === null || $resolvedSpeaksArabic === false,
        };
    }
}
