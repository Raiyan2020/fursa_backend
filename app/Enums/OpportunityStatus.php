<?php

namespace App\Enums;

enum OpportunityStatus: string
{
    case UPCOMING = 'upcoming';
    case INPROGRESS = 'inprogress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
