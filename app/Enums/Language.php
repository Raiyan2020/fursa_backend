<?php

namespace App\Enums;

enum Language: string
{
    case EN = 'en';
    case AR = 'ar';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
