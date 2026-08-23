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
        'preparation_validity_days',
        'preparation_validity_hours',
        'preparation_reminder_hours_before',
        'is_deleted',
        'deleted_at',
    ];
}
