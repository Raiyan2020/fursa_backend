<?php

namespace App\Enums;

enum UserType: string
{
    case VOLUNTEER = 'volunteer';
    case ORGANIZATION = 'organization';
    case ADMIN = 'admin';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
