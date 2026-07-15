<?php

namespace App\Enums;

enum DeletionStatus: string
{
    case NOT_REQUESTED = 'not_requested';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return __('admin.statuses.'.$this->value);
    }
}
