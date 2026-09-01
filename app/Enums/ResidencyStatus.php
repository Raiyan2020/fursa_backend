<?php

namespace App\Enums;

/**
 * Only meaningful for a non-Kuwaiti volunteer, and only decides which
 * identifier is mandatory at registration:
 *   RESIDENT     -> civil_id required (same as a Kuwaiti)
 *   NON_RESIDENT -> passport_number required instead
 */
enum ResidencyStatus: string
{
    case RESIDENT = 'resident';
    case NON_RESIDENT = 'non_resident';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return __('admin.residency_statuses.'.$this->value);
    }
}
