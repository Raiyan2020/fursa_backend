<?php

namespace App\Enums;

enum SocialMediaProvider: string
{
    case GOOGLE = 'google';
    case LINKEDIN = 'linkedin';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
