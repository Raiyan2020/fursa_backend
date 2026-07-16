<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationStatistic extends Model
{
    protected $fillable = [
        'user_id', 'year', 'month', 'organization_hours', 'vol_opportunity_organized',
        'learn_opportunity_organized', 'sponsored', 'badge_id',
    ];

    protected $casts = [
        'organization_hours' => 'float',
        'vol_opportunity_organized' => 'float',
        'learn_opportunity_organized' => 'float',
        'sponsored' => 'float',
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
