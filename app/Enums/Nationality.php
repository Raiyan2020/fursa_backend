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
}
