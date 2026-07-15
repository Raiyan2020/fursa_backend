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

    public function label(): string
    {
        return match ($this) {
            self::VOLUNTEER => __('admin.user_types.volunteer'),
            self::ORGANIZATION => __('admin.user_types.organization'),
            self::ADMIN => __('admin.user_types.admin'),
        };
    }
}
