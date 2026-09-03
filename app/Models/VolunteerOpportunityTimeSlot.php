<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled day of a volunteer opportunity.
 *
 * Lets an opportunity run on non-consecutive days, and lets each day carry its
 * own hours instead of inheriting one time range for the whole opportunity.
 */
class VolunteerOpportunityTimeSlot extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'opportunity_id', 'date', 'start_time', 'end_time', 'is_deleted', 'deleted_at',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class, 'opportunity_id');
    }

    /**
     * Hours this day contributes, used when crediting attendance.
     */
    public function durationInHours(): float
    {
        if (! $this->start_time || ! $this->end_time) {
            return 0.0;
        }

        $start = strtotime((string) $this->start_time);
        $end = strtotime((string) $this->end_time);
        $hours = ($end - $start) / 3600;

        // An end time before the start means the shift crosses midnight.
        if ($hours < 0) {
            $hours += 24;
        }

        return round($hours, 2);
    }
}
