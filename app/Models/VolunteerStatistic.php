<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerStatistic extends Model
{
    protected $fillable = [
        'user_id', 'year', 'month', 'volunteer_hours', 'opportunities_participated',
        'opportunities_organized', 'certificates_earned', 'rank', 'badge_id',
    ];

    protected $casts = [
        'volunteer_hours' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}
