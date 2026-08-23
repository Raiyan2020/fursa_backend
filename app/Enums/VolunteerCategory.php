<?php

namespace App\Enums;

enum VolunteerCategory: string
{
    case ENVIRONMENTAL = 'environmental';
    case CHARITY = 'charity';
    case ORGANIZATIONAL = 'organizational';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::ENVIRONMENTAL => 'Environmental',
            self::CHARITY => 'Charity',
            self::ORGANIZATIONAL => 'Organizational',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::ENVIRONMENTAL => 'بيئي',
            self::CHARITY => 'خيري',
            self::ORGANIZATIONAL => 'تنظيمي',
        };
    }

    /**
     * The client only counts beneficiaries for charity opportunities.
     */
    public function countsBeneficiaries(): bool
    {
        return $this === self::CHARITY;
    }
}
