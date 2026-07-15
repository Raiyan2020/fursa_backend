<?php

namespace App\Enums;

enum Nationality: string
{
    case KUWAITIS = 'kuwaitis';
    case ALL = 'all';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Accepts official values plus common aliases (e.g. KW → kuwaitis).
     */
    public static function tryFromInput(mixed $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof self) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'kuwaitis', 'kuwait', 'kuwaiti', 'kw', 'kwt' => self::KUWAITIS,
            'all' => self::ALL,
            'other' => self::OTHER,
            default => self::tryFrom($normalized),
        };
    }

    public static function normalize(mixed $value): ?string
    {
        return self::tryFromInput($value)?->value;
    }
}
