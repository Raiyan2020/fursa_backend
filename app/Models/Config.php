<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'cycle_type',
        'cycle_scope',
        'cycle_year',
        'cycle_index',
        'unit',
        'duration',
        'number_of_opportunities',
        'time_duration',
        'time_unit',
        'manual_attendance_threshold',
        'economic_impact_rate_kwd',
        'platform_fee_percentage',
        'preparation_validity_days',
        'preparation_validity_hours',
        'preparation_reminder_hours_before',
        'self_check_out_grace_hours',
        'is_deleted',
        'deleted_at',
    ];

    /**
     * BE-74 — the single source of truth for the platform fee, shared by
     * LearnServeOpportunity::platformFeePercentage() (BE-71) and any payload
     * a publisher reads before an opportunity exists yet.
     */
    public static function platformFeePercentage(): float
    {
        return (float) (static::query()->value('platform_fee_percentage') ?? 7);
    }

    /**
     * BE-75 — how long after a session's scheduled end a self-scan departure
     * is still accepted.
     */
    public static function selfCheckOutGraceHours(): float
    {
        return (float) (static::query()->value('self_check_out_grace_hours') ?? 2);
    }
}
