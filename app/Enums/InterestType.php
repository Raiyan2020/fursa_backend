<?php

namespace App\Enums;

enum InterestType: string
{
    case PERSONAL = 'personal';
    case VOLUNTEER = 'volunteer';
    case LEARNSHARE = 'learnshare';
    case EVENT = 'event';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
