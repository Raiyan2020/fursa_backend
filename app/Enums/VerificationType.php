<?php

namespace App\Enums;

enum VerificationType: string
{
    case ACCOUNT_ACTIVATION = 'account_activation';
    case FORGOT_PASSWORD = 'forgot_password';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
