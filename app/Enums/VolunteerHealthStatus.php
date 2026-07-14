<?php

namespace App\Enums;

enum VolunteerHealthStatus: string
{
    case YES = 'yes';
    case NO = 'no';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
